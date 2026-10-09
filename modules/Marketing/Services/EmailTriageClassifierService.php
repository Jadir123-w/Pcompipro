<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;

/**
 * EmailTriageClassifierService — MKT-01 Email Triage ET2.
 *
 * Procesa correos en estado `received` ejecutando 2 pasos:
 *
 *   1. CLASIFICAR: el LLM lee el correo y devuelve una de las 5 categorías
 *      (rfq | consulta | no_relacionado | spam | otro) + confidence + reason.
 *      → Marca el correo como `classified` y persiste classification + meta.
 *
 *   2. REDACTAR BORRADOR: si la clasificación fue rfq o consulta, el LLM
 *      redacta una respuesta apropiada en el tono COMPIPRO.
 *      → Crea MarketingEmailDraft con status='pending' y marca el correo
 *        como `draft_pending`.
 *
 * Para clasificaciones no_relacionado/spam/otro: marca el correo como
 * `discarded` y NO crea borrador (no hay nada que responder).
 *
 * Reutiliza la capa de IA existente (AIProviderInterface) — el mismo
 * provider que usa el resto del módulo Marketing (configurable desde
 * Config Chatbot → tab IA).
 */
class EmailTriageClassifierService
{
    /** @var AIProviderInterface */
    private $provider;

    /** @var string */
    private $classifierPrompt;

    /** @var string */
    private $draftPrompt;

    public function __construct(AIProviderInterface $provider)
    {
        $this->provider = $provider;
        $this->classifierPrompt = $this->loadPrompt('mkt01-email-triage-classifier-prompt.md');
        $this->draftPrompt      = $this->loadPrompt('mkt01-email-triage-draft-prompt.md');
    }

    /**
     * Procesar un correo: clasificar + (si aplica) generar borrador.
     *
     * @param  MarketingEmailReceived  $email
     * @return array  ['classified'=>bool,'classification'=>?string,'draft_id'=>?int,'error'=>?string]
     */
    public function process(MarketingEmailReceived $email)
    {
        if (!$email->isReceived()) {
            return [
                'classified'     => false,
                'classification' => $email->classification,
                'draft_id'       => null,
                'error'          => 'Correo no está en estado received (actual: ' . $email->status . ')',
            ];
        }

        // Paso 1: Clasificar
        $classification = $this->classifyEmail($email);
        if (!empty($classification['error'])) {
            $email->markError('Clasificación: ' . $classification['error']);
            return [
                'classified'     => false,
                'classification' => null,
                'draft_id'       => null,
                'error'          => $classification['error'],
            ];
        }

        // Persistir clasificación
        $email->update([
            'status'                    => MarketingEmailReceived::STATUS_CLASSIFIED,
            'classification'            => $classification['classification'],
            'classification_confidence' => $classification['confidence'],
            'classification_reason'     => $classification['reason'],
            'urgency_level'             => $classification['urgency_level'],
            'ai_provider'               => $classification['ai_provider'],
            'ai_model'                  => $classification['ai_model'],
        ]);

        // Registrar lead en Comercial cuando el correo es una cotización (RFQ).
        // El lead nace al clasificar (no espera la aprobación del borrador) para
        // que aparezca en el listado de Leads del ERP que ve la ejecutiva,
        // cumpliendo el alcance del agente "registra lead en ERP/CRM". (MKT-01 T1.)
        if ($classification['classification'] === MarketingEmailReceived::CLASSIFICATION_RFQ) {
            $this->registerLeadFromEmail($email, $classification);
        }

        // Paso 2: ¿corresponde generar borrador?
        $needsDraft = in_array($classification['classification'], [
            MarketingEmailReceived::CLASSIFICATION_RFQ,
            MarketingEmailReceived::CLASSIFICATION_CONSULTA,
        ], true);

        if (!$needsDraft) {
            // No se responde automáticamente — descartar (un humano puede revisar luego)
            $email->update(['status' => MarketingEmailReceived::STATUS_DISCARDED]);
            return [
                'classified'     => true,
                'classification' => $classification['classification'],
                'draft_id'       => null,
                'error'          => null,
            ];
        }

        // Generar borrador
        $draftResult = $this->generateDraft($email->fresh(), $classification);
        if (!empty($draftResult['error'])) {
            // Mantener status=classified para reintento futuro; loguear sin fallar el run
            Log::warning('EmailTriageClassifierService: clasificó OK pero falló al generar borrador', [
                'email_id' => $email->id,
                'error'    => $draftResult['error'],
            ]);
            return [
                'classified'     => true,
                'classification' => $classification['classification'],
                'draft_id'       => null,
                'error'          => 'Borrador: ' . $draftResult['error'],
            ];
        }

        // Persistir borrador
        $draft = MarketingEmailDraft::create([
            'email_received_id' => $email->id,
            'draft_subject'     => $draftResult['draft_subject'],
            'draft_body'        => $draftResult['draft_body'],
            'to_email'          => $draftResult['to_email'],
            'cc_emails'         => $draftResult['cc_emails'],
            'tone_used'         => $draftResult['tone_used'],
            'data_gaps'         => $draftResult['data_gaps'],
            'ai_provider'       => $classification['ai_provider'],
            'ai_model'          => $classification['ai_model'],
            'status'            => MarketingEmailDraft::STATUS_PENDING,
        ]);

        // Marcar el correo como draft_pending
        $email->update(['status' => MarketingEmailReceived::STATUS_DRAFT_PENDING]);

        return [
            'classified'     => true,
            'classification' => $classification['classification'],
            'draft_id'       => $draft->id,
            'error'          => null,
        ];
    }

    /**
     * Registrar el lead en Comercial a partir de un correo clasificado como RFQ.
     *
     * Reusa el destino común (LeadRegistrationService::createComercialLeadFromData),
     * el mismo que alimenta el chatbot, para que el correo aparezca en el listado
     * de Leads del ERP con resumen + datos + etiquetas. No rompe el flujo del
     * triage si algo falla.
     *
     * @param  MarketingEmailReceived  $email
     * @param  array                   $classification
     * @return void
     */
    private function registerLeadFromEmail(MarketingEmailReceived $email, array $classification)
    {
        try {
            $entities  = (isset($classification['entities']) && is_array($classification['entities']))
                ? $classification['entities'] : [];
            $fromEmail = (string) $email->from_email;
            $subject   = (string) $email->subject;

            // Dedup: el estado del correo (received→classified) ya evita
            // reprocesar, pero por robustez no duplicamos el lead de este correo.
            $yaExiste = \Modules\Comercial\Models\Lead::where('origen', 'email_triage_mkt01')
                ->where('email', $fromEmail)
                ->where('asunto_email', $subject)
                ->exists();
            if ($yaExiste) {
                return;
            }

            app(LeadRegistrationService::class)->createComercialLeadFromData([
                'origen'       => 'email_triage_mkt01',
                'descripcion'  => $this->buildLeadDescripcionFromEmail($email, $classification, $entities),
                'contacto'     => !empty($entities['contacto']) ? $entities['contacto'] : $email->from_name,
                'telefono'     => !empty($entities['telefono']) ? $entities['telefono'] : null,
                'email'        => $fromEmail,
                'razon_social' => !empty($entities['empresa']) ? $entities['empresa'] : null,
                'ruc'          => !empty($entities['ruc']) ? $entities['ruc'] : '',
                'asunto_email' => $subject,
                'prioridad'    => isset($classification['urgency_level']) ? $classification['urgency_level'] : 'normal',
            ]);

            Log::info('EmailTriageClassifierService: lead registrado desde correo RFQ', [
                'email_id' => $email->id,
                'from'     => $fromEmail,
            ]);
        } catch (\Exception $e) {
            // No romper el triage si el registro del lead falla.
            Log::warning('EmailTriageClassifierService: no se pudo registrar el lead del correo', [
                'email_id' => $email->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Armar la descripción del lead: resumen + datos + etiquetas del correo RFQ.
     *
     * @param  MarketingEmailReceived  $email
     * @param  array                   $classification
     * @param  array                   $entities
     * @return string
     */
    private function buildLeadDescripcionFromEmail(MarketingEmailReceived $email, array $classification, array $entities)
    {
        $partes = ['Lead por correo (Email Triage MKT-01).'];

        if (!empty($email->subject)) {
            $partes[] = 'Asunto: ' . $email->subject;
        }
        if (!empty($classification['summary_es'])) {
            $partes[] = 'Resumen: ' . $classification['summary_es'];
        }
        if (!empty($entities['items_solicitados'])) {
            $partes[] = 'Productos: ' . $entities['items_solicitados'];
        }
        if (!empty($entities['ciudad'])) {
            $partes[] = 'Ciudad: ' . $entities['ciudad'];
        }
        if (!empty($entities['fecha_objetivo'])) {
            $partes[] = 'Fecha objetivo: ' . $entities['fecha_objetivo'];
        }
        if (!empty($entities['tags']) && is_array($entities['tags'])) {
            $partes[] = 'Etiquetas: ' . implode(', ', $entities['tags']);
        }

        return implode("\n", $partes);
    }

    /**
     * Llamar al LLM para clasificar el correo. Devuelve estructura simple o ['error' => '...'].
     */
    private function classifyEmail(MarketingEmailReceived $email)
    {
        $context = $this->buildClassifierContext($email);

        try {
            $rawResponse = $this->provider->sendMessage(
                $this->classifierPrompt,
                [['role' => 'user', 'content' => $context]],
                ['temperature' => 0.0, 'max_tokens' => 1500]
            );
        } catch (AIProviderException $e) {
            return ['error' => 'Provider IA: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => 'Excepción: ' . $e->getMessage()];
        }

        $parsed = $this->parseJsonResponse($rawResponse);
        if ($parsed === null) {
            return ['error' => 'Respuesta del LLM no es JSON válido'];
        }

        if (!isset($parsed['classification']) || !in_array($parsed['classification'], MarketingEmailReceived::CLASSIFICATIONS, true)) {
            return ['error' => 'Respuesta del LLM tiene classification inválida: ' . json_encode($parsed)];
        }

        // Urgencia (R7 del prompt): si el LLM no la devolvió o la devolvió mal,
        // default conservador "normal" para no inflar falsos positivos.
        $urgencyLevel = MarketingEmailReceived::URGENCY_NORMAL;
        if (isset($parsed['urgency_level'])
            && in_array($parsed['urgency_level'], MarketingEmailReceived::URGENCY_LEVELS, true)) {
            $urgencyLevel = $parsed['urgency_level'];
        }

        return [
            'classification' => $parsed['classification'],
            'confidence'     => isset($parsed['confidence']) ? (float) $parsed['confidence'] : 50.0,
            'reason'         => isset($parsed['reason']) ? (string) $parsed['reason'] : null,
            'summary_es'     => isset($parsed['summary_es']) ? (string) $parsed['summary_es'] : null,
            'lang_detected'  => isset($parsed['lang_detected']) ? (string) $parsed['lang_detected'] : 'es',
            'urgency_level'  => $urgencyLevel,
            'urgency_reason' => isset($parsed['urgency_reason']) ? (string) $parsed['urgency_reason'] : null,
            // Datos comerciales para el lead (solo vienen cuando el LLM clasifica rfq).
            'entities'       => (isset($parsed['entities']) && is_array($parsed['entities'])) ? $parsed['entities'] : [],
            'ai_provider'    => method_exists($this->provider, 'getProviderName') ? $this->provider->getProviderName() : 'unknown',
            'ai_model'       => method_exists($this->provider, 'getModelName')    ? $this->provider->getModelName()    : null,
        ];
    }

    /**
     * Llamar al LLM para redactar el borrador de respuesta.
     */
    private function generateDraft(MarketingEmailReceived $email, array $classification)
    {
        $signature = (string) ChatbotConfig::getOrEnv('email_default_from_name', 'email.default_from_name', 'Equipo COMPIPRO');
        $userName  = (string) ChatbotConfig::getOrEnv('email_user', 'email.user', 'informacion@compipro.net');
        $includeDisclaimer = (bool) ChatbotConfig::getOrEnv('email_disclaimer_ai', 'email.disclaimer_ai', true);

        $payload = [
            'classification' => $classification['classification'],
            'from_email'     => $email->from_email,
            'from_name'      => $email->from_name,
            'subject'        => $email->subject,
            'body_plain'     => mb_substr((string) $email->body_plain, 0, 8000), // límite anti-flood
            'lang_detected'  => $classification['lang_detected'],
            'include_ai_disclaimer' => $includeDisclaimer,
            'company_signature'     => $signature . "\n" . $userName,
        ];

        try {
            $rawResponse = $this->provider->sendMessage(
                $this->draftPrompt,
                [['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                ['temperature' => 0.4, 'max_tokens' => 2000]
            );
        } catch (AIProviderException $e) {
            return ['error' => 'Provider IA: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => 'Excepción: ' . $e->getMessage()];
        }

        $parsed = $this->parseJsonResponse($rawResponse);
        if ($parsed === null) {
            return ['error' => 'Respuesta del LLM no es JSON válido'];
        }

        $shouldDraft = !empty($parsed['should_draft']);
        if (!$shouldDraft) {
            return ['error' => 'LLM decidió no generar borrador: ' . (isset($parsed['skip_reason']) ? $parsed['skip_reason'] : 'sin razón')];
        }

        return [
            'draft_subject' => isset($parsed['draft_subject']) ? (string) $parsed['draft_subject'] : ('Re: ' . $email->subject),
            'draft_body'    => isset($parsed['draft_body']) ? (string) $parsed['draft_body'] : '',
            'to_email'      => isset($parsed['to_email']) ? (string) $parsed['to_email'] : $email->from_email,
            'cc_emails'     => isset($parsed['cc_emails']) && is_array($parsed['cc_emails']) ? $parsed['cc_emails'] : [],
            'tone_used'     => isset($parsed['tone_used']) ? (string) $parsed['tone_used'] : null,
            'data_gaps'     => isset($parsed['data_gaps']) && is_array($parsed['data_gaps']) ? $parsed['data_gaps'] : [],
            'error'         => null,
        ];
    }

    /**
     * Construir el contexto del clasificador.
     */
    private function buildClassifierContext(MarketingEmailReceived $email)
    {
        $ctx  = "From: {$email->from_email}";
        if ($email->from_name) $ctx .= " ({$email->from_name})";
        $ctx .= "\nSubject: " . (string) $email->subject;
        $ctx .= "\nReceived: " . ($email->received_at ? $email->received_at->format('Y-m-d H:i') : 'unknown');
        $ctx .= "\nHas attachments: " . ($email->has_attachments ? 'sí' : 'no');
        $ctx .= "\n\n--- BODY ---\n";
        $ctx .= mb_substr((string) $email->body_plain, 0, 8000);
        return $ctx;
    }

    /**
     * Parser tolerante a respuestas JSON envueltas en code-fences o con prefacio.
     * (Mismo patrón que GridPatternAnalyzer / GridGeneratorEngine.)
     */
    private function parseJsonResponse($raw)
    {
        $text = trim((string) $raw);
        if ($text === '') return null;

        if (preg_match('/^```(?:json)?\s*(.+?)\s*```$/is', $text, $m)) {
            $text = trim($m[1]);
        } elseif (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first = strpos($text, '{');
            $last  = strrpos($text, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $candidate = substr($text, $first, $last - $first + 1);
                $decoded = json_decode($candidate, true);
            }
        }

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    /**
     * Cargar un prompt del directorio Prompts/.
     */
    private function loadPrompt($filename)
    {
        $path = __DIR__ . '/../Prompts/' . $filename;
        if (!file_exists($path)) {
            Log::error('EmailTriageClassifierService: prompt no encontrado', ['path' => $path]);
            return 'Eres un asistente. Responde en JSON válido.';
        }
        return file_get_contents($path);
    }
}

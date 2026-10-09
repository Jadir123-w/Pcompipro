<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Comercial\Models\Lead;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\MarketingChatbotLead;

/**
 * LeadRegistrationService
 *
 * Servicio de registro inteligente de leads.
 *
 * Valida los 3 criterios simultáneos antes de crear un lead:
 * 1. Contacto válido (correo o celular)
 * 2. Intención comercial clara
 * 3. Al menos un dato útil
 *
 * Si ya existe un lead para la sesión, lo actualiza en vez de crear duplicado.
 */
class LeadRegistrationService
{
    /**
     * Registrar o actualizar un lead desde los datos del LLM.
     *
     * @param  array   $aiResponse   Respuesta completa del AIConversationEngine
     * @param  string  $sessionId
     * @param  string  $channel
     * @return MarketingChatbotLead|null  null si no cumple criterios
     */
    public function registerOrUpdate(array $aiResponse, $sessionId, $channel = 'web')
    {
        $entities = isset($aiResponse['entities_extracted']) ? $aiResponse['entities_extracted'] : [];
        $actions  = isset($aiResponse['actions']) ? $aiResponse['actions'] : [];
        $state    = isset($aiResponse['conversation_state']) ? $aiResponse['conversation_state'] : [];

        // Verificar si el LLM indica que debe registrarse
        $shouldRegister = isset($actions['should_register_lead']) ? $actions['should_register_lead'] : false;

        if (!$shouldRegister) {
            return null;
        }

        // Doble verificación: validar los 3 criterios desde los datos reales
        if (!$this->meetsRegistrationCriteria($entities, $state)) {
            Log::info('LeadRegistrationService: LLM pidió registrar pero datos insuficientes', [
                'session_id' => $sessionId,
                'entities'   => $entities,
            ]);
            return null;
        }

        // Buscar lead existente para esta sesión
        $lead = MarketingChatbotLead::where('session_id', $sessionId)->first();

        if ($lead) {
            return $this->updateLead($lead, $aiResponse);
        }

        return $this->createLead($aiResponse, $sessionId, $channel);
    }

    /**
     * Crear un lead nuevo.
     *
     * @param  array   $aiResponse
     * @param  string  $sessionId
     * @param  string  $channel
     * @return MarketingChatbotLead
     */
    private function createLead(array $aiResponse, $sessionId, $channel)
    {
        $entities = $aiResponse['entities_extracted'];
        $actions  = $aiResponse['actions'];

        $lead = MarketingChatbotLead::create([
            // Datos de contacto
            'name'         => !empty($entities['nombre_contacto']) ? $entities['nombre_contacto'] : 'Sin nombre',
            'email'        => !empty($entities['correo']) ? $entities['correo'] : null,
            'phone'        => !empty($entities['celular']) ? $entities['celular'] : null,
            // company_name es NOT NULL en BD: defaulteamos a '' si el LLM no lo capturó
            'company_name' => !empty($entities['empresa']) ? $entities['empresa'] : '',

            // Origen
            'source'  => 'marketing_chatbot',
            'status'  => MarketingChatbotLead::STATUS_NEW,
            'channel' => $channel,

            // Inteligencia conversacional
            'intent_detected'      => isset($aiResponse['intent']) ? $aiResponse['intent'] : null,
            'conversation_summary' => isset($aiResponse['lead_summary']) ? $aiResponse['lead_summary'] : null,
            'structured_data'      => $this->buildStructuredData($entities),
            'tags'                 => $this->extractTags($entities),

            // Estado de madurez
            'ready_to_convert' => isset($actions['ready_to_convert']) ? $actions['ready_to_convert'] : false,
            'urgency_level'    => $this->detectUrgency($aiResponse),

            // Asignación — prioridad BD (general_default_assigned_to) → .env → null
            'assigned_to' => \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
                'general_default_assigned_to',
                'default_assigned_to',
                null
            ) ?: null,

            // Referencia a sesión
            'session_id' => $sessionId,

            // Meta legacy (compatibilidad). ai_provider lee BD primero igual que el resto del módulo.
            'meta' => [
                'created_from' => 'mkt01_ai_agent',
                'ai_provider'  => \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
                    'chatbot_ai_provider',
                    'ai_provider',
                    'claude'
                ),
            ],
        ]);

        Log::info('LeadRegistrationService: lead creado', [
            'lead_id'          => $lead->id,
            'session_id'       => $sessionId,
            'intent'           => $lead->intent_detected,
            'ready_to_convert' => $lead->ready_to_convert,
        ]);

        // Asociar attachments huérfanos de la sesión a este lead
        $this->linkAttachmentsToLead($sessionId, $lead->id);

        // Crear también en tabla lead (módulo Comercial)
        $this->createComercialLead($lead, $entities);

        return $lead;
    }

    /**
     * Asocia los attachments subidos durante la sesión al lead recién creado.
     *
     * @param  string  $sessionId
     * @param  int     $leadId
     * @return void
     */
    private function linkAttachmentsToLead($sessionId, $leadId)
    {
        try {
            $linked = ChatbotAttachment::associateToLead($sessionId, $leadId);
            if ($linked > 0) {
                Log::info('LeadRegistrationService: attachments asociados al lead', [
                    'lead_id'    => $leadId,
                    'session_id' => $sessionId,
                    'count'      => $linked,
                ]);
            }
        } catch (\Exception $e) {
            // No fallar la creación del lead si la tabla aún no existe en este tenant
            Log::warning('LeadRegistrationService: no se pudieron asociar attachments', [
                'lead_id'    => $leadId,
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualizar un lead existente con datos nuevos.
     *
     * @param  MarketingChatbotLead  $lead
     * @param  array                 $aiResponse
     * @return MarketingChatbotLead
     */
    private function updateLead(MarketingChatbotLead $lead, array $aiResponse)
    {
        $entities = $aiResponse['entities_extracted'];
        $actions  = $aiResponse['actions'];

        // Solo actualizar campos si los nuevos datos no están vacíos
        $updates = [];

        if (!empty($entities['nombre_contacto']) && empty($lead->name)) {
            $updates['name'] = $entities['nombre_contacto'];
        }
        if (!empty($entities['correo']) && empty($lead->email)) {
            $updates['email'] = $entities['correo'];
        }
        if (!empty($entities['celular']) && empty($lead->phone)) {
            $updates['phone'] = $entities['celular'];
        }
        if (!empty($entities['empresa']) && empty($lead->company_name)) {
            $updates['company_name'] = $entities['empresa'];
        }

        // Siempre actualizar estos campos con la info más reciente
        $updates['intent_detected']      = isset($aiResponse['intent']) ? $aiResponse['intent'] : $lead->intent_detected;
        $updates['conversation_summary'] = isset($aiResponse['lead_summary']) ? $aiResponse['lead_summary'] : $lead->conversation_summary;
        $updates['structured_data']      = $this->buildStructuredData($entities);
        $updates['tags']                 = $this->extractTags($entities);
        $updates['ready_to_convert']     = isset($actions['ready_to_convert']) ? $actions['ready_to_convert'] : $lead->ready_to_convert;
        $updates['urgency_level']        = $this->detectUrgency($aiResponse);

        $lead->update($updates);

        // Vincular cualquier attachment nuevo que haya subido en este turno
        if (!empty($lead->session_id)) {
            $this->linkAttachmentsToLead($lead->session_id, $lead->id);
        }

        // Sincronizar el lead del módulo Comercial: si la conversación trajo
        // info nueva (acciones solicitadas, items nuevos, datos de contacto),
        // la descripción del lead comercial debe reflejarlo. Sin este sync, la
        // ejecutiva veía solo la descripción del primer turno donde se registró.
        $this->syncComercialLead($lead->fresh(), $entities);

        Log::info('LeadRegistrationService: lead actualizado', [
            'lead_id'          => $lead->id,
            'ready_to_convert' => $lead->ready_to_convert,
        ]);

        return $lead->fresh();
    }

    /**
     * Verificar los 3 criterios simultáneos de registro.
     *
     * @param  array  $entities
     * @param  array  $state
     * @return bool
     */
    private function meetsRegistrationCriteria(array $entities, array $state)
    {
        // 1. Contacto válido
        $hasContact = !empty($entities['correo']) || !empty($entities['celular']);

        // 2. Intención comercial clara
        $hasIntent = isset($state['has_commercial_intent']) ? $state['has_commercial_intent'] : false;

        // 3. Dato útil
        $hasData = isset($state['has_useful_data']) ? $state['has_useful_data'] : false;

        // Validación adicional: verificar que realmente hay datos útiles
        if (!$hasData) {
            $hasData = !empty($entities['items_solicitados'])
                || !empty($entities['empresa'])
                || !empty($entities['ciudad_entrega'])
                || !empty($entities['fecha_objetivo']);
        }

        return $hasContact && $hasIntent && $hasData;
    }

    /**
     * Construir datos estructurados para el lead.
     *
     * @param  array  $entities
     * @return array
     */
    private function buildStructuredData(array $entities)
    {
        return [
            'items_solicitados'     => isset($entities['items_solicitados']) ? $entities['items_solicitados'] : [],
            'ciudad_entrega'        => isset($entities['ciudad_entrega']) ? $entities['ciudad_entrega'] : null,
            'fecha_objetivo'        => isset($entities['fecha_objetivo']) ? $entities['fecha_objetivo'] : null,
            'rubro'                 => isset($entities['rubro']) ? $entities['rubro'] : null,
            'evento_feria'          => isset($entities['evento_feria']) ? $entities['evento_feria'] : null,
            'presupuesto_mencionado'=> isset($entities['presupuesto_mencionado']) ? $entities['presupuesto_mencionado'] : null,
            'interes_resumen'       => isset($entities['interes_resumen']) ? $entities['interes_resumen'] : null,
            'acciones_solicitadas'  => isset($entities['acciones_solicitadas']) && is_array($entities['acciones_solicitadas'])
                                            ? $entities['acciones_solicitadas'] : [],
        ];
    }

    /**
     * Extraer tags/categorías de los items solicitados.
     *
     * @param  array  $entities
     * @return array
     */
    private function extractTags(array $entities)
    {
        $tags = [];
        $items = isset($entities['items_solicitados']) ? $entities['items_solicitados'] : [];

        foreach ($items as $item) {
            if (!empty($item['item_categoria'])) {
                $tags[] = $item['item_categoria'];
            }
        }

        return array_unique($tags);
    }

    /**
     * Detectar nivel de urgencia.
     *
     * @param  array  $aiResponse
     * @return string
     */
    private function detectUrgency(array $aiResponse)
    {
        $intent = isset($aiResponse['intent']) ? $aiResponse['intent'] : '';

        if ($intent === 'reclamo_o_urgente') {
            return 'high';
        }

        // Si mencionó fecha cercana, urgencia media
        $entities = isset($aiResponse['entities_extracted']) ? $aiResponse['entities_extracted'] : [];
        $fecha = isset($entities['fecha_objetivo']) ? $entities['fecha_objetivo'] : null;

        if ($fecha !== null) {
            $lower = strtolower($fecha);
            if (strpos($lower, 'urgente') !== false
                || strpos($lower, 'mañana') !== false
                || strpos($lower, 'hoy') !== false
                || strpos($lower, 'esta semana') !== false) {
                return 'high';
            }
            return 'medium';
        }

        return 'low';
    }

    /**
     * Crear un lead en la tabla `lead` del módulo Comercial
     * para que aparezca en el listado de Leads del ERP.
     *
     * @param  MarketingChatbotLead  $chatbotLead
     * @param  array                 $entities
     * @return void
     */
    private function createComercialLead(MarketingChatbotLead $chatbotLead, array $entities)
    {
        // Delega en el método plano reusable (mismo destino que usa el Email
        // Triage). El comportamiento del chatbot no cambia.
        $this->createComercialLeadFromData([
            'origen'       => 'chatbot_mkt01',
            'descripcion'  => $this->buildLeadDescripcion($chatbotLead, $entities),
            'contacto'     => $chatbotLead->name,
            'telefono'     => $chatbotLead->phone,
            'email'        => $chatbotLead->email,
            'prioridad'    => $chatbotLead->urgency_level,
            'razon_social' => $chatbotLead->company_name,
        ]);
    }

    /**
     * Crear un Lead en el módulo Comercial (tabla `lead`) desde datos PLANOS.
     *
     * Es el destino común que ve la ejecutiva en el listado de Leads. Lo usan
     * tanto el chatbot (createComercialLead) como el Email Triage, para no
     * duplicar el insert ni acoplar el registro al formato del LLM.
     *
     * @param  array  $data  Claves soportadas: origen, descripcion, contacto,
     *   telefono, email, razon_social, ruc, asunto_email, prioridad
     *   (acepta 'alta|media|baja' o 'critical|high|medium|normal|low').
     * @return \Modules\Comercial\Models\Lead|null
     */
    public function createComercialLeadFromData(array $data)
    {
        try {
            // Normalizar prioridad → alta|media|baja (acepta niveles del LLM).
            $prioridadMap = [
                'critical' => 'alta', 'high' => 'alta',
                'medium'   => 'media', 'normal' => 'media',
                'low'      => 'baja',
            ];
            $prioridadIn = isset($data['prioridad']) ? (string) $data['prioridad'] : 'media';
            $prioridad = isset($prioridadMap[$prioridadIn]) ? $prioridadMap[$prioridadIn] : $prioridadIn;
            if (!in_array($prioridad, ['alta', 'media', 'baja'], true)) {
                $prioridad = 'media';
            }

            $lead = Lead::create([
                'origen'          => !empty($data['origen']) ? $data['origen'] : 'mkt01',
                'etapa'           => 'nuevo',
                'descripcion'     => !empty($data['descripcion']) ? substr($data['descripcion'], 0, 500) : null,
                'contacto'        => isset($data['contacto']) ? $data['contacto'] : null,
                'telefono'        => isset($data['telefono']) ? $data['telefono'] : null,
                'email'           => isset($data['email']) ? $data['email'] : null,
                'prioridad'       => $prioridad,
                'estado'          => 'activo',
                'ruc'             => isset($data['ruc']) ? $data['ruc'] : '',
                'razon_social'    => isset($data['razon_social']) ? $data['razon_social'] : null,
                'asunto_email'    => isset($data['asunto_email']) ? $data['asunto_email'] : null,
                'estado_revision' => 'pendiente',
                'procesado'       => false,
            ]);

            Log::info('LeadRegistrationService: lead comercial creado', [
                'comercial_lead_id' => $lead->id,
                'origen'            => $lead->origen,
            ]);

            return $lead;
        } catch (\Exception $e) {
            // No fallar el flujo principal si el lead comercial no se crea.
            Log::error('LeadRegistrationService: error creando lead comercial', [
                'origen' => isset($data['origen']) ? $data['origen'] : null,
                'error'  => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sincronizar el lead del módulo Comercial cuando se actualiza el chatbot lead.
     *
     * Necesario porque la conversación puede continuar después del primer registro
     * (cliente da más datos, pide acciones específicas como "envíame catálogo",
     * etc.) y la descripción del lead comercial debe reflejar esos cambios para
     * que la ejecutiva tenga el contexto completo.
     *
     * @param  MarketingChatbotLead  $chatbotLead
     * @param  array                 $entities
     * @return void
     */
    private function syncComercialLead(MarketingChatbotLead $chatbotLead, array $entities)
    {
        try {
            // Buscar el lead comercial asociado a este chatbot_lead.
            // Lo identificamos por email o teléfono (no hay FK directa, es una
            // limitación del schema actual del módulo Comercial).
            $query = Lead::where('origen', 'chatbot_mkt01');
            if (!empty($chatbotLead->email)) {
                $query->where('email', $chatbotLead->email);
            } elseif (!empty($chatbotLead->phone)) {
                $query->where('telefono', $chatbotLead->phone);
            } else {
                return;  // sin contacto, no podemos identificar el lead
            }
            $comercialLead = $query->latest('id')->first();
            if (!$comercialLead) {
                return;  // no existe todavía, lo crea createComercialLead
            }

            $descripcion = $this->buildLeadDescripcion($chatbotLead, $entities);

            $updates = [];
            if ($descripcion && $descripcion !== $comercialLead->descripcion) {
                $updates['descripcion'] = substr($descripcion, 0, 500);
            }
            if (!empty($chatbotLead->name) && empty($comercialLead->contacto)) {
                $updates['contacto'] = $chatbotLead->name;
            }
            if (!empty($chatbotLead->phone) && empty($comercialLead->telefono)) {
                $updates['telefono'] = $chatbotLead->phone;
            }
            if (!empty($chatbotLead->email) && empty($comercialLead->email)) {
                $updates['email'] = $chatbotLead->email;
            }
            if (!empty($chatbotLead->company_name) && empty($comercialLead->razon_social)) {
                $updates['razon_social'] = $chatbotLead->company_name;
            }

            if (!empty($updates)) {
                $comercialLead->update($updates);
                Log::info('LeadRegistrationService: lead comercial actualizado', [
                    'comercial_lead_id' => $comercialLead->id,
                    'chatbot_lead_id'   => $chatbotLead->id,
                    'fields_updated'    => array_keys($updates),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LeadRegistrationService: error actualizando lead comercial', [
                'chatbot_lead_id' => $chatbotLead->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construir la descripción del lead a partir de TODAS las fuentes de info
     * que el LLM extrajo durante la conversación. Combinar (no descartar) es
     * la clave para no perder contexto que la ejecutiva necesita.
     *
     * Fuentes (en orden):
     *   1. items_solicitados — productos pedidos (ej: "200 casacas con logo")
     *   2. acciones_solicitadas — tareas que el cliente pidió que ejecutemos
     *      (ej: "Enviar catálogo al correo", "Agendar llamada")
     *   3. interes_resumen — resumen corto del interés (ej: "textil corporativo")
     *   4. conversation_summary — resumen narrativo del LLM si no quedó en lo
     *      anterior
     *
     * @internal Público para tests y posible uso desde otros services. No llamar
     *           desde controllers / código de presentación.
     *
     * @param  MarketingChatbotLead  $chatbotLead
     * @param  array                 $entities
     * @return string|null
     */
    public function buildLeadDescripcion(MarketingChatbotLead $chatbotLead, array $entities)
    {
        $parts = [];

        // 1) Items solicitados (productos)
        $items = isset($entities['items_solicitados']) ? $entities['items_solicitados'] : [];
        $itemTexts = [];
        foreach ($items as $item) {
            $text = isset($item['item_texto_original']) ? $item['item_texto_original'] : '';
            $qty  = isset($item['item_cantidad']) ? $item['item_cantidad'] : '';
            if ($qty) {
                $text = $qty . ' ' . $text;
            }
            if (!empty($text)) {
                $itemTexts[] = $text;
            }
        }
        if (!empty($itemTexts)) {
            $parts[] = implode(', ', $itemTexts);
        }

        // 2) Acciones específicas solicitadas por el cliente (campo nuevo)
        $acciones = isset($entities['acciones_solicitadas']) ? $entities['acciones_solicitadas'] : [];
        if (is_array($acciones) && !empty($acciones)) {
            $accionesText = 'Solicita: ' . implode('; ', array_filter(array_map('strval', $acciones)));
            $parts[] = $accionesText;
        }

        // 3) Interés-resumen del LLM (si no es redundante con los items)
        $interes = isset($entities['interes_resumen']) ? trim((string) $entities['interes_resumen']) : '';
        if ($interes !== '' && !$this->textAlreadyCovered($interes, $parts)) {
            $parts[] = $interes;
        }

        // 4) Conversation summary como fallback solo si NO hay nada acumulado
        //    (evita texto duplicado cuando el LLM resume lo mismo de los items).
        if (empty($parts) && !empty($chatbotLead->conversation_summary)) {
            $parts[] = $chatbotLead->conversation_summary;
        }

        return !empty($parts) ? implode('. ', $parts) : null;
    }

    /**
     * Heurística para evitar duplicar texto en la descripción: si las primeras
     * 2 palabras significativas (>4 chars, sin stopwords) del candidato ya
     * están en las partes previas combinadas, lo consideramos cubierto.
     *
     * Ej:
     *   parts = ["100 casacas corporativas"]
     *   needle = "casacas corporativas para uniforme"
     *   palabras significativas del needle: ["casacas", "corporativas"]
     *   ambas están en parts → cubierto → no agregar
     */
    private function textAlreadyCovered($needle, array $haystack)
    {
        if (empty($haystack)) return false;
        $combined = mb_strtolower(implode(' ', $haystack));
        $needleLower = mb_strtolower(trim((string) $needle));
        if ($needleLower === '') return false;

        // Extraer las primeras 2 palabras significativas (>4 chars) del needle
        $words = preg_split('/\s+/', $needleLower);
        $significant = [];
        $stopwords = ['para', 'pero', 'sobre', 'desde', 'hacia', 'entre', 'según', 'segun'];
        foreach ($words as $w) {
            $w = trim($w, ".,;:!?¿¡()[]\"'");
            if (mb_strlen($w) > 4 && !in_array($w, $stopwords, true)) {
                $significant[] = $w;
                if (count($significant) >= 2) break;
            }
        }
        if (empty($significant)) return false;

        // Si TODAS las palabras significativas están en parts → cubierto
        foreach ($significant as $w) {
            if (mb_strpos($combined, $w) === false) {
                return false;
            }
        }
        return true;
    }
}

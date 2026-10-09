<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Mail\ProposalTestMail;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Services\ProposalPdfGenerator;
use Modules\Marketing\Services\QuotationAIEngine;

/**
 * QuotationProposalController — Salida Comercial Derivada (COM-02 §26)
 *
 * Gestiona el ciclo de vida de la `QuotationProposal`:
 *   - Creación automática con IA pre-llenando todo desde la prequote
 *   - Edición campo por campo (o regenerar un campo específico con IA)
 *   - Sincronización de productos desde la prequote
 *   - Generación del PDF con Dompdf
 *   - Emisión al cliente (congela el snapshot)
 *   - Cambio de estado hasta aprobación del cliente
 */
class QuotationProposalController extends Controller
{
    /** @var QuotationAIEngine */
    private $aiEngine;

    /** @var ProposalPdfGenerator */
    private $pdfGenerator;

    public function __construct(QuotationAIEngine $aiEngine, ProposalPdfGenerator $pdfGenerator)
    {
        $this->aiEngine     = $aiEngine;
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * GET /marketing/quotation/prequotes/{id}/proposal
     *
     * Obtener la proposal de una prequote. Si no existe, la crea con IA
     * pre-llenando todos los campos. Si existe, la retorna tal cual.
     */
    public function show($prequoteId)
    {
        try {
            $prequote = PreQuote::with('items', 'empresa', 'contacto')->findOrFail($prequoteId);

            // ¿Ya existe una proposal?
            $proposal = QuotationProposal::where('prequote_id', $prequoteId)->first();

            if (!$proposal) {
                // Crear con defaults + IA
                $proposal = $this->createProposalWithAI($prequote);
            }

            return response()->json([
                'ok'            => true,
                'proposal'      => $proposal->load('bankAccount.bank', 'ejecutiva'),
                // Embebemos bank_accounts y users para que el frontend los use
                // en los selects sin necesidad de endpoints adicionales
                'bank_accounts' => $this->loadBankAccounts(),
                'users'         => $this->loadUsers(),
            ]);

        } catch (\Exception $e) {
            Log::error('QuotationProposalController::show error', [
                'prequote_id' => $prequoteId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error obteniendo la proposal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cargar cuentas bancarias activas del tenant (para el select del footer).
     * @return array
     */
    private function loadBankAccounts()
    {
        try {
            return DB::connection('tenant')
                ->table('bank_accounts')
                ->where('status', 1)
                ->orderBy('id')
                ->select('id', 'description', 'number', 'cci', 'currency_type_id', 'bank_id')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Cargar usuarios activos (para el select de ejecutiva firmante).
     * @return array
     */
    private function loadUsers()
    {
        try {
            return DB::connection('tenant')
                ->table('users')
                ->where('active', 1)
                ->orderBy('name')
                ->select('id', 'name', 'paternal_lastname', 'maternal_lastname', 'email',
                         'cotizacion_firma_cargo', 'cotizacion_firma_celular')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * PUT /marketing/quotation/proposals/{id}
     *
     * Actualizar uno o más campos editables de la proposal. Bloquea cambios
     * si la proposal ya está emitida.
     */
    public function update(Request $request, $id)
    {
        $proposal = QuotationProposal::findOrFail($id);

        if (!$proposal->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La proposal ya fue emitida y no se puede editar. Crea una versión nueva si necesitas cambios.',
            ], 422);
        }

        $v = Validator::make($request->all(), [
            'prefijo_numeracion'      => ['nullable', 'string', 'max:10'],
            'fecha_emision'           => ['nullable', 'date'],
            'secuencial_dia'          => ['nullable', 'integer', 'min:1'],
            'version_externa'         => ['nullable', 'integer', 'min:1'],
            'destinatario_nombre'     => ['nullable', 'string', 'max:255'],
            'destinatario_cargo'      => ['nullable', 'string', 'max:255'],
            'destinatario_sede'       => ['nullable', 'string', 'max:255'],
            'destinatario_empresa'    => ['nullable', 'string', 'max:255'],
            'titulo_descriptivo'      => ['nullable', 'string'],
            'notas_encabezado'        => ['nullable', 'array'],
            'precios_incluyen_igv'    => ['nullable', 'boolean'],
            'aplica_igv'              => ['nullable', 'boolean'],
            'moneda'                  => ['nullable', 'string', 'max:3'],
            'condiciones'             => ['nullable', 'array'],
            'incluye_datos_empresa'   => ['nullable', 'boolean'],
            'incluye_datos_bancarios' => ['nullable', 'boolean'],
            'incluye_sostenibilidad'  => ['nullable', 'boolean'],
            // Agosto 2026 (punto 13): mostrar u ocultar el bloque de totales.
            'incluye_totales'         => ['nullable', 'boolean'],
            'bank_account_id'         => ['nullable', 'integer'],
            'ejecutiva_id'            => ['nullable', 'integer'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $updates = $request->only([
            'prefijo_numeracion', 'fecha_emision', 'secuencial_dia', 'version_externa',
            'destinatario_nombre', 'destinatario_cargo', 'destinatario_sede', 'destinatario_empresa',
            'titulo_descriptivo', 'precios_incluyen_igv', 'aplica_igv', 'moneda',
            'incluye_datos_empresa', 'incluye_datos_bancarios', 'incluye_sostenibilidad',
            // Agosto 2026 (punto 13).
            'incluye_totales',
            'bank_account_id', 'ejecutiva_id',
        ]);

        // Los campos JSON hay que codificarlos
        if ($request->has('notas_encabezado')) {
            $updates['notas_encabezado'] = json_encode(array_values((array) $request->input('notas_encabezado')));
        }
        if ($request->has('condiciones')) {
            $updates['condiciones'] = json_encode((array) $request->input('condiciones'));
        }

        // Si cambiaron componentes del número de cotización, recalcularlo
        $recalcNumero = false;
        foreach (['prefijo_numeracion', 'fecha_emision', 'secuencial_dia', 'version_externa'] as $k) {
            if ($request->has($k)) {
                $recalcNumero = true;
                break;
            }
        }

        $proposal->fill($updates);
        if ($recalcNumero) {
            $proposal->numero_cotizacion = $proposal->buildNumeroCotizacion();

            // El correlativo se puede editar a mano. Si el número resultante ya
            // lo tiene otra cotización, se avisa con un mensaje claro en vez de
            // dejar que la base lo rechace y mostrar el error SQL crudo.
            if ($this->numeroOcupadoPorOtra($proposal)) {
                return $this->respuestaNumeroOcupado($proposal);
            }
        }

        try {
            $proposal->save();
        } catch (QueryException $e) {
            // Otra ejecutiva tomó el mismo número entre la comprobación de arriba
            // y este guardado.
            if ($this->esNumeroDuplicado($e)) {
                return $this->respuestaNumeroOcupado($proposal);
            }
            throw $e;
        }

        return response()->json([
            'ok'       => true,
            'proposal' => $proposal->fresh()->load('bankAccount.bank', 'ejecutiva'),
        ]);
    }

    /**
     * POST /marketing/quotation/proposals/{id}/regenerate-with-ai
     *
     * Re-ejecutar el motor de IA para volver a pre-llenar la proposal completa
     * (o un solo campo específico si viene `field` en el body).
     */
    public function regenerateWithAI(Request $request, $id)
    {
        $proposal = QuotationProposal::with('prequote.items', 'prequote.empresa', 'prequote.contacto')->findOrFail($id);

        if (!$proposal->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La proposal ya fue emitida. No se puede regenerar con IA.',
            ], 422);
        }

        if (!$proposal->prequote) {
            return response()->json([
                'ok'      => false,
                'message' => 'La proposal no tiene precotización asociada.',
            ], 422);
        }

        try {
            $extraContext = [];
            $field = $request->input('field'); // opcional

            $aiContent = $this->aiEngine->generateProposalContent($proposal->prequote, $extraContext);

            // Si viene un campo específico, solo actualizar ese
            if ($field) {
                $proposal->fill($this->mapSingleField($field, $aiContent));
            } else {
                // Regenerar todos los campos editoriales
                $proposal->fill($this->mapAIContentToProposalFields($aiContent));
            }

            $proposal->ai_provider = config('marketing.chatbot.provider', 'claude');
            $proposal->ai_model    = config('marketing.chatbot.claude_model', '');
            $proposal->save();

            return response()->json([
                'ok'          => true,
                'proposal'    => $proposal->fresh()->load('bankAccount.bank', 'ejecutiva'),
                'ai_summary'  => isset($aiContent['interpretation_summary']) ? $aiContent['interpretation_summary'] : '',
                'ai_fallback' => isset($aiContent['_fallback']) && $aiContent['_fallback'],
            ]);

        } catch (\Exception $e) {
            Log::error('QuotationProposalController::regenerateWithAI error', [
                'proposal_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error regenerando con IA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /marketing/quotation/proposals/{id}/sync-items
     *
     * Recargar los items de la proposal desde la prequote (útil si la
     * prequote cambió después de crear la proposal). Solo efecto visible
     * cuando se re-genera el PDF, ya que la proposal no almacena los items
     * directamente (los lee de la prequote).
     */
    public function syncItems($id)
    {
        $proposal = QuotationProposal::findOrFail($id);

        if (!$proposal->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La proposal ya fue emitida. Los items están congelados.',
            ], 422);
        }

        return response()->json([
            'ok'       => true,
            'proposal' => $proposal->fresh()->load('prequote.items', 'bankAccount.bank', 'ejecutiva'),
            'message'  => 'Items leídos desde la precotización. Regenera el PDF para reflejar cambios.',
        ]);
    }

    /**
     * POST /marketing/quotation/proposals/{id}/pdf
     *
     * Generar el PDF y devolver la URL pública.
     */
    public function generatePdf($id)
    {
        $proposal = QuotationProposal::findOrFail($id);

        $result = $this->pdfGenerator->generate($proposal);

        if (!$result['success']) {
            return response()->json([
                'ok'      => false,
                'message' => $result['message'],
            ], 500);
        }

        return response()->json([
            'ok'       => true,
            'pdf_url'  => $result['pdf_url'],
            'pdf_path' => $result['pdf_path'],
            'proposal' => $proposal->fresh(),
        ]);
    }

    /**
     * POST /marketing/quotation/proposals/{id}/emit
     *
     * Emitir la proposal al cliente: genera PDF final, congela snapshot,
     * marca estado=emitida, timestamp emitida_at.
     */
    public function emit($id)
    {
        $proposal = QuotationProposal::findOrFail($id);

        if ($proposal->isEmitida()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La proposal ya fue emitida anteriormente.',
            ], 422);
        }

        // Generar PDF
        $result = $this->pdfGenerator->generate($proposal);

        if (!$result['success']) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo emitir: error generando PDF: ' . $result['message'],
            ], 500);
        }

        // Congelar snapshot
        $proposal->update([
            'estado'     => QuotationProposal::ESTADO_EMITIDA,
            'emitida_at' => now(),
        ]);

        // También actualizar la prequote a estado "propuesta_emitida"
        if ($proposal->prequote) {
            $proposal->prequote->update(['estado' => PreQuote::ESTADO_PROPUESTA_EMITIDA]);
        }

        return response()->json([
            'ok'       => true,
            'proposal' => $proposal->fresh()->load('bankAccount.bank', 'ejecutiva'),
            'pdf_url'  => $result['pdf_url'],
            'message'  => 'Proposal emitida correctamente.',
        ]);
    }

    /**
     * POST /marketing/quotation/proposals/{id}/send-test-email
     *
     * Enviar la propuesta (mismo PDF que se emitiría) por correo a una casilla
     * interna de Compipro, para validar cómo le llega al cliente ANTES de
     * emitir de verdad. (COM-02 Req #14.)
     *
     * Reglas:
     *   - El correo destino prioriza el `email` que el usuario envía en el body
     *     (desde el modal del frente); si no llega, usa la config
     *     `correo_prueba_interno` (editable desde la UI del cotizador). (COM10)
     *   - NO modifica `estado`, `emitida_at`, ni el estado de la prequote.
     *     Es un envío de validación, repetible las veces que haga falta.
     *   - Responde con { success: bool, message: string } (contrato acordado
     *     con el frente Comercial).
     *
     * El envío reutiliza las credenciales SMTP del Email Triage (guardadas en
     * marketing_chatbot_config), que ya están operativas. Si no hay SMTP
     * configurado, cae al mailer por defecto de Laravel (config/mail.php), que
     * en Pruebas suele estar en modo `log`.
     */
    public function sendTestEmail(Request $request, $id)
    {
        // ── 1. Resolver el correo destino: prioriza el que el usuario envía en
        //       el body; si no llega, usa el configurado en el cotizador. (COM10) ──
        $email = trim((string) $request->input('email', ''));
        if ($email === '') {
            $email = trim((string) QuotationConfig::getValue('correo_prueba_interno', ''));
        }

        if ($email === '') {
            return response()->json([
                'success' => false,
                'message' => 'Indica un correo de prueba, o configura el "Correo interno para pruebas" en Configuración del Cotizador.',
            ], 422);
        }

        $v = Validator::make(['email' => $email], [
            'email' => ['required', 'email'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'El correo de prueba no es una dirección válida: ' . $email,
            ], 422);
        }

        // ── 2. Cargar la proposal ──
        $proposal = QuotationProposal::with(['prequote.empresa', 'prequote.contacto'])->findOrFail($id);

        // ── 3. Generar (o regenerar) el PDF. NO toca el estado. ──
        $result = $this->pdfGenerator->generate($proposal);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el PDF para el correo de prueba: ' . $result['message'],
            ], 500);
        }

        // ── 4. Datos de referencia para el cuerpo del correo ──
        $destinatario = $proposal->destinatario_nombre ?: '';
        $empresa = '';
        if ($proposal->prequote && $proposal->prequote->empresa) {
            $empresa = $proposal->prequote->empresa->razon_social ?? '';
        }

        // ── 5. Enviar usando el SMTP del Email Triage (BD-driven) ──
        try {
            $this->applyEmailTriageMailer();

            // Remitente: alinear con la cuenta SMTP para máxima entregabilidad.
            // (Si no se fija explícito, Laravel usa el "from" global, que en
            // runtime puede quedar en el default hello@example.com → spam/rechazo.)
            $fromAddress = ChatbotConfig::getOrEnv('email_user', 'email.user', null);
            $fromName    = ChatbotConfig::getOrEnv('email_default_from_name', 'email.default_from_name', 'COMPIPRO');

            $mailable = new ProposalTestMail(
                $proposal->numero_cotizacion,
                $result['pdf_path'],
                $destinatario,
                $empresa,
                $fromAddress,
                $fromName
            );

            \Illuminate\Support\Facades\Mail::to($email)->send($mailable);

            Log::info('QuotationProposalController: correo de prueba enviado', [
                'proposal_id'       => $proposal->id,
                'numero_cotizacion' => $proposal->numero_cotizacion,
                'destino'           => $email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado a ' . $email,
            ]);

        } catch (\Exception $e) {
            Log::error('QuotationProposalController: error enviando correo de prueba', [
                'proposal_id' => $proposal->id,
                'destino'     => $email,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error enviando el correo de prueba: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configurar el mailer de Laravel en runtime con las credenciales SMTP del
     * Email Triage (marketing_chatbot_config), que ya están operativas.
     *
     * Si no hay credenciales en BD, no hacemos nada: queda el mailer por
     * defecto de config/mail.php (que en Pruebas suele ser `log`).
     *
     * @return void
     */
    private function applyEmailTriageMailer()
    {
        $host       = ChatbotConfig::getOrEnv('email_smtp_host',       'email.smtp_host',       null);
        $port       = (int) ChatbotConfig::getOrEnv('email_smtp_port', 'email.smtp_port',       465);
        $encryption = ChatbotConfig::getOrEnv('email_smtp_encryption', 'email.smtp_encryption', 'ssl');
        $user       = ChatbotConfig::getOrEnv('email_user',            'email.user',            null);
        $pass       = ChatbotConfig::getOrEnv('email_password',        'email.password',        null);
        $fromName   = ChatbotConfig::getOrEnv('email_default_from_name','email.default_from_name', 'Equipo COMPIPRO');
        $verifySsl  = (bool) ChatbotConfig::getOrEnv('email_smtp_verify_ssl', 'email.smtp_verify_ssl', true);

        // Sin credenciales en BD → mantener el mailer por defecto (log/smtp del .env).
        if (empty($host) || empty($user) || empty($pass)) {
            // Igual fijamos un "from" coherente si el default está vacío.
            if (empty(config('mail.from.address'))) {
                config(['mail.from.address' => $user ?: 'no-reply@compipro.net']);
                config(['mail.from.name'    => $fromName]);
            }
            return;
        }

        // Construir el transport SwiftMailer con las credenciales de BD.
        $enc = strtolower($encryption);
        $swiftEncryption = ($enc === 'ssl') ? 'ssl' : (($enc === 'tls') ? 'tls' : null);

        $transport = new \Swift_SmtpTransport($host, $port, $swiftEncryption);
        $transport->setUsername($user);
        $transport->setPassword($pass);

        // Tolerar certificados auto-firmados si el flag verify_ssl está en false.
        if (!$verifySsl) {
            $transport->setStreamOptions([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ]);
        }

        $swiftMailer = new \Swift_Mailer($transport);
        \Illuminate\Support\Facades\Mail::setSwiftMailer($swiftMailer);

        // From coherente con la cuenta SMTP.
        config(['mail.from.address' => $user]);
        config(['mail.from.name'    => $fromName]);
    }

    /**
     * PUT /marketing/quotation/proposals/{id}/status
     *
     * Cambiar el estado de la proposal (emitida → enviada_cliente →
     * aprobada_cliente / rechazada_cliente).
     */
    public function updateStatus(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'estado' => ['required', 'string', 'in:' . implode(',', QuotationProposal::ESTADOS)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $proposal = QuotationProposal::findOrFail($id);
        $newEstado = $request->input('estado');

        // Reglas de transición
        $allowedTransitions = [
            QuotationProposal::ESTADO_BORRADOR          => [QuotationProposal::ESTADO_EMITIDA],
            QuotationProposal::ESTADO_EMITIDA           => [QuotationProposal::ESTADO_ENVIADA_CLIENTE, QuotationProposal::ESTADO_APROBADA_CLIENTE, QuotationProposal::ESTADO_RECHAZADA_CLIENTE],
            QuotationProposal::ESTADO_ENVIADA_CLIENTE   => [QuotationProposal::ESTADO_APROBADA_CLIENTE, QuotationProposal::ESTADO_RECHAZADA_CLIENTE],
            QuotationProposal::ESTADO_APROBADA_CLIENTE  => [],
            QuotationProposal::ESTADO_RECHAZADA_CLIENTE => [QuotationProposal::ESTADO_BORRADOR], // se puede reabrir
        ];

        $current = $proposal->estado;
        $allowed = isset($allowedTransitions[$current]) ? $allowedTransitions[$current] : [];

        if (!in_array($newEstado, $allowed)) {
            return response()->json([
                'ok'      => false,
                'message' => "Transición no permitida: {$current} → {$newEstado}.",
            ], 422);
        }

        $proposal->update(['estado' => $newEstado]);

        // Si el cliente aprobó, actualizar la prequote para disparar conversión a Quotation formal
        if ($newEstado === QuotationProposal::ESTADO_APROBADA_CLIENTE && $proposal->prequote) {
            $proposal->prequote->update(['estado' => PreQuote::ESTADO_APROBADA]);
        }

        return response()->json([
            'ok'       => true,
            'proposal' => $proposal->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers privados
    |--------------------------------------------------------------------------
    */

    /**
     * Crear una proposal nueva para una prequote, pre-llenando con IA.
     *
     * @param  PreQuote  $prequote
     * @return QuotationProposal
     */
    private function createProposalWithAI(PreQuote $prequote)
    {
        // Defaults mínimos
        $prefix = $this->getConfigValue('cotizacion_prefijo_default', 'MAP');
        $fechaEmision = date('Y-m-d');

        // El correlativo y el número NO se calculan acá: se asignan al final, justo
        // antes de guardar (ver guardarConNumeroLibre). Entre este punto y el
        // guardado está la llamada a la IA, que tarda varios segundos.
        $proposal = new QuotationProposal();
        $proposal->prequote_id        = $prequote->id;
        $proposal->prefijo_numeracion = $prefix;
        $proposal->fecha_emision      = $fechaEmision;
        $proposal->version_externa    = 1;
        $proposal->moneda             = 'PEN';
        $proposal->estado             = QuotationProposal::ESTADO_BORRADOR;
        $proposal->ejecutiva_id       = $prequote->user_id; // default: mismo user que creó la prequote

        // Seleccionar cuenta bancaria default (la única por ahora: BCP Soles)
        $defaultBank = DB::connection('tenant')
            ->table('bank_accounts')
            ->where('status', 1)
            ->orderBy('id')
            ->first();
        if ($defaultBank) {
            $proposal->bank_account_id = $defaultBank->id;
        }

        // Intentar pre-llenar con IA
        try {
            $aiContent = $this->aiEngine->generateProposalContent($prequote);
            $fields = $this->mapAIContentToProposalFields($aiContent);
            foreach ($fields as $k => $v) {
                $proposal->{$k} = $v;
            }
            $proposal->ai_provider = config('marketing.chatbot.provider', 'claude');
            $proposal->ai_model    = config('marketing.chatbot.claude_model', '');
        } catch (\Exception $e) {
            Log::warning('QuotationProposalController::createProposalWithAI — IA falló, se crea con defaults', [
                'prequote_id' => $prequote->id,
                'error'       => $e->getMessage(),
            ]);
            // Aún así, completar con defaults para que la proposal sea usable
            $fallback = $this->aiEngine->generateProposalContent($prequote); // ya tiene fallback interno
            $fields = $this->mapAIContentToProposalFields($fallback);
            foreach ($fields as $k => $v) {
                $proposal->{$k} = $v;
            }
        }

        $this->guardarConNumeroLibre($proposal);

        return $proposal;
    }

    /**
     * Guarda una proposal nueva con el primer número de cotización libre del día.
     *
     * El número se calcula recién acá, inmediatamente antes del INSERT. Antes se
     * calculaba al entrar a createProposalWithAI, y entre ese momento y el guardado
     * corría la llamada a la IA —varios segundos, y si fallaba se repetía—: dos
     * ejecutivas generando en esa ventana obtenían el mismo número.
     *
     * Aun calculándolo al final, dos altas pueden coincidir en el mismo instante.
     * Para ese caso, si la base rechaza el número por duplicado, se recalcula y se
     * vuelve a intentar.
     *
     * @param  QuotationProposal $proposal  con prefijo, fecha y versión ya asignados
     * @return void
     * @throws QueryException  si el error no es de número duplicado, o si persiste
     */
    private function guardarConNumeroLibre(QuotationProposal $proposal)
    {
        $intentos = 3;

        for ($i = 1; $i <= $intentos; $i++) {
            $proposal->secuencial_dia    = $this->siguienteSecuencialDelDia($proposal);
            $proposal->numero_cotizacion = $proposal->buildNumeroCotizacion();

            try {
                $proposal->save();
                return;
            } catch (QueryException $e) {
                // Solo se reintenta si lo repetido es el número. Si es otra cosa —por
                // ejemplo, la pre-cotización en un doble clic—, recalcular el número
                // no lo arregla.
                if (!$this->esNumeroDuplicado($e) || $i === $intentos) {
                    throw $e;
                }

                Log::warning('QuotationProposalController::guardarConNumeroLibre — número tomado al guardar, se recalcula', [
                    'prequote_id'       => $proposal->prequote_id,
                    'numero_cotizacion' => $proposal->numero_cotizacion,
                    'intento'           => $i,
                ]);
            }
        }
    }

    /**
     * Próximo correlativo libre del día para el número de cotización.
     *
     * Antes se calculaba CONTANDO las cotizaciones del día y sumando 1. Eso falla
     * en cuanto el conteo queda por debajo del correlativo más alto ya usado, lo
     * que puede pasar por dos vías:
     *
     *   · El correlativo se puede editar a mano en la pestaña Cotización Final.
     *     Si hoy existen el 01, el 02 y el 03 y alguien cambia el 03 por 04, el
     *     conteo sigue dando 3 y la siguiente cotización vuelve a salir 04.
     *   · El modelo usa borrado lógico. Al contar, Laravel deja afuera las
     *     cotizaciones borradas, pero el índice único de `numero_cotizacion` las
     *     sigue incluyendo, así que su número queda ocupado.
     *
     * En ambos casos el número calculado ya existía, la base rechazaba el alta, y
     * como todas las ejecutivas calculaban el mismo número, la generación de la
     * Cotización Final quedaba bloqueada para todos hasta el día siguiente.
     *
     * Ahora se parte del correlativo MÁS ALTO del día, incluyendo las borradas, y
     * además se comprueba que el número armado esté libre; si no, se avanza. Esa
     * segunda comprobación cubre números que el máximo no ve, como uno cargado
     * por fuera del sistema.
     *
     * Se sigue numerando por día para todos los prefijos juntos, igual que antes:
     * cambiar eso alteraría la numeración que Compipro ya usa.
     *
     * @param  QuotationProposal $proposal  con prefijo, fecha y versión ya asignados
     * @return int
     */
    private function siguienteSecuencialDelDia(QuotationProposal $proposal)
    {
        $maximo = QuotationProposal::withTrashed()
            ->whereDate('fecha_emision', $proposal->fecha_emision)
            ->max('secuencial_dia');

        $secuencial = ((int) $maximo) + 1;

        // Tope de seguridad para no quedar en un bucle si algo raro pasa con los
        // datos; en la práctica se sale en la primera vuelta.
        $tope = $secuencial + 100;

        for (; $secuencial < $tope; $secuencial++) {
            $proposal->secuencial_dia = $secuencial;

            $ocupado = QuotationProposal::withTrashed()
                ->where('numero_cotizacion', $proposal->buildNumeroCotizacion())
                ->exists();

            if (!$ocupado) {
                return $secuencial;
            }
        }

        return $secuencial;
    }

    /**
     * ¿El error de la base es un número de cotización repetido?
     *
     * 1062 es "entrada duplicada en un índice único" en MySQL/MariaDB, pero esta
     * tabla tiene DOS índices únicos: el número de cotización y la pre-cotización
     * (una sola Cotización Final por pre-cotización). Hay que distinguirlos.
     *
     * Se mira errorInfo[2] —el mensaje de la base, que nombra el índice— y NO
     * getMessage(): ese incluye la sentencia SQL completa, cuya lista de columnas
     * contiene siempre `numero_cotizacion`, así que cualquier duplicado parecería
     * del número. Se comprobó: con getMessage() un duplicado de pre-cotización
     * disparaba tres reintentos inútiles.
     *
     * @param  QueryException $e
     * @return bool
     */
    private function esNumeroDuplicado(QueryException $e)
    {
        return isset($e->errorInfo[1], $e->errorInfo[2])
            && (int) $e->errorInfo[1] === 1062
            && strpos($e->errorInfo[2], 'numero_cotizacion_unique') !== false;
    }

    /**
     * ¿Otra cotización —incluidas las borradas— ya tiene este número?
     *
     * Se excluye la propia, porque la pantalla reenvía el correlativo en cada
     * guardado aunque no haya cambiado.
     *
     * @param  QuotationProposal $proposal  con numero_cotizacion ya recalculado
     * @return bool
     */
    private function numeroOcupadoPorOtra(QuotationProposal $proposal)
    {
        return QuotationProposal::withTrashed()
            ->where('numero_cotizacion', $proposal->numero_cotizacion)
            ->where('id', '!=', $proposal->id)
            ->exists();
    }

    /**
     * Respuesta 422 para un correlativo editado a mano que choca con otra cotización.
     *
     * Además de avisar, sugiere el siguiente correlativo libre de esa fecha, para
     * que la ejecutiva no tenga que probar números a ciegas.
     *
     * La pantalla muestra el campo `message` de la respuesta tal cual.
     *
     * @param  QuotationProposal $proposal  con los valores que se intentaron guardar
     * @return \Illuminate\Http\JsonResponse
     */
    private function respuestaNumeroOcupado(QuotationProposal $proposal)
    {
        $numero = $proposal->numero_cotizacion;

        // Sobre una copia: el cálculo va probando correlativos en el modelo.
        $sugerido = $this->siguienteSecuencialDelDia(clone $proposal);

        return response()->json([
            'ok'      => false,
            'message' => 'El número ' . $numero . ' ya lo usa otra cotización. '
                       . 'El siguiente correlativo libre para esa fecha es el '
                       . str_pad($sugerido, 2, '0', STR_PAD_LEFT) . '.',
        ], 422);
    }

    /**
     * Mapear la respuesta del AIEngine a los campos fillable de QuotationProposal.
     *
     * @param  array  $ai
     * @return array
     */
    private function mapAIContentToProposalFields(array $ai)
    {
        $dest = isset($ai['destinatario']) ? $ai['destinatario'] : [];

        return [
            'destinatario_nombre'     => isset($dest['nombre'])  ? $dest['nombre']  : null,
            'destinatario_cargo'      => isset($dest['cargo'])   ? $dest['cargo']   : null,
            'destinatario_sede'       => isset($dest['sede'])    ? $dest['sede']    : null,
            'destinatario_empresa'    => isset($dest['empresa']) ? $dest['empresa'] : null,
            'titulo_descriptivo'      => isset($ai['titulo_descriptivo']) ? $ai['titulo_descriptivo'] : '',
            'notas_encabezado'        => json_encode(isset($ai['notas_encabezado']) ? $ai['notas_encabezado'] : []),
            'precios_incluyen_igv'    => isset($ai['precios_incluyen_igv']) ? (bool) $ai['precios_incluyen_igv'] : false,
            'moneda'                  => isset($ai['moneda']) ? $ai['moneda'] : 'PEN',
            'condiciones'             => json_encode(isset($ai['condiciones']) ? $ai['condiciones'] : []),
            'incluye_datos_empresa'   => isset($ai['incluye_datos_empresa']) ? (bool) $ai['incluye_datos_empresa'] : true,
            'incluye_datos_bancarios' => isset($ai['incluye_datos_bancarios']) ? (bool) $ai['incluye_datos_bancarios'] : true,
        ];
    }

    /**
     * Obtener solo un campo específico del AI content mapeado.
     *
     * @param  string  $field
     * @param  array   $ai
     * @return array
     */
    private function mapSingleField($field, array $ai)
    {
        $full = $this->mapAIContentToProposalFields($ai);
        return isset($full[$field]) ? [$field => $full[$field]] : [];
    }

    /**
     * Leer un valor de `quotation_config`.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    private function getConfigValue($key, $default = null)
    {
        try {
            $row = DB::connection('tenant')
                ->table('quotation_config')
                ->where('config_key', $key)
                ->first();
            return $row ? $row->config_value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

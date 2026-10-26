<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Support\QuotationCondiciones;

/**
 * QuotationAIEngine — Motor de IA del cotizador COM-02
 *
 * Interpreta texto libre de requerimientos y retorna ítems estructurados.
 * Reutiliza la capa de IA (ClaudeProvider/OpenAIProvider) de MKT-01.
 *
 * Provider y modelo se resuelven en runtime desde marketing_agent_config (agent_key=com02),
 * con fallback transparente al .env si la tabla aún no existe.
 */
class QuotationAIEngine
{
    /** @var AgentConfigService */
    private $configService;

    /** @var string */
    private $agentKey = AgentConfig::AGENT_COM02;

    /** @var string */
    private $systemPromptTemplate;

    /** @var AIProviderInterface|null Cache por instancia */
    private $provider = null;

    public function __construct(AgentConfigService $configService)
    {
        $this->configService = $configService;
        $this->systemPromptTemplate = $this->loadSystemPrompt();
    }

    /**
     * Resolver el provider en runtime (lazy + cached).
     *
     * @return AIProviderInterface
     */
    private function getProvider()
    {
        if ($this->provider === null) {
            $this->provider = $this->configService->resolveTextProvider($this->agentKey);
        }
        return $this->provider;
    }

    public function reloadProvider()
    {
        $this->provider = null;
        $this->configService->clearCache();
    }

    /**
     * Interpretar un texto de requerimiento y extraer ítems cotizables.
     *
     * @param  string  $text       Texto del requerimiento
     * @param  array   $context    Contexto adicional (categoria_productos, empresa, etc.)
     * @return array               JSON decodificado con items detectados
     */
    public function interpretRequirement($text, array $context = [])
    {
        $systemPrompt = $this->buildSystemPrompt($context);

        $messages = [
            [
                'role'    => 'user',
                'content' => $text,
            ],
        ];

        // S2 — multimodal: si el contexto incluye una imagen, la pasamos al
        // provider en formato base64. El AIProviderInterface define que cada
        // provider la inyecta donde corresponda en su payload.
        $providerOptions = [
            'temperature' => 0.2, // Más determinista para interpretación estructurada
            'max_tokens'  => 2048,
        ];
        $images = $this->loadImagesForContext($context);
        if (!empty($images)) {
            $providerOptions['images'] = $images;

            // También enriquecemos el texto del user message con una nota
            // explícita para que el LLM sepa que la imagen es del producto/s
            // del requerimiento.
            $messages[0]['content'] = $text . "\n\n[Se adjunta imagen del producto solicitado para que la analices junto con el texto.]";
        }

        try {
            $rawResponse = $this->getProvider()->sendMessage($systemPrompt, $messages, $providerOptions);
        } catch (AIProviderException $e) {
            Log::error('QuotationAIEngine: error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);

            return $this->fallbackResponse('Error de conexión con el motor de IA.');
        }

        // Parsear JSON
        $parsed = $this->parseResponse($rawResponse);

        if ($parsed === null) {
            // Reintento
            Log::warning('QuotationAIEngine: respuesta no es JSON válido, reintentando');
            $parsed = $this->retryWithCorrection($systemPrompt, $messages, $rawResponse);
        }

        if ($parsed === null) {
            Log::error('QuotationAIEngine: segundo intento falló');
            return $this->fallbackResponse('No se pudo interpretar el requerimiento.');
        }

        // Normalizar
        $parsed = $this->normalizeResponse($parsed);

        Log::info('QuotationAIEngine: requerimiento interpretado', [
            'total_items' => $parsed['total_items_detected'],
            'provider'    => $this->getProvider()->getProviderName(),
        ]);

        return $parsed;
    }

    /**
     * Cargar el system prompt desde archivo.
     *
     * @return string
     */
    private function loadSystemPrompt()
    {
        $path = __DIR__ . '/../Prompts/quotation-system-prompt.md';

        if (!file_exists($path)) {
            Log::error('QuotationAIEngine: quotation-system-prompt.md no encontrado');
            return 'Eres un motor de interpretación de requerimientos. Responde en JSON válido.';
        }

        return file_get_contents($path);
    }

    /**
     * Cargar el prompt de generación de proposal desde archivo.
     *
     * @return string
     */
    private function loadProposalPrompt()
    {
        $path = __DIR__ . '/../Prompts/quotation-proposal-prompt.md';

        if (!file_exists($path)) {
            Log::error('QuotationAIEngine: quotation-proposal-prompt.md no encontrado');
            return 'Eres el generador de salida comercial. Responde en JSON válido con los campos destinatario, titulo_descriptivo, notas_encabezado, precios_incluyen_igv, moneda, condiciones, incluye_datos_empresa, incluye_datos_bancarios.';
        }

        return file_get_contents($path);
    }

    /**
     * Cargar el prompt de sugerencia de proveedores (E4).
     *
     * @return string
     */
    private function loadSupplierSuggestionPrompt()
    {
        $path = __DIR__ . '/../Prompts/quotation-supplier-suggestion-prompt.md';

        if (!file_exists($path)) {
            Log::error('QuotationAIEngine: quotation-supplier-suggestion-prompt.md no encontrado');
            return 'Eres el asesor de proveedores. Retorna JSON con { summary, best_match_rationale, suggestions: [ { supplier_id, supplier_name, supplier_category, costo_unitario_estimado, tiempo_estimado_dias, confianza, justificacion, alertas } ] }.';
        }

        return file_get_contents($path);
    }

    /**
     * Sugerir top-3 proveedores para un ítem de precotización.
     *
     * Analiza el ítem (producto, cantidad, personalización) contra el catálogo
     * de proveedores del tenant + histórico de compras, y devuelve las 3
     * mejores sugerencias con costo y tiempo estimado.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return array  ['summary', 'best_match_rationale', 'suggestions' => [...]]
     */
    public function suggestSuppliersForItem($item)
    {
        $systemPrompt = $this->buildSupplierSuggestionPrompt($item);

        $userMsg = [
            'role'    => 'user',
            'content' => 'Analiza el ítem, catálogo de proveedores e histórico. Retorna el JSON con las 3 sugerencias.',
        ];

        try {
            $rawResponse = $this->getProvider()->sendMessage($systemPrompt, [$userMsg], [
                'temperature' => 0.3,
                'max_tokens'  => 2048,
            ]);
        } catch (AIProviderException $e) {
            Log::error('QuotationAIEngine::suggestSuppliersForItem error del proveedor', [
                'item_id' => $item->id,
                'message' => $e->getMessage(),
            ]);
            return $this->fallbackSupplierSuggestions($item, 'Error de conexión con el motor de IA.');
        }

        $parsed = $this->parseSupplierResponse($rawResponse);

        if ($parsed === null) {
            // Reintento con corrección
            Log::warning('QuotationAIEngine::suggestSuppliersForItem: respuesta no es JSON válido, reintentando');
            $messages = [$userMsg];
            $messages[] = ['role' => 'assistant', 'content' => $rawResponse];
            $messages[] = [
                'role'    => 'user',
                'content' => 'Tu respuesta debe ser SOLO el JSON del esquema. Sin markdown, sin texto adicional. Retorna el JSON ahora.',
            ];

            try {
                $rawRetry = $this->getProvider()->sendMessage($systemPrompt, $messages, [
                    'temperature' => 0.1,
                ]);
                $parsed = $this->parseSupplierResponse($rawRetry);
            } catch (\Exception $e) {
                $parsed = null;
            }
        }

        if ($parsed === null) {
            Log::error('QuotationAIEngine::suggestSuppliersForItem: segundo intento falló');
            return $this->fallbackSupplierSuggestions($item, 'No se pudo generar sugerencias de proveedores.');
        }

        $parsed = $this->normalizeSupplierSuggestions($parsed);

        Log::info('QuotationAIEngine::suggestSuppliersForItem: sugerencias generadas', [
            'item_id'    => $item->id,
            'total'      => count($parsed['suggestions']),
            'provider'   => $this->getProvider()->getProviderName(),
        ]);

        return $parsed;
    }

    /**
     * Construir system prompt para supplier suggestion inyectando ítem,
     * catálogo de proveedores e histórico.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    private function buildSupplierSuggestionPrompt($item)
    {
        $prompt = $this->loadSupplierSuggestionPrompt();

        $prompt = str_replace('{{ITEM_CONTEXT}}', $this->formatItemContext($item), $prompt);
        $prompt = str_replace('{{SUPPLIERS_CATALOG}}', $this->formatSuppliersCatalog(), $prompt);
        $prompt = str_replace('{{HISTORIAL_DEL_ITEM}}', $this->formatItemPurchaseHistory($item), $prompt);

        return $prompt;
    }

    /**
     * Formatear el ítem como contexto legible para la IA.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    private function formatItemContext($item)
    {
        $lines = [];
        $lines[] = '## Ítem a cotizar';
        $lines[] = '- **Nombre**: ' . ($item->product_name ?: 'Sin nombre');
        $lines[] = '- **Cantidad**: ' . (int) $item->quantity;
        if (!empty($item->product_description)) {
            $lines[] = '- **Descripción**: ' . $item->product_description;
        }
        if (!empty($item->material)) {
            $lines[] = '- **Material**: ' . $item->material;
        }
        if (!empty($item->medida)) {
            $lines[] = '- **Medida**: ' . $item->medida;
        }
        if (!empty($item->tecnica_impresion)) {
            $lines[] = '- **Técnica de impresión**: ' . $item->tecnica_impresion;
        }
        if ($item->cantidad_colores > 1) {
            $lines[] = '- **Colores**: ' . $item->cantidad_colores;
        }
        if ($item->cantidad_lados > 1) {
            $lines[] = '- **Lados**: ' . $item->cantidad_lados;
        }
        if (!empty($item->empaque)) {
            $lines[] = '- **Empaque**: ' . $item->empaque;
        }
        if (!empty($item->personalizacion)) {
            $lines[] = '- **Personalización**: ' . $item->personalizacion;
        }
        if ((float) $item->costo_base > 0) {
            $lines[] = '- **Costo base actual** (referencia): S/ ' . number_format((float) $item->costo_base, 2);
        }
        if (!empty($item->observaciones)) {
            $lines[] = '- **Observaciones**: ' . $item->observaciones;
        }

        // Proveedor actual si ya existe
        if ($item->supplier_id && !empty($item->supplier_name)) {
            $lines[] = '- **Proveedor actual asignado**: ' . $item->supplier_name;
        }

        return implode("\n", $lines);
    }

    /**
     * Formatear el catálogo de proveedores del tenant para el prompt.
     *
     * @return string
     */
    private function formatSuppliersCatalog()
    {
        try {
            $suppliers = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('persons')
                ->leftJoin('supplier_categories', 'persons.supplier_category_id', '=', 'supplier_categories.id')
                ->where('persons.type', 'suppliers')
                ->where('persons.enabled', 1)
                ->select(
                    'persons.id',
                    'persons.name',
                    'persons.trade_name',
                    'persons.rubro_sector',
                    'persons.tipo_provee',
                    'persons.calificacion',
                    'persons.condicion_pago',
                    'persons.telephone',
                    'persons.email',
                    'supplier_categories.nombre as categoria_nombre'
                )
                ->orderBy('persons.name')
                ->get();
        } catch (\Exception $e) {
            return '(No se pudo cargar el catálogo de proveedores)';
        }

        if ($suppliers->isEmpty()) {
            return '(No hay proveedores activos en el sistema)';
        }

        $lines = [];
        foreach ($suppliers as $s) {
            $bits = [];
            $bits[] = "ID: {$s->id}";
            $bits[] = "Nombre: {$s->name}";
            if (!empty($s->trade_name)) $bits[] = "Comercial: {$s->trade_name}";
            $bits[] = 'Categoría: ' . (!empty($s->categoria_nombre) ? $s->categoria_nombre : 'Sin categoría');
            if (!empty($s->rubro_sector))   $bits[] = "Rubro: {$s->rubro_sector}";
            if (!empty($s->tipo_provee))    $bits[] = "Tipo: {$s->tipo_provee}";
            if (!empty($s->calificacion))   $bits[] = "Calificación: {$s->calificacion}";
            if (!empty($s->condicion_pago)) $bits[] = "Pago: {$s->condicion_pago}";
            $lines[] = '- ' . implode(' | ', $bits);
        }

        return implode("\n", $lines);
    }

    /**
     * Formatear el histórico de compras para este ítem (si lo tiene
     * vinculado a un item del catálogo ERP).
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    private function formatItemPurchaseHistory($item)
    {
        if (!$item->item_id) {
            return '(El ítem no está vinculado a un producto del catálogo ERP; no hay histórico específico)';
        }

        try {
            $history = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('purchase_items')
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->join('persons', 'purchases.supplier_id', '=', 'persons.id')
                ->where('purchase_items.item_id', $item->item_id)
                ->select(
                    'purchases.date_of_issue',
                    'purchases.supplier_id',
                    'persons.name as supplier_name',
                    'purchase_items.quantity',
                    'purchase_items.unit_value',
                    'purchases.currency_type_id'
                )
                ->orderByDesc('purchases.date_of_issue')
                ->limit(5)
                ->get();
        } catch (\Exception $e) {
            return '(No se pudo cargar el histórico de compras)';
        }

        if ($history->isEmpty()) {
            return '(Sin compras previas registradas de este producto específico)';
        }

        $lines = [];
        foreach ($history as $h) {
            $curr = $h->currency_type_id === 'USD' ? '$' : 'S/';
            $lines[] = sprintf(
                '- %s | Proveedor ID %s (%s) | cant. %s | costo unit. %s %s',
                $h->date_of_issue,
                $h->supplier_id,
                $h->supplier_name,
                (int) $h->quantity,
                $curr,
                number_format((float) $h->unit_value, 2)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Parsear respuesta JSON del LLM para supplier suggestion.
     *
     * @param  string  $raw
     * @return array|null
     */
    private function parseSupplierResponse($raw)
    {
        $clean = trim($raw);

        if (substr($clean, 0, 7) === '```json') {
            $clean = preg_replace('/^```json\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        } elseif (substr($clean, 0, 3) === '```') {
            $clean = preg_replace('/^```\s*/', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        }

        $clean = trim($clean);
        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!isset($decoded['suggestions']) || !is_array($decoded['suggestions'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Normalizar las sugerencias: asegura que cada una tiene todos los campos.
     *
     * @param  array  $parsed
     * @return array
     */
    private function normalizeSupplierSuggestions(array $parsed)
    {
        $parsed['summary']              = isset($parsed['summary']) ? $parsed['summary'] : '';
        $parsed['best_match_rationale'] = isset($parsed['best_match_rationale']) ? $parsed['best_match_rationale'] : '';

        $suggestions = isset($parsed['suggestions']) ? $parsed['suggestions'] : [];
        $normalized = [];

        foreach ($suggestions as $s) {
            $normalized[] = [
                'supplier_id'               => isset($s['supplier_id']) ? (int) $s['supplier_id'] : null,
                'supplier_name'             => isset($s['supplier_name']) ? $s['supplier_name'] : 'Desconocido',
                'supplier_category'         => isset($s['supplier_category']) ? $s['supplier_category'] : null,
                'costo_unitario_estimado'   => isset($s['costo_unitario_estimado']) ? (float) $s['costo_unitario_estimado'] : 0,
                // COM08 — tiempo de entrega es texto libre (ej. "15 días útiles", "Mié 02 Abril").
                'tiempo_estimado_dias'      => isset($s['tiempo_estimado_dias']) ? (string) $s['tiempo_estimado_dias'] : '',
                'confianza'                 => isset($s['confianza']) ? max(0, min(100, (int) $s['confianza'])) : 50,
                'justificacion'             => isset($s['justificacion']) ? $s['justificacion'] : '',
                'alertas'                   => isset($s['alertas']) && is_array($s['alertas']) ? $s['alertas'] : [],
            ];
        }

        // Limitar a 3 y ordenar por confianza desc
        usort($normalized, function ($a, $b) {
            return $b['confianza'] - $a['confianza'];
        });
        $parsed['suggestions'] = array_slice($normalized, 0, 3);

        return $parsed;
    }

    /**
     * Fallback cuando la IA falla: toma los 3 primeros proveedores activos
     * como sugerencias básicas.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @param  string  $message
     * @return array
     */
    private function fallbackSupplierSuggestions($item, $message)
    {
        try {
            $suppliers = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('persons')
                ->leftJoin('supplier_categories', 'persons.supplier_category_id', '=', 'supplier_categories.id')
                ->where('persons.type', 'suppliers')
                ->where('persons.enabled', 1)
                ->select('persons.id', 'persons.name', 'supplier_categories.nombre as categoria_nombre')
                ->orderBy('persons.name')
                ->limit(3)
                ->get();
        } catch (\Exception $e) {
            $suppliers = collect();
        }

        $suggestions = [];
        foreach ($suppliers as $s) {
            $suggestions[] = [
                'supplier_id'             => (int) $s->id,
                'supplier_name'           => $s->name,
                'supplier_category'       => !empty($s->categoria_nombre) ? $s->categoria_nombre : 'Sin categoría',
                'costo_unitario_estimado' => (float) $item->costo_base,
                'tiempo_estimado_dias'    => $item->tiempo_estimado_dias ? (string) $item->tiempo_estimado_dias : '',
                'confianza'               => 40,
                'justificacion'           => 'Sugerencia por defecto: primeros proveedores activos del catálogo. La IA no pudo analizar — requiere revisión manual.',
                'alertas'                 => ['Fallback sin análisis IA — revisar manualmente'],
            ];
        }

        return [
            'summary'              => $message,
            'best_match_rationale' => 'Fallback: no se pudo ejecutar análisis IA.',
            'suggestions'          => $suggestions,
            '_fallback'            => true,
        ];
    }

    /**
     * Generar el contenido editorial de una proposal a partir de una PreQuote.
     *
     * Retorna un array estructurado con todos los campos que pre-llenan la
     * `QuotationProposal`: destinatario, título, notas, condiciones, toggles
     * de bloques opcionales, etc.
     *
     * @param  \Modules\Marketing\Models\PreQuote  $prequote
     * @param  array  $extraContext  contexto adicional opcional
     * @return array
     */
    public function generateProposalContent($prequote, array $extraContext = [])
    {
        $systemPrompt = $this->buildProposalSystemPrompt($prequote, $extraContext);

        $userContext = [
            'role'    => 'user',
            'content' => 'Genera el contenido comercial de la proposal siguiendo el esquema JSON. No agregues explicaciones fuera del JSON.',
        ];

        try {
            $rawResponse = $this->getProvider()->sendMessage($systemPrompt, [$userContext], [
                'temperature' => 0.3,
                'max_tokens'  => 2048,
            ]);
        } catch (AIProviderException $e) {
            Log::error('QuotationAIEngine::generateProposalContent error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);
            return $this->fallbackProposalContent($prequote, 'Error de conexión con el motor de IA.');
        }

        $parsed = $this->parseProposalResponse($rawResponse);

        if ($parsed === null) {
            // Reintento con corrección
            Log::warning('QuotationAIEngine::generateProposalContent: respuesta no es JSON válido, reintentando');
            $messages = [$userContext];
            $messages[] = ['role' => 'assistant', 'content' => $rawResponse];
            $messages[] = [
                'role'    => 'user',
                'content' => 'Tu respuesta debe ser SOLO el JSON del esquema. Sin markdown, sin texto adicional. Retorna el JSON.',
            ];

            try {
                $rawRetry = $this->getProvider()->sendMessage($systemPrompt, $messages, [
                    'temperature' => 0.1,
                ]);
                $parsed = $this->parseProposalResponse($rawRetry);
            } catch (\Exception $e) {
                $parsed = null;
            }
        }

        if ($parsed === null) {
            Log::error('QuotationAIEngine::generateProposalContent: segundo intento falló');
            return $this->fallbackProposalContent($prequote, 'No se pudo generar el contenido de la proposal.');
        }

        // Normalizar
        $parsed = $this->normalizeProposalContent($parsed, $prequote);

        Log::info('QuotationAIEngine::generateProposalContent: proposal generada', [
            'prequote_id' => $prequote->id,
            'provider'    => $this->getProvider()->getProviderName(),
        ]);

        return $parsed;
    }

    /**
     * Construir el system prompt del proposal generator inyectando contexto
     * de la prequote y defaults del sistema.
     *
     * @param  \Modules\Marketing\Models\PreQuote  $prequote
     * @param  array  $extraContext
     * @return string
     */
    private function buildProposalSystemPrompt($prequote, array $extraContext = [])
    {
        $prompt = $this->loadProposalPrompt();

        // ── Inyectar contexto de la prequote ──
        $context = $this->formatPrequoteContext($prequote, $extraContext);
        $prompt = str_replace('{{PREQUOTE_CONTEXT}}', $context, $prompt);

        // ── Inyectar defaults de condiciones desde quotation_config ──
        $defaults = $this->formatDefaultsForProposal();
        $prompt = str_replace('{{DEFAULTS_CONDICIONES}}', $defaults, $prompt);

        return $prompt;
    }

    /**
     * Formatear la prequote como contexto legible para la IA.
     *
     * @param  \Modules\Marketing\Models\PreQuote  $prequote
     * @param  array  $extraContext
     * @return string
     */
    private function formatPrequoteContext($prequote, array $extraContext = [])
    {
        $lines = [];

        // Datos básicos
        $lines[] = "## Datos de la precotización";
        $lines[] = "- ID: #{$prequote->id}";
        $lines[] = "- Versión interna: {$prequote->version}";
        $lines[] = "- Estado: {$prequote->estado}";
        $lines[] = "- Título interno: " . ($prequote->titulo ?: 'Sin título');

        // Empresa del cliente
        $lines[] = "\n## Cliente";
        if ($prequote->empresa) {
            $emp = $prequote->empresa;
            $lines[] = "- Razón social: " . (isset($emp->razon_social) ? $emp->razon_social : 'Desconocido');
            $lines[] = "- RUC: " . (isset($emp->ruc) ? $emp->ruc : 'no especificado');
            $lines[] = "- Tipo: " . (isset($emp->tipo_empresa) ? $emp->tipo_empresa : 'no especificado');

            // Es cliente nuevo o recurrente? (criterio: si tiene otras prequotes emitidas antes)
            try {
                $prevCount = \Modules\Marketing\Models\PreQuote::query()
                    ->where('empresa_id', $emp->id)
                    ->where('id', '!=', $prequote->id)
                    ->where('estado', 'aprobada')
                    ->count();
                $clienteCategoria = $prevCount > 0 ? 'recurrente' : 'nuevo';
                $lines[] = "- Categoría: {$clienteCategoria} (aprobadas previas: {$prevCount})";
            } catch (\Exception $e) {
                $lines[] = "- Categoría: desconocida";
            }
        } else {
            $lines[] = "- Sin empresa asociada (cotización libre)";
        }

        // Contacto
        $lines[] = "\n## Contacto";
        if ($prequote->contacto) {
            $c = $prequote->contacto;
            $lines[] = "- Nombre: " . (isset($c->nombre) ? $c->nombre : 'no especificado');
            $lines[] = "- Cargo: " . (isset($c->cargo) ? $c->cargo : 'no especificado');
            $lines[] = "- Email: " . (isset($c->email) ? $c->email : 'no especificado');
            $lines[] = "- Celular: " . (isset($c->celular) ? $c->celular : 'no especificado');
        } else {
            $lines[] = "- Sin contacto asociado";
        }

        // Productos de la prequote
        $lines[] = "\n## Productos en la precotización";
        $items = $prequote->items;
        if ($items && count($items) > 0) {
            foreach ($items as $idx => $item) {
                $n = $idx + 1;
                $qty = (int) $item->quantity;
                $name = $item->product_name ?: 'Sin nombre';
                $total = number_format((float) $item->total_item, 2);
                $lines[] = "- Ítem {$n}: {$qty} × {$name} (total: S/ {$total})";
            }
            $lines[] = "- Total productos: " . count($items);
        } else {
            $lines[] = "- La precotización aún no tiene ítems";
        }

        // Totales
        $lines[] = "\n## Totales";
        $lines[] = "- Subtotal: S/ " . number_format((float) $prequote->subtotal, 2);
        $lines[] = "- IGV: S/ " . number_format((float) $prequote->total_igv, 2);
        $lines[] = "- Total: S/ " . number_format((float) $prequote->total, 2);

        // Requerimiento origen (si viene del ERP)
        if ($prequote->requerimiento_id) {
            $lines[] = "\n## Requerimiento origen";
            $lines[] = "- ID requerimiento: #{$prequote->requerimiento_id}";
            if ($prequote->descripcion_original) {
                $desc = strlen($prequote->descripcion_original) > 500
                    ? substr($prequote->descripcion_original, 0, 497) . '...'
                    : $prequote->descripcion_original;
                $lines[] = "- Texto original: {$desc}";
            }
        }

        // Extra context (destino envío, versión externa, etc.)
        if (!empty($extraContext)) {
            $lines[] = "\n## Contexto adicional";
            foreach ($extraContext as $k => $v) {
                $lines[] = "- {$k}: " . (is_scalar($v) ? $v : json_encode($v));
            }
        }

        // Fecha actual
        $lines[] = "\n## Fecha";
        $lines[] = "- Hoy: " . date('Y-m-d') . " (formato corto: " . date('d.m.y') . ")";

        return implode("\n", $lines);
    }

    /**
     * Construir la guía de "secciones de condiciones disponibles" que la IA usa
     * para decidir cuáles incluir (contrato: la IA SOLO elige qué secciones van;
     * los textos salen siempre del default de config, la IA no los reescribe).
     *
     * También inyecta los umbrales de envío, que la IA usa para elegir bien las
     * `notas_encabezado` (esas sí las redacta).
     *
     * @return string
     */
    private function formatDefaultsForProposal()
    {
        // Guía de inclusión por sección (cuándo tiene sentido que aparezca).
        $guia = [
            'costo_servicio'             => 'Incluir SIEMPRE.',
            'forma_pago'                 => 'Incluir SIEMPRE.',
            'tiempo_ejecucion'           => 'Incluir SIEMPRE.',
            'consideraciones_produccion' => 'Incluir cuando haya fabricación/producción del producto (lo habitual).',
            'moneda_texto'               => 'Incluir SIEMPRE.',
            'validez_oferta'             => 'Incluir SIEMPRE.',
            'consideraciones_entrega'    => 'Incluir SIEMPRE (envío/entrega del producto).',
            'terminos_instalacion'       => 'Incluir SOLO si el requerimiento involucra instalación/montaje en sitio.',
            'garantia_servicio'          => 'Incluir cuando aplique garantía posterior a la entrega (lo habitual).',
            'servicio_complementario'    => 'Incluir cuando haya trabajo en instalaciones del cliente o riesgo laboral (SCTR).',
            'especificacion_upcycling'   => 'Incluir SOLO si el proyecto es de upcycling / reciclaje de materiales del cliente.',
        ];

        $labels = QuotationCondiciones::labels();
        $lines = ['## Secciones de condiciones disponibles (elegí en "condiciones_incluidas")'];
        foreach (QuotationCondiciones::keys() as $key) {
            $label = isset($labels[$key]) ? $labels[$key] : $key;
            $hint  = isset($guia[$key]) ? $guia[$key] : '';
            $lines[] = "- `{$key}` ({$label}): {$hint}";
        }

        // Umbrales de envío (para notas_encabezado).
        try {
            $rows = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('quotation_config')
                ->whereIn('config_key', ['cotizacion_envio_umbral_lima', 'cotizacion_envio_adicional'])
                ->pluck('config_value', 'config_key');
            $umbral    = $rows->get('cotizacion_envio_umbral_lima', '550');
            $adicional = $rows->get('cotizacion_envio_adicional', '30');
            $lines[] = '';
            $lines[] = '## Parámetros de envío (para notas de encabezado)';
            $lines[] = "- Envío gratis en Lima para subtotal mayor a S/ {$umbral}; si es menor, adicional de S/ {$adicional}.";
        } catch (\Exception $e) {
            // sin umbrales — la IA usa los defaults del pool de notas
        }

        return implode("\n", $lines);
    }

    /**
     * Parsear la respuesta del LLM para proposal content.
     *
     * @param  string  $raw
     * @return array|null
     */
    private function parseProposalResponse($raw)
    {
        $clean = trim($raw);

        if (substr($clean, 0, 7) === '```json') {
            $clean = preg_replace('/^```json\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        } elseif (substr($clean, 0, 3) === '```') {
            $clean = preg_replace('/^```\s*/', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        }

        $clean = trim($clean);
        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Validar que tenga los campos mínimos esperados. Contrato nuevo: la IA
        // devuelve "condiciones_incluidas" (lista de secciones). Se acepta también
        // "condiciones" (formato viejo) para tolerar respuestas en transición.
        if (!isset($decoded['titulo_descriptivo'])
            || (!isset($decoded['condiciones_incluidas']) && !isset($decoded['condiciones']))) {
            return null;
        }

        return $decoded;
    }

    /**
     * Normalizar el contenido de proposal asegurando que todos los campos
     * tengan un valor válido.
     *
     * @param  array  $parsed
     * @param  \Modules\Marketing\Models\PreQuote  $prequote
     * @return array
     */
    private function normalizeProposalContent(array $parsed, $prequote)
    {
        // Destinatario
        $dest = isset($parsed['destinatario']) && is_array($parsed['destinatario']) ? $parsed['destinatario'] : [];
        $parsed['destinatario'] = [
            'nombre'  => isset($dest['nombre']) ? $dest['nombre'] : null,
            'cargo'   => isset($dest['cargo']) ? $dest['cargo'] : null,
            'sede'    => isset($dest['sede']) ? $dest['sede'] : null,
            'empresa' => isset($dest['empresa']) ? $dest['empresa'] : ($prequote->empresa ? (isset($prequote->empresa->razon_social) ? $prequote->empresa->razon_social : null) : null),
        ];

        // Título
        $parsed['titulo_descriptivo'] = isset($parsed['titulo_descriptivo']) && $parsed['titulo_descriptivo']
            ? $parsed['titulo_descriptivo']
            : 'COTIZACIÓN PROPUESTA_' . date('d.m.y');

        // Notas encabezado (siempre array de 3)
        $notas = isset($parsed['notas_encabezado']) && is_array($parsed['notas_encabezado']) ? $parsed['notas_encabezado'] : [];
        while (count($notas) < 3) {
            $defaults = [
                'Productos de fabricación nacional',
                'Logo a ser facilitado por el Cliente (en Corel, Photoshop, Ilustrator)',
                'Precios incluyen gastos de envío a Lima Metropolitana, mas no IGV.',
            ];
            $notas[] = $defaults[count($notas)];
        }
        $parsed['notas_encabezado'] = array_slice($notas, 0, 3);

        // Booleans y moneda
        $parsed['precios_incluyen_igv']    = isset($parsed['precios_incluyen_igv']) ? (bool) $parsed['precios_incluyen_igv'] : false;
        $parsed['moneda']                  = isset($parsed['moneda']) ? $parsed['moneda'] : 'PEN';
        $parsed['incluye_datos_empresa']   = isset($parsed['incluye_datos_empresa']) ? (bool) $parsed['incluye_datos_empresa'] : true;
        $parsed['incluye_datos_bancarios'] = isset($parsed['incluye_datos_bancarios']) ? (bool) $parsed['incluye_datos_bancarios'] : true;

        // Condiciones (contrato: la IA elige qué secciones incluir; el server arma
        // los textos desde los defaults de config). Siempre las 11 claves.
        $incluidas = $this->extractCondicionesIncluidas($parsed);
        $parsed['condiciones_incluidas'] = $incluidas;
        $parsed['condiciones'] = QuotationCondiciones::assemble($incluidas);

        // Justificación y resumen
        $parsed['justificacion_bancario']  = isset($parsed['justificacion_bancario']) ? $parsed['justificacion_bancario'] : '';
        $parsed['interpretation_summary'] = isset($parsed['interpretation_summary']) ? $parsed['interpretation_summary'] : '';

        return $parsed;
    }

    /**
     * Fallback cuando la IA falla: construye proposal content con defaults
     * del sistema sin intervención del LLM.
     *
     * @param  \Modules\Marketing\Models\PreQuote  $prequote
     * @param  string  $message
     * @return array
     */
    private function fallbackProposalContent($prequote, $message)
    {
        $empresa = $prequote->empresa ? (isset($prequote->empresa->razon_social) ? $prequote->empresa->razon_social : null) : null;

        // Contrato: incluir el set de secciones por defecto, con sus textos de
        // config (QuotationCondiciones arma el mapa de las 11 claves).
        $incluidas = QuotationCondiciones::defaultIncluidas();

        return [
            'destinatario' => [
                'nombre'  => null,
                'cargo'   => null,
                'sede'    => null,
                'empresa' => $empresa,
            ],
            'titulo_descriptivo' => 'COTIZACIÓN PROPUESTA_' . ($empresa ? strtoupper($empresa) . '_' : '') . date('d.m.y'),
            'notas_encabezado' => [
                'Productos de fabricación nacional',
                'Logo a ser facilitado por el Cliente (en Corel, Photoshop, Ilustrator)',
                'Precios incluyen gastos de envío a Lima Metropolitana, mas no IGV.',
            ],
            'precios_incluyen_igv' => false,
            'moneda' => 'PEN',
            'condiciones_incluidas' => $incluidas,
            'condiciones' => QuotationCondiciones::assemble($incluidas),
            'incluye_datos_empresa'   => true,
            'incluye_datos_bancarios' => true,
            'justificacion_bancario'  => 'Fallback: se incluyen datos bancarios por defecto (cliente puede requerirlos).',
            'interpretation_summary'  => $message,
            '_fallback'               => true,
        ];
    }

    /**
     * Determinar qué secciones de condiciones incluir a partir de la respuesta de
     * la IA. Acepta el contrato nuevo ("condiciones_incluidas": [claves]) y, por
     * tolerancia, el viejo ("condiciones": {clave: texto}) → incluidas = las
     * claves con texto no vacío. Si no viene ninguno, usa el set por defecto.
     *
     * @param  array  $parsed
     * @return string[]
     */
    private function extractCondicionesIncluidas(array $parsed)
    {
        $valid = QuotationCondiciones::keys();

        // Contrato nuevo: lista de claves.
        if (isset($parsed['condiciones_incluidas']) && is_array($parsed['condiciones_incluidas'])) {
            $scalars = array_filter($parsed['condiciones_incluidas'], 'is_scalar');
            $incluidas = array_values(array_intersect(array_map('strval', $scalars), $valid));
            return !empty($incluidas) ? $incluidas : QuotationCondiciones::defaultIncluidas();
        }

        // Contrato viejo: derivar de las claves con texto no vacío.
        if (isset($parsed['condiciones']) && is_array($parsed['condiciones'])) {
            $incluidas = [];
            foreach ($valid as $key) {
                if (!isset($parsed['condiciones'][$key])) {
                    continue;
                }
                $v = $parsed['condiciones'][$key];
                $noVacio = is_array($v) ? !empty($v) : trim((string) $v) !== '';
                if ($noVacio) {
                    $incluidas[] = $key;
                }
            }
            return !empty($incluidas) ? $incluidas : QuotationCondiciones::defaultIncluidas();
        }

        return QuotationCondiciones::defaultIncluidas();
    }

    /**
     * Construir el system prompt con catálogo inyectado.
     *
     * @param  array  $context
     * @return string
     */
    private function buildSystemPrompt(array $context = [])
    {
        $prompt = $this->systemPromptTemplate;

        // Inyectar catálogo de productos del ERP
        $catalogItems = $this->getCatalogForPrompt($context);
        $prompt = str_replace('{{CATALOG_ITEMS}}', $catalogItems, $prompt);

        // Los casos históricos ya no se envían a la IA: los tomaba como productos del
        // pedido. Lo ya cotizado lo busca el sistema después (CotizacionAnteriorService).

        return $prompt;
    }

    /**
     * Obtener productos del catálogo del ERP para el prompt.
     *
     * @param  array  $context
     * @return string
     */
    private function getCatalogForPrompt(array $context = [])
    {
        try {
            $query = \App\Models\Tenant\Item::query()
                ->where('active', true)
                ->select(['id', 'name', 'description', 'internal_id', 'sale_unit_price', 'purchase_unit_price'])
                ->with('category:id,name');

            // Filtrar por categoría si viene en el contexto
            $categoria = isset($context['categoria_productos']) ? $context['categoria_productos'] : null;
            if ($categoria) {
                $query->whereHas('category', function ($q) use ($categoria) {
                    $q->where('name', 'LIKE', '%' . $categoria . '%');
                });
            }

            $items = $query->orderBy('name')->limit(100)->get();

            if ($items->isEmpty()) {
                return "No hay productos cargados en el catálogo del ERP.";
            }

            $lines = [];
            foreach ($items as $item) {
                $cat = $item->category ? $item->category->name : 'Sin categoría';
                $price = $item->sale_unit_price > 0 ? ' | Precio ref: S/ ' . number_format($item->sale_unit_price, 2) : '';
                $lines[] = "- [ID:{$item->id}] {$item->name} ({$cat}){$price}";
            }

            return implode("\n", $lines);

        } catch (\Exception $e) {
            Log::warning('QuotationAIEngine: no se pudo cargar catálogo', [
                'error' => $e->getMessage(),
            ]);
            return "Catálogo no disponible en este momento.";
        }
    }

    /**
     * S2 — Cargar las imágenes del contexto para multimodal.
     *
     * El contexto puede incluir:
     *   - 'reference_image_path' (string|null) — path relativo al disk local
     *     de la imagen general del requerimiento (a nivel prequote)
     *   - 'item_image_paths' (array<string>) — paths de imágenes de items
     *     individuales (raramente útil en interpretRequirement; se usa más en
     *     suggestSuppliersForItem). Para mantener simple, ignoramos por ahora
     *     y solo usamos la prequote-level.
     *
     * Lee los bytes, los codifica base64 y arma el array que cada provider
     * sabe formatear (formato común: ['mime_type' => '...', 'data' => '<base64>']).
     *
     * Si la imagen excede 5MB la ignoramos (los providers tienen límites
     * variables, mejor prevenir).
     *
     * @param  array  $context
     * @return array  Lista de imágenes en formato común (puede ser vacía)
     */
    private function loadImagesForContext(array $context)
    {
        $images = [];
        $maxBytes = 5 * 1024 * 1024; // 5 MB hard cap

        $path = isset($context['reference_image_path']) ? $context['reference_image_path'] : null;
        if (empty($path)) {
            return $images;
        }

        try {
            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                Log::warning('QuotationAIEngine: reference_image_path no existe en disco', ['path' => $path]);
                return $images;
            }

            $bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($path);
            $size = strlen($bytes);
            if ($size > $maxBytes) {
                Log::warning('QuotationAIEngine: imagen excede límite multimodal, se omite', [
                    'path' => $path, 'size' => $size,
                ]);
                return $images;
            }

            $mime = isset($context['reference_image_mime']) ? $context['reference_image_mime'] : null;
            if (empty($mime)) {
                $mime = $this->guessMimeFromExtension($path);
            }

            $images[] = [
                'mime_type' => $mime,
                'data'      => base64_encode($bytes),
            ];

            Log::info('QuotationAIEngine: imagen cargada para multimodal', [
                'path' => $path, 'mime' => $mime, 'size_bytes' => $size,
            ]);

        } catch (\Exception $e) {
            Log::warning('QuotationAIEngine: error cargando imagen multimodal', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return $images;
    }

    /**
     * Detectar MIME a partir de la extensión del path (fallback).
     *
     * @param  string  $path
     * @return string
     */
    private function guessMimeFromExtension($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        return isset($map[$ext]) ? $map[$ext] : 'image/jpeg';
    }


    /**
     * Parsear la respuesta raw del LLM como JSON.
     *
     * @param  string  $raw
     * @return array|null
     */
    private function parseResponse($raw)
    {
        $clean = trim($raw);

        // Remover bloques markdown
        if (substr($clean, 0, 7) === '```json') {
            $clean = preg_replace('/^```json\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        } elseif (substr($clean, 0, 3) === '```') {
            $clean = preg_replace('/^```\s*/', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        }

        $clean = trim($clean);
        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!isset($decoded['items'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Reintentar si la primera respuesta no fue JSON válido.
     *
     * @param  string  $systemPrompt
     * @param  array   $messages
     * @param  string  $badResponse
     * @return array|null
     */
    private function retryWithCorrection($systemPrompt, $messages, $badResponse)
    {
        $corrected = $messages;
        $corrected[] = ['role' => 'assistant', 'content' => $badResponse];
        $corrected[] = [
            'role'    => 'user',
            'content' => 'Tu respuesta no es JSON válido. Responde SOLAMENTE con el JSON según el esquema indicado. Sin texto adicional.',
        ];

        try {
            $rawRetry = $this->getProvider()->sendMessage($systemPrompt, $corrected, [
                'temperature' => 0.1,
            ]);
            return $this->parseResponse($rawRetry);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalizar la respuesta del LLM.
     *
     * @param  array  $parsed
     * @return array
     */
    private function normalizeResponse(array $parsed)
    {
        $parsed['interpretation_summary'] = isset($parsed['interpretation_summary']) ? $parsed['interpretation_summary'] : '';
        $parsed['total_items_detected'] = isset($parsed['total_items_detected']) ? (int) $parsed['total_items_detected'] : count(isset($parsed['items']) ? $parsed['items'] : []);
        $parsed['shared_attributes_applied'] = isset($parsed['shared_attributes_applied']) ? $parsed['shared_attributes_applied'] : [];
        $parsed['warnings'] = isset($parsed['warnings']) ? $parsed['warnings'] : [];

        $items = isset($parsed['items']) ? $parsed['items'] : [];
        $normalized = [];

        foreach ($items as $i => $item) {
            $normalized[] = [
                'item_order'          => isset($item['item_order']) ? (int) $item['item_order'] : ($i + 1),
                'product_name'        => isset($item['product_name']) ? $item['product_name'] : 'Producto sin nombre',
                'product_description' => isset($item['product_description']) ? $item['product_description'] : null,
                'category'            => isset($item['category']) ? $item['category'] : 'Otros',
                'quantity'            => isset($item['quantity']) ? (int) $item['quantity'] : 0,
                'material'            => isset($item['material']) ? $item['material'] : null,
                'medida'              => isset($item['medida']) ? $item['medida'] : null,
                'tecnica_impresion'   => isset($item['tecnica_impresion']) ? $item['tecnica_impresion'] : null,
                'cantidad_colores'    => isset($item['cantidad_colores']) ? (int) $item['cantidad_colores'] : 1,
                'cantidad_lados'      => isset($item['cantidad_lados']) ? (int) $item['cantidad_lados'] : 1,
                'empaque'             => isset($item['empaque']) ? $item['empaque'] : null,
                'personalizacion'     => isset($item['personalizacion']) ? $item['personalizacion'] : null,
                'datos_explicitos'    => isset($item['datos_explicitos']) ? $item['datos_explicitos'] : [],
                'datos_inferidos'     => isset($item['datos_inferidos']) ? $item['datos_inferidos'] : [],
                'observaciones'       => isset($item['observaciones']) ? $item['observaciones'] : null,
            ];
        }

        $parsed['items'] = $normalized;
        $parsed['total_items_detected'] = count($normalized);

        return $parsed;
    }

    /**
     * Respuesta de fallback.
     *
     * @param  string  $message
     * @return array
     */
    private function fallbackResponse($message)
    {
        return [
            'interpretation_summary' => $message,
            'total_items_detected'   => 0,
            'items'                  => [],
            'shared_attributes_applied' => [],
            'warnings'               => [$message],
            '_fallback'              => true,
        ];
    }
}

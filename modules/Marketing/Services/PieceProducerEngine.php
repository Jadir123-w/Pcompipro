<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Models\PlatformConfig;

/**
 * PieceProducerEngine — Productor de piezas finales (MKT-03)
 *
 * Convierte un ítem de grilla en una pieza publicable:
 * copy + CTA + hashtags + brief visual + imagen generada.
 *
 * Provider de TEXTO y de IMAGEN se resuelven en runtime desde
 * marketing_agent_config (agent_key=mkt03), con fallback al .env.
 */
class PieceProducerEngine
{
    /** @var AgentConfigService */
    private $configService;

    /** @var ImageGenerationService */
    private $imageService;

    /** @var EditorialGuidelinesService|null */
    private $guidelines;

    /** @var string */
    private $agentKey = AgentConfig::AGENT_MKT03;

    /** @var string */
    private $systemPromptTemplate;

    /** @var AIProviderInterface|null Cache por instancia */
    private $provider = null;

    public function __construct(
        AgentConfigService $configService,
        ImageGenerationService $imageService,
        EditorialGuidelinesService $guidelines = null
    ) {
        $this->configService = $configService;
        $this->imageService = $imageService;
        $this->guidelines = $guidelines ?: new EditorialGuidelinesService();
        $this->systemPromptTemplate = $this->loadSystemPrompt();
    }

    /**
     * Resolver el provider de texto en runtime (lazy + cached).
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
     * Producir la pieza final para un GridItem dado.
     *
     * @param  GridItem  $gridItem
     * @param  bool      $generateImage  Si true, genera imagen con ImageGenerationService
     * @return Piece|null
     */
    public function producePiece(GridItem $gridItem, $generateImage = true)
    {
        // 1. Construir contexto del ítem para el LLM
        $context = $this->buildItemContext($gridItem);

        $messages = [
            [
                'role'    => 'user',
                'content' => $context,
            ],
        ];

        // 2. Llamar al LLM para generar copy + CTA + hashtags + brief visual + imagen_prompt
        // Inyectar las guidelines editoriales (M4) con la sección específica de la red del ítem.
        $effectiveSystemPrompt = $this->systemPromptTemplate
            . $this->guidelines->buildSystemPromptAddendum($gridItem->red_social);

        try {
            $rawResponse = $this->getProvider()->sendMessage($effectiveSystemPrompt, $messages, [
                'temperature' => 0.7, // Creatividad para copy
                // El JSON de la pieza incluye copy + CTA + hashtags + brief visual + imagen_prompt;
                // 2000 era ajustado para modelos con thinking tokens. Subido para coherencia con M1/M2.
                'max_tokens'  => 6000,
            ]);
        } catch (AIProviderException $e) {
            Log::error('PieceProducerEngine: error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);
            return null;
        }

        $parsed = $this->parseResponse($rawResponse);
        if ($parsed === null || !isset($parsed['pieza_producida'])) {
            Log::warning('PieceProducerEngine: respuesta no parseable');
            return null;
        }

        $piezaData = $parsed['pieza_producida'];

        // 3. Crear la Piece en BD
        $piece = Piece::create([
            'grid_item_id'           => $gridItem->id,
            'red_social'             => isset($piezaData['red_social']) ? $piezaData['red_social'] : $gridItem->red_social,
            'copy'                   => isset($piezaData['copy']) ? $piezaData['copy'] : null,
            'cta'                    => isset($piezaData['cta']) ? $piezaData['cta'] : null,
            'hashtags'               => isset($piezaData['hashtags']) ? $piezaData['hashtags'] : null,
            'formato'                => isset($piezaData['formato']) ? $piezaData['formato'] : 'post',
            'enfoque_visual'         => isset($piezaData['enfoque_visual']) ? $piezaData['enfoque_visual'] : null,
            'referencia_visual'      => null,
            'texto_en_imagen_video'  => isset($piezaData['texto_en_imagen_video']) ? $piezaData['texto_en_imagen_video'] : null,
            'imagen_prompt'          => isset($piezaData['imagen_prompt']) ? $piezaData['imagen_prompt'] : null,
            'imagen_source'          => Piece::IMAGEN_AI_GENERATED,
            'estado_pieza'           => Piece::ESTADO_EN_PRODUCCION,
            'version'                => 1,
            'is_current'             => true,
            'ai_text_provider'       => $this->getProvider()->getProviderName(),
        ]);

        // 4. Generar imagen con DALL-E 3 si corresponde
        if ($generateImage && !empty($piece->imagen_prompt)) {
            $this->generateImageForPiece($piece, $piezaData);
        }

        Log::info('PieceProducerEngine: pieza producida', [
            'piece_id'     => $piece->id,
            'grid_item_id' => $gridItem->id,
            'red_social'   => $piece->red_social,
            'con_imagen'   => !empty($piece->imagen_final_url),
        ]);

        return $piece->fresh();
    }

    /**
     * Generar la imagen de una pieza y actualizar sus campos.
     *
     * @param  Piece  $piece
     * @param  array  $piezaData
     * @return void
     */
    private function generateImageForPiece(Piece $piece, array $piezaData)
    {
        $aspectRatio = isset($piezaData['aspect_ratio']) ? $piezaData['aspect_ratio'] : '1:1';

        $result = $this->imageService->generate($piece->imagen_prompt, [
            'aspect_ratio' => $aspectRatio,
            'quality'      => 'standard',
            'style'        => 'vivid',
        ]);

        if ($result['success']) {
            $piece->update([
                'imagen_final_url'   => isset($result['url']) ? $result['url'] : null,
                'ai_image_provider'  => isset($result['provider']) ? $result['provider'] : null,
                'ai_image_model'     => isset($result['model']) ? $result['model'] : null,
                'estado_pieza'       => Piece::ESTADO_LISTA_PUBLICAR,
            ]);
        } else {
            Log::warning('PieceProducerEngine: no se pudo generar imagen', [
                'piece_id' => $piece->id,
                'error'    => isset($result['error']) ? $result['error'] : 'unknown',
            ]);
            $piece->update([
                'estado_pieza' => Piece::ESTADO_OBSERVADA,
                'observaciones' => 'Pieza textual generada. Imagen pendiente: ' . (isset($result['error']) ? $result['error'] : 'error desconocido'),
            ]);
        }
    }

    /**
     * Construir el contexto del ítem para el LLM.
     *
     * @param  GridItem  $gridItem
     * @return string
     */
    private function buildItemContext(GridItem $gridItem)
    {
        $cfg = PlatformConfig::where('red_social', $gridItem->red_social)->first();

        $ctx  = "# ÍTEM A PRODUCIR\n\n";
        $ctx .= "- Red social: {$gridItem->red_social}\n";
        $ctx .= "- Fecha publicación: " . ($gridItem->fecha_publicacion ? $gridItem->fecha_publicacion->format('Y-m-d') : 'N/A') . "\n";
        $ctx .= "- Temática: " . ($gridItem->tematica ?: 'N/A') . "\n";
        $ctx .= "- Campaña: " . ($gridItem->campania ?: 'N/A') . "\n";
        $ctx .= "- Temporada: " . ($gridItem->temporada ?: 'N/A') . "\n";
        $ctx .= "- Evento: " . ($gridItem->evento ?: 'N/A') . "\n";
        $ctx .= "- Objetivo: " . ($gridItem->objetivo ?: 'N/A') . "\n";
        $ctx .= "- Línea comercial: " . ($gridItem->linea_comercial ?: 'N/A') . "\n";
        $ctx .= "- Tipo de contenido: " . ($gridItem->tipo_contenido ?: 'N/A') . "\n";
        $ctx .= "- Prioridad: {$gridItem->prioridad}\n";
        $ctx .= "- Origen: {$gridItem->origen}\n";
        if ($gridItem->fase_feria) {
            $ctx .= "- Fase de feria: {$gridItem->fase_feria}\n";
        }
        if ($gridItem->observaciones) {
            $ctx .= "- Observaciones: {$gridItem->observaciones}\n";
        }
        $ctx .= "\n";

        if ($cfg) {
            $ctx .= "# CONFIGURACIÓN DE {$cfg->nombre_publico}\n\n";
            $ctx .= "- Función editorial principal: {$cfg->funcion_editorial_principal}\n";
            $ctx .= "- Qué priorizar: {$cfg->que_priorizar}\n";
            $ctx .= "- Qué evitar: {$cfg->que_evitar}\n";
            $ctx .= "- Tono recomendado: {$cfg->tono_recomendado}\n";
            $ctx .= "- Max copy length: {$cfg->max_copy_length}\n";
            $ctx .= "- Max hashtags: {$cfg->max_hashtags}\n";
            $ctx .= "- Aspect ratio default: {$cfg->aspect_ratio_default}\n";
            $ctx .= "- Formatos soportados: " . json_encode($cfg->formatos_soportados) . "\n\n";
        }

        $ctx .= "Produce la pieza final respetando todas las reglas del sistema. Devuelve el JSON según el esquema indicado.";

        return $ctx;
    }

    /**
     * @return string
     */
    private function loadSystemPrompt()
    {
        $path = __DIR__ . '/../Prompts/mkt03-piece-producer-prompt.md';
        if (!file_exists($path)) {
            Log::error('PieceProducerEngine: mkt03-piece-producer-prompt.md no encontrado');
            return 'Eres un productor de piezas publicables. Responde en JSON válido.';
        }
        return file_get_contents($path);
    }

    /**
     * @param  string  $raw
     * @return array|null
     */
    private function parseResponse($raw)
    {
        $clean = trim($raw);
        if (substr($clean, 0, 7) === '```json') {
            $clean = preg_replace('/^```json\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        } elseif (substr($clean, 0, 3) === '```') {
            $clean = preg_replace('/^```\s*/', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        }
        $decoded = json_decode(trim($clean), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }
}

<?php

namespace Modules\Marketing\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Services\AI\ClaudeProvider;
use Modules\Marketing\Services\AI\GeminiProvider;
use Modules\Marketing\Services\AI\OpenAIProvider;
use Modules\Marketing\Services\AgentConfigService;
use Modules\Marketing\Services\AIConversationEngine;
use Modules\Marketing\Services\ConversationSessionManager;
use Modules\Marketing\Services\ChatbotOrchestrator;
use Modules\Marketing\Services\LeadRegistrationService;
use Modules\Marketing\Services\WhatsAppService;
use Modules\Marketing\Services\QuotationAIEngine;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Modules\Marketing\Services\QuotationOrchestrator;
use Modules\Marketing\Services\MarginService;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Services\Image\DallE3Provider;
use Modules\Marketing\Services\Image\GPTImage2Provider;
use Modules\Marketing\Services\ImageGenerationService;
use Modules\Marketing\Services\EditorialGuidelinesService;
use Modules\Marketing\Services\GridContinuityService;
use Modules\Marketing\Services\GridPatternAnalyzer;
use Modules\Marketing\Services\GridGeneratorEngine;
use Modules\Marketing\Services\PieceProducerEngine;
use Modules\Marketing\Services\MKT03Orchestrator;
use Modules\Marketing\Services\ExcelHistoricalImporter;

/**
 * ChatbotServiceProvider
 *
 * Registra los bindings del chatbot en el Service Container de Laravel.
 * Actualizado en Fase 3 con SessionManager, Orchestrator y LeadRegistration.
 */
class ChatbotServiceProvider extends ServiceProvider
{
    /**
     * Registrar bindings en el container.
     *
     * @return void
     */
    public function register()
    {
        // ── Merge config ──
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/chatbot.php',
            'marketing.chatbot'
        );

        // ── Bind del proveedor de IA según config ──
        // bind (no singleton) para que cada resolve consulte la config en BD.
        // Prioridad: marketing_chatbot_config (key chatbot_ai_provider) → .env → 'claude'.
        $this->app->bind(AIProviderInterface::class, function ($app) {
            $provider = $this->resolveChatbotSetting('chatbot_ai_provider', 'ai_provider', 'claude');

            switch ($provider) {
                case 'claude':
                    return new ClaudeProvider();
                case 'openai':
                    return new OpenAIProvider();
                case 'gemini':
                    return new GeminiProvider();
                default:
                    throw new \InvalidArgumentException(
                        "Proveedor de IA no soportado: {$provider}. Usa 'claude', 'openai' o 'gemini'."
                    );
            }
        });

        // ── Bind del AgentConfigService (servicio central de resolución por agente) ──
        $this->app->singleton(AgentConfigService::class, function ($app) {
            return new AgentConfigService();
        });

        // ── Bind del AIConversationEngine (MKT-01) ──
        // Resuelve provider + modelo desde marketing_agent_config (agent_key=mkt01)
        $this->app->singleton(AIConversationEngine::class, function ($app) {
            return new AIConversationEngine(
                $app->make(AgentConfigService::class)
            );
        });

        // ── Bind del ConversationSessionManager ──
        $this->app->singleton(ConversationSessionManager::class, function ($app) {
            return new ConversationSessionManager();
        });

        // ── Bind del LeadRegistrationService ──
        $this->app->singleton(LeadRegistrationService::class, function ($app) {
            return new LeadRegistrationService();
        });

        // ── Bind del WhatsAppService ──
        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService();
        });

        // ══════════════════════════════════════════════
        // COM-02: Cotizador Inteligente
        // ══════════════════════════════════════════════

        // ── Bind del QuotationAIEngine (COM-02) ──
        // Resuelve provider + modelo desde marketing_agent_config (agent_key=com02)
        $this->app->singleton(QuotationAIEngine::class, function ($app) {
            return new QuotationAIEngine(
                $app->make(AgentConfigService::class)
            );
        });

        // ── Bind del MarginService ──
        $this->app->singleton(MarginService::class, function ($app) {
            return new MarginService();
        });

        // ── Bind del QuotationCalculationEngine ──
        $this->app->singleton(QuotationCalculationEngine::class, function ($app) {
            return new QuotationCalculationEngine();
        });

        // ── Bind del QuotationOrchestrator ──
        $this->app->singleton(QuotationOrchestrator::class, function ($app) {
            return new QuotationOrchestrator(
                $app->make(QuotationAIEngine::class),
                $app->make(QuotationCalculationEngine::class),
                $app->make(MarginService::class),
                $app->make(\Modules\Marketing\Services\ProductMatchingService::class),
                $app->make(\Modules\Marketing\Services\VolumePricingService::class),
                $app->make(\Modules\Marketing\Services\CotizacionAnteriorService::class)
            );
        });

        // ── Bind del ProposalPdfGenerator (COM-02 E2 — Cotización Final) ──
        $this->app->singleton(\Modules\Marketing\Services\ProposalPdfGenerator::class, function ($app) {
            return new \Modules\Marketing\Services\ProposalPdfGenerator();
        });

        // ══════════════════════════════════════════════
        // MKT-03: Social Media Content
        // ══════════════════════════════════════════════

        // ── Bind del ImageProviderInterface ──
        // IMPORTANTE: usamos bind (no singleton) para que cada resolve consulte
        // la config en BD. Eso permite cambiar el provider desde la UI Config
        // Chatbot sin reiniciar el servicio. La resolución busca primero en
        // marketing_chatbot_config (clave 'mkt03_image_provider') y cae al
        // .env como fallback si la tabla no está disponible (instalación fresh).
        $this->app->bind(ImageProviderInterface::class, function ($app) {
            $provider = $this->resolveMkt03Setting('mkt03_image_provider', 'image_provider', 'dalle3');

            switch ($provider) {
                case 'dalle3':
                    return new DallE3Provider();
                case 'gpt-image-2':
                    return new GPTImage2Provider();
                default:
                    throw new \InvalidArgumentException(
                        "Proveedor de imagen no soportado: {$provider}. Usa 'dalle3' o 'gpt-image-2'."
                    );
            }
        });

        // ── Bind del ImageGenerationService ──
        $this->app->singleton(ImageGenerationService::class, function ($app) {
            return new ImageGenerationService(
                $app->make(ImageProviderInterface::class)
            );
        });

        // ── Bind del GridPatternAnalyzer ──
        $this->app->singleton(GridPatternAnalyzer::class, function ($app) {
            return new GridPatternAnalyzer(
                $app->make(AIProviderInterface::class)
            );
        });

        // ── Bind del EditorialGuidelinesService (MKT-03 M4 — hallazgos del cliente) ──
        $this->app->singleton(EditorialGuidelinesService::class, function ($app) {
            return new EditorialGuidelinesService();
        });

        // ── Bind del GridGeneratorEngine ──
        $this->app->singleton(GridGeneratorEngine::class, function ($app) {
            return new GridGeneratorEngine(
                $app->make(AIProviderInterface::class),
                $app->make(EditorialGuidelinesService::class)
            );
        });

        // ── Bind del PieceProducerEngine (MKT-03) ──
        // Resuelve provider de TEXTO + IMAGEN desde marketing_agent_config (agent_key=mkt03)
        $this->app->singleton(PieceProducerEngine::class, function ($app) {
            return new PieceProducerEngine(
                $app->make(AgentConfigService::class),
                $app->make(ImageGenerationService::class),
                $app->make(EditorialGuidelinesService::class)
            );
        });

        // ── Bind del GridContinuityService (Modo C — §11.5 doc funcional) ──
        $this->app->singleton(GridContinuityService::class, function ($app) {
            return new GridContinuityService();
        });

        // ── Bind del MKT03Orchestrator ──
        $this->app->singleton(MKT03Orchestrator::class, function ($app) {
            return new MKT03Orchestrator(
                $app->make(GridPatternAnalyzer::class),
                $app->make(GridGeneratorEngine::class),
                $app->make(PieceProducerEngine::class),
                $app->make(GridContinuityService::class)
            );
        });

        // ── Bind del ExcelHistoricalImporter ──
        $this->app->singleton(ExcelHistoricalImporter::class, function ($app) {
            return new ExcelHistoricalImporter();
        });

        // ══════════════════════════════════════════════
        // MKT-01: ChatBot
        // ══════════════════════════════════════════════

        // ── Bind del ChatbotOrchestrator ──
        $this->app->singleton(ChatbotOrchestrator::class, function ($app) {
            return new ChatbotOrchestrator(
                $app->make(ConversationSessionManager::class),
                $app->make(AIConversationEngine::class),
                $app->make(LeadRegistrationService::class)
            );
        });
    }

    /**
     * Boot.
     *
     * Las rutas se cargan desde RouteServiceProvider (no aquí,
     * para evitar doble registro con prefijo api/api/).
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Resuelve un setting general del chatbot con prioridad BD → config legacy → default.
     *
     * Para configs que viven en config('marketing.chatbot.X') directamente (no
     * dentro de un sub-array como mkt03).
     *
     * @param  string  $configKey         Clave en marketing_chatbot_config
     * @param  string  $legacyConfigPath  Path en config('marketing.chatbot.X')
     * @param  mixed   $default
     * @return mixed
     */
    private function resolveChatbotSetting($configKey, $legacyConfigPath, $default)
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get($configKey);
            if ($val !== null && $val !== '') {
                return $val;
            }
        } catch (\Exception $e) {
            // Tabla no disponible — fallback silencioso
        }
        return config('marketing.chatbot.' . $legacyConfigPath, $default);
    }

    /**
     * Resuelve un setting MKT-03 con prioridad BD → config legacy → default.
     *
     * Si la tabla `marketing_chatbot_config` existe en el tenant actual y tiene
     * la clave, devuelve ese valor. Sino cae al config Laravel (que puede leer
     * de .env). Sino devuelve el default.
     *
     * Si la tabla aún no existe (instalación fresh, antes de migrar), no falla:
     * sigue de largo al fallback.
     *
     * @param  string       $configKey      Clave en marketing_chatbot_config (ej: mkt03_image_provider)
     * @param  string       $legacyConfigPath Path en config('marketing.chatbot.mkt03.X') (ej: image_provider)
     * @param  mixed        $default        Default si nada está configurado
     * @return mixed
     */
    private function resolveMkt03Setting($configKey, $legacyConfigPath, $default)
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get($configKey);
            if ($val !== null && $val !== '') {
                return $val;
            }
        } catch (\Exception $e) {
            // Tabla no disponible (instalación fresh, sin tenant resuelto, etc.) — fallback
        }
        return config('marketing.chatbot.mkt03.' . $legacyConfigPath, $default);
    }
}

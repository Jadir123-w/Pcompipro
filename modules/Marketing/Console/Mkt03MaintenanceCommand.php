<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Services\ImageGenerationService;

/**
 * Comando de mantenimiento y diagnóstico del agente MKT-03.
 *
 * Uso:
 *   php artisan marketing:mkt03 status                        → Resumen del estado del agente (todos los tenants)
 *   php artisan marketing:mkt03 status --tenant=1             → Resumen de un tenant específico
 *   php artisan marketing:mkt03 update-feria-fases            → Recalcula fase_editorial_actual de ferias
 *   php artisan marketing:mkt03 continuity-check              → Detecta grillas por agotarse (Modo C auto, Etapa 2)
 *   php artisan marketing:mkt03 auto-schedule                 → Programa piezas "aprobado" (Etapa 2 stub)
 *   php artisan marketing:mkt03 test-image                                       → Smoke test text→image con prompt default
 *   php artisan marketing:mkt03 test-image --prompt="..."                        → Prompt custom
 *   php artisan marketing:mkt03 test-image --quality=high                        → Override quality
 *   php artisan marketing:mkt03 test-image --aspect-ratio=4:5                    → Vertical (stories)
 *   php artisan marketing:mkt03 test-image --reference=/path/to/img.jpg          → Edit con 1 referencia (gpt-image-2 only)
 *   php artisan marketing:mkt03 test-image --reference=a.jpg --reference=b.jpg   → Edit con varias referencias
 *   php artisan marketing:mkt03 test-image --reference=img.jpg --mask=mask.png   → Inpainting con máscara
 */
class Mkt03MaintenanceCommand extends Command
{
    protected $signature = 'marketing:mkt03
                            {action=status : status|update-feria-fases|continuity-check|auto-schedule|test-image}
                            {--tenant= : ID del tenant (Website) a procesar. Sin este parámetro, procesa todos.}
                            {--prompt= : Prompt custom para test-image (solo aplica a esa acción)}
                            {--aspect-ratio=1:1 : Aspect ratio para test-image (1:1, 4:5, 16:9)}
                            {--quality= : Quality override. dalle3: standard|hd. gpt-image-2: low|medium|high|auto. Sin valor = default del provider.}
                            {--reference=* : Ruta(s) a imagen(es) de referencia (solo gpt-image-2). Repite la opción para múltiples: --reference=foo.jpg --reference=bar.jpg}
                            {--mask= : Ruta a máscara con canal alfa para inpainting (solo gpt-image-2). Requiere al menos 1 --reference.}
                            {--auto : (continuity-check) Ejecuta la extensión automática de las grillas candidatas. Sin esto, solo lista.}
                            {--dias= : (continuity-check) Umbral en días para considerar una grilla candidata. Default 14.}';

    protected $description = 'Comando de mantenimiento y diagnóstico del agente MKT-03 (Social Media Content)';

    public function handle()
    {
        $action = $this->argument('action');
        $tenantId = $this->option('tenant');

        // Validar acción
        $validActions = ['status', 'update-feria-fases', 'continuity-check', 'auto-schedule', 'test-image'];
        if (!in_array($action, $validActions)) {
            $this->error("Acción desconocida: {$action}");
            $this->line("Acciones válidas: " . implode(', ', $validActions));
            return 1;
        }

        // test-image NO necesita iterar tenants — es un smoke test del provider
        if ($action === 'test-image') {
            return $this->cmdTestImage();
        }

        // Determinar lista de tenants a procesar
        if ($tenantId) {
            $website = Website::find($tenantId);
            if (!$website) {
                $this->error("Tenant con ID {$tenantId} no encontrado.");
                return 1;
            }
            $websites = collect([$website]);
        } else {
            $websites = Website::all();
            if ($websites->isEmpty()) {
                $this->error('No hay tenants registrados.');
                return 1;
            }
            $this->info("Procesando {$websites->count()} tenant(s)...");
            $this->line('');
        }

        $tenancy = app(Environment::class);

        foreach ($websites as $website) {
            $tenancy->tenant($website);
            $this->info(">>> Tenant: {$website->uuid} (ID: {$website->id})");

            try {
                switch ($action) {
                    case 'status':
                        $this->cmdStatus();
                        break;
                    case 'update-feria-fases':
                        $this->cmdUpdateFeriaFases();
                        break;
                    case 'continuity-check':
                        $this->cmdContinuityCheck();
                        break;
                    case 'auto-schedule':
                        $this->cmdAutoSchedule();
                        break;
                }
            } catch (\Exception $e) {
                $this->error("Error en tenant {$website->uuid}: " . $e->getMessage());
            }

            $this->line('');
        }

        return 0;
    }

    /**
     * Smoke test: genera una imagen de prueba con DALL-E 3.
     *
     * Valida end-to-end: API key → request → response → download → storage.
     * No requiere contexto tenant (usa la config global de OpenAI).
     */
    private function cmdTestImage()
    {
        $this->info('═══════════════════════════════════════════════');
        $this->info('  MKT-03 — Smoke test de generación de imagen');
        $this->info('═══════════════════════════════════════════════');
        $this->line('');

        // 1. Validar API key configurada
        $apiKey = config('marketing.chatbot.ai.openai.api_key');
        if (empty($apiKey)) {
            $this->error('✗ CHATBOT_OPENAI_API_KEY no está configurada en .env');
            $this->line('');
            $this->comment('Revisa el archivo .env y asegúrate que esta línea no esté comentada:');
            $this->comment('  CHATBOT_OPENAI_API_KEY=sk-...');
            return 1;
        }
        $this->line('✓ API key detectada: ' . substr($apiKey, 0, 10) . '...' . substr($apiKey, -4));

        // 2. Validar provider configurado
        $provider = config('marketing.chatbot.mkt03.image_provider', 'dalle3');
        $model    = config('marketing.chatbot.mkt03.image_model', 'dall-e-3');
        $defaultQ = config('marketing.chatbot.mkt03.image_quality', 'medium');
        $disk     = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $path     = config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');
        $this->line("✓ Provider: {$provider} ({$model})");
        $this->line("✓ Storage: disk={$disk}, path={$path}");
        $this->line('');

        // 3. Construir prompt (custom o default)
        $prompt = $this->option('prompt');
        if (empty($prompt)) {
            $prompt = 'A modern, minimalist flat illustration for a B2B corporate gift company. '
                   . 'Shows stylized eco-friendly notebooks and pens on a clean desk. '
                   . 'Soft pastel colors, no text. Professional and elegant.';
        }
        $aspectRatio = $this->option('aspect-ratio') ?: '1:1';

        // 4. Resolver quality según provider activo y override
        $qualityOverride = $this->option('quality');
        if (!empty($qualityOverride)) {
            $quality = $qualityOverride;
        } else {
            // Default por provider:
            //   - dalle3: 'standard' (alternativa: 'hd')
            //   - gpt-image-2: lo que dice config (default 'medium')
            $quality = ($provider === 'dalle3') ? 'standard' : $defaultQ;
        }

        // 4.b — ¿Hay imágenes de referencia? → modo edit
        $references = $this->option('reference') ?: [];
        $maskPath   = $this->option('mask');

        $isEditMode = !empty($references);

        if ($isEditMode && $provider !== 'gpt-image-2') {
            $this->error('✗ El provider activo (' . $provider . ') no soporta imágenes de referencia.');
            $this->comment('   Cambia MKT03_IMAGE_PROVIDER=gpt-image-2 en .env y vuelve a probar.');
            return 1;
        }

        // Validar archivos de referencia antes de cualquier llamada
        if ($isEditMode) {
            foreach ($references as $idx => $refPath) {
                if (!file_exists($refPath)) {
                    $this->error("✗ Imagen de referencia #{$idx} no existe: {$refPath}");
                    $this->comment('   Tip: usa la ruta dentro del container fpm. Ej: /var/www/html/storage/app/public/uploads/foo.jpg');
                    return 1;
                }
            }
            if ($maskPath && !file_exists($maskPath)) {
                $this->error("✗ Máscara no existe: {$maskPath}");
                return 1;
            }
        }

        // Estimar costo aproximado para mostrarlo en la confirmación
        $costEstimate = $this->estimateImageCost($provider, $quality, $aspectRatio);

        $this->comment('Configuración de la prueba:');
        $this->line('  Modo:         ' . ($isEditMode ? 'EDIT (con referencias)' : 'GENERATE (text → image)'));
        $this->line('  Prompt:       ' . substr($prompt, 0, 120) . (strlen($prompt) > 120 ? '...' : ''));
        $this->line("  aspect_ratio: {$aspectRatio}");
        $this->line("  quality:      {$quality}");
        if ($isEditMode) {
            $this->line('  References:   ' . count($references) . ' imagen(es)');
            foreach ($references as $idx => $refPath) {
                $sizeKb = round(filesize($refPath) / 1024);
                $this->line('    [' . ($idx + 1) . '] ' . basename($refPath) . " ({$sizeKb} KB)");
            }
            if ($maskPath) {
                $this->line('  Mask:         ' . basename($maskPath));
            }
        }
        $this->line('');

        if (!$this->confirm("¿Proceder? (consumirá ~\${$costEstimate} de crédito OpenAI)", true)) {
            $this->comment('Cancelado.');
            return 0;
        }

        // 5. Llamar al servicio
        $this->line('');
        $this->line('Generando imagen... (puede tardar hasta 2 minutos en gpt-image-2)');

        // Construir options según provider — gpt-image-2 NO soporta 'style'
        $options = [
            'aspect_ratio' => $aspectRatio,
            'quality'      => $quality,
        ];
        if ($provider === 'dalle3') {
            $options['style'] = 'vivid';
        }
        if ($maskPath) {
            $options['mask_path'] = $maskPath;
        }

        $startTime = microtime(true);
        try {
            $imageService = app(ImageGenerationService::class);
            if ($isEditMode) {
                $result = $imageService->edit($prompt, $references, $options);
            } else {
                $result = $imageService->generate($prompt, $options);
            }
        } catch (\Exception $e) {
            $this->error('✗ Excepción inesperada: ' . $e->getMessage());
            return 1;
        }
        $duration = round(microtime(true) - $startTime, 2);

        $this->line('');
        $this->info("Respuesta en {$duration}s:");
        $this->line('');

        // 5. Analizar resultado
        if (!empty($result['success'])) {
            $this->info('✓ IMAGEN GENERADA CORRECTAMENTE');
            $this->line('');
            $this->line('  URL pública:      ' . (isset($result['url']) ? $result['url'] : 'N/A'));
            $this->line('  Storage path:     ' . (isset($result['storage_path']) ? $result['storage_path'] : 'N/A'));
            $this->line('  Provider:         ' . (isset($result['provider']) ? $result['provider'] : 'N/A'));
            $this->line('  Model:            ' . (isset($result['model']) ? $result['model'] : 'N/A'));
            if (!empty($result['revised_prompt'])) {
                $this->line('  Revised prompt:   ' . substr($result['revised_prompt'], 0, 80) . '...');
            }
            $this->line('');
            $this->comment('Abre la URL pública en tu navegador para verificar que se guardó correctamente.');
            return 0;
        }

        $this->error('✗ FALLÓ LA GENERACIÓN');
        $this->line('  Provider: ' . (isset($result['provider']) ? $result['provider'] : 'N/A'));
        $this->line('  Error:    ' . (isset($result['error']) ? $result['error'] : 'desconocido'));
        $this->line('');
        $this->comment('Causas comunes:');
        $this->comment('  • API key inválida → revisa en platform.openai.com');
        $this->comment('  • Sin saldo → recarga en platform.openai.com/settings/billing');
        $this->comment('  • Rate limit → espera 1 min y reintenta');
        $this->comment('  • Content policy violation → cambia el prompt');
        return 1;
    }

    /**
     * Mostrar un resumen del estado del agente MKT-03.
     */
    private function cmdStatus()
    {
        $this->info('═══════════════════════════════════════════════');
        $this->info('  MKT-03 — Estado del agente Social Media');
        $this->info('═══════════════════════════════════════════════');
        $this->line('');

        // Grillas
        $grids = [
            'Total'        => Grid::count(),
            'Borrador'     => Grid::where('estado', Grid::ESTADO_BORRADOR)->count(),
            'Validadas'    => Grid::where('estado', Grid::ESTADO_VALIDADA)->count(),
            'Activas'      => Grid::where('estado', Grid::ESTADO_ACTIVA)->count(),
            'Cerradas'     => Grid::where('estado', Grid::ESTADO_CERRADA)->count(),
        ];
        $this->line('<comment>Grillas:</comment>');
        foreach ($grids as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        $this->line('');

        // Piezas
        $pieces = [
            'Total'          => Piece::count(),
            'Pendientes'     => Piece::where('estado_pieza', Piece::ESTADO_PENDIENTE)->count(),
            'En producción'  => Piece::where('estado_pieza', Piece::ESTADO_EN_PRODUCCION)->count(),
            'Listas'         => Piece::where('estado_pieza', Piece::ESTADO_LISTA_PUBLICAR)->count(),
            'Publicadas'     => Piece::where('estado_pieza', Piece::ESTADO_PUBLICADA)->count(),
            'Observadas'     => Piece::where('estado_pieza', Piece::ESTADO_OBSERVADA)->count(),
        ];
        $this->line('<comment>Piezas:</comment>');
        foreach ($pieces as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        $this->line('');

        // Ferias
        $ferias = [
            'Total'         => FeriaReceived::count(),
            'Activas'       => FeriaReceived::where('estado', FeriaReceived::ESTADO_ACTIVA)->count(),
            'En cobertura'  => FeriaReceived::where('estado', FeriaReceived::ESTADO_EN_COBERTURA)->count(),
            'Concluidas'    => FeriaReceived::where('estado', FeriaReceived::ESTADO_CONCLUIDA)->count(),
        ];
        $this->line('<comment>Ferias:</comment>');
        foreach ($ferias as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        $this->line('');

        // Patrones
        $patterns = [
            'Total activos'         => LearningPattern::where('active', true)->count(),
            'Aprobados por usuario' => LearningPattern::where('aprobado_por_usuario', true)->count(),
            'De histórico'          => LearningPattern::where('fuente', LearningPattern::FUENTE_HISTORICO)->count(),
            'Confianza >= 80%'      => LearningPattern::where('confianza', '>=', 80)->count(),
        ];
        $this->line('<comment>Patrones aprendidos:</comment>');
        foreach ($patterns as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        $this->line('');

        // Históricos
        $histCount = HistoricalGrid::count();
        $histAnalyzed = HistoricalGrid::where('estado', HistoricalGrid::ESTADO_ANALIZADO)->count();
        $this->line('<comment>Grillas históricas importadas:</comment>');
        $this->line("  Total: {$histCount}");
        $this->line("  Analizadas: {$histAnalyzed}");
        $this->line('');

        $this->info('═══════════════════════════════════════════════');
        return 0;
    }

    /**
     * Recalcular fase_editorial_actual de todas las ferias activas.
     */
    private function cmdUpdateFeriaFases()
    {
        $this->info('Recalculando fases editoriales de ferias...');

        $ferias = FeriaReceived::whereIn('estado', [
            FeriaReceived::ESTADO_ACTIVA,
            FeriaReceived::ESTADO_EN_COBERTURA,
        ])->get();

        $updated = 0;
        foreach ($ferias as $feria) {
            $newFase = method_exists($feria, 'calcularFaseActual')
                ? $feria->calcularFaseActual()
                : $feria->fase_editorial_actual;

            if ($newFase !== $feria->fase_editorial_actual) {
                $feria->update(['fase_editorial_actual' => $newFase]);
                $this->line("  #{$feria->id} {$feria->nombre}: fase -> {$newFase}");
                $updated++;
            }
        }

        $this->info("Ferias actualizadas: {$updated}");
        return 0;
    }

    /**
     * Detectar grillas cuya cobertura está por agotarse (Modo C — §11.5 doc funcional).
     *
     * Sin --auto: lista candidatas con su tramo sugerido (dry-run, no escribe).
     * Con --auto: ejecuta la extensión de cada grilla candidata respetando las
     * 5 sub-reglas del §11.5 (proactividad, expansión progresiva por trimestre,
     * orden FB/IG → LinkedIn → TikTok, criterio cuánto ampliar, no contradicción
     * con configuración antigua).
     */
    private function cmdContinuityCheck()
    {
        $orchestrator = app(\Modules\Marketing\Services\MKT03Orchestrator::class);
        $continuity   = app(\Modules\Marketing\Services\GridContinuityService::class);

        $umbralDias = $this->option('dias') !== null ? (int) $this->option('dias') : null;
        $auto       = (bool) $this->option('auto');

        $this->info(sprintf(
            'Revisando grillas por agotarse (umbral: %s días, modo: %s)...',
            $umbralDias !== null ? $umbralDias : \Modules\Marketing\Services\GridContinuityService::UMBRAL_DIAS_DEFAULT,
            $auto ? 'AUTO (ejecutará extensión)' : 'dry-run (solo listar)'
        ));

        $candidates = $continuity->detectCandidates($umbralDias);

        if (empty($candidates)) {
            $this->info('  ✓ No hay grillas que necesiten continuidad.');
            return 0;
        }

        $this->warn('Grillas candidatas (orden §11.5.3 — FB → IG → LinkedIn → TikTok):');
        foreach ($candidates as $c) {
            $g = $c['grid'];
            $estado    = $c['agotada'] ? 'AGOTADA' : 'por agotarse';
            $tramo     = $c['tramo_sugerido'];
            $tramoStr  = $tramo['fecha_desde'] . ' → ' . $tramo['fecha_hasta'] . ' (' . $tramo['tipo'] . ')';
            $this->line(sprintf(
                '  Grid #%d [%s] %s — %s — última: %s — tramo sugerido: %s',
                $g->id,
                $g->red_social,
                $g->titulo,
                $estado,
                $c['ultima_fecha'] ?: '(sin items)',
                $tramoStr
            ));
        }

        $this->line('');

        if (!$auto) {
            $this->comment('Sin flag --auto este comando solo lista candidatas.');
            $this->comment('Para extender automáticamente: php artisan marketing:mkt03 continuity-check --auto --tenant=' . ($this->option('tenant') ?: 'X'));
            return 0;
        }

        $this->info('Ejecutando extensión automática (Modo C)...');
        $result = $orchestrator->extendAllNeedingContinuity($umbralDias, false);

        $this->info('═══════════════════════════════════════════════');
        $this->info(sprintf('  Procesadas %d / %d candidatas', $result['processed'], $result['total_candidates']));
        $this->info('═══════════════════════════════════════════════');

        $exitCode = 0;
        foreach ($result['results'] as $r) {
            if (isset($r['error'])) {
                $this->error(sprintf('  ✗ Grid #%d [%s]: ERROR — %s', $r['grid_id'], $r['red'], $r['error']));
                $exitCode = 1;
            } else {
                $items = isset($r['items_added']) ? $r['items_added'] : 0;
                $this->info(sprintf(
                    '  ✓ Grid #%d [%s]: +%d ítems (%s)',
                    $r['grid_id'],
                    $r['red'],
                    $items,
                    $r['razon']
                ));
            }
        }

        return $exitCode;
    }

    /**
     * Skeleton: programar automáticamente piezas en estado "lista_publicar".
     */
    private function cmdAutoSchedule()
    {
        $count = Piece::where('estado_pieza', Piece::ESTADO_LISTA_PUBLICAR)->count();

        $this->info('Auto-schedule (skeleton Etapa 2):');
        $this->line("  Piezas listas para publicar: {$count}");
        $this->line('');
        $this->comment('Este comando esta preparado para Etapa 2.');
        $this->comment('Cuando se implemente, programara cada pieza en mkt_scheduled_publications');
        $this->comment('y un scheduler publicara en la red correspondiente via OAuth.');
        $this->line('');
        $this->line('Requerimientos para Etapa 2:');
        $this->line('  1. Cuentas Business en Meta (FB+IG), LinkedIn y TikTok');
        $this->line('  2. OAuth tokens almacenados en mkt_social_credentials');
        $this->line('  3. Jobs de Laravel registrados y ejecutandose');
        $this->line('  4. Politica de fallback ante errores de publicacion');

        return 0;
    }

    /**
     * Estimación aproximada del costo USD de una sola imagen.
     * Solo para mostrar en confirmación — los precios reales pueden variar.
     */
    private function estimateImageCost($provider, $quality, $aspectRatio)
    {
        // Mapear aspect_ratio → tamaño aproximado para tabla
        $isSquare = ($aspectRatio === '1:1');

        // Tabla de pricing referencial (abr 2026, sujeto a cambios)
        if ($provider === 'dalle3') {
            // standard $0.040 / hd $0.080
            return $quality === 'hd' ? '0.080' : '0.040';
        }
        if ($provider === 'gpt-image-2') {
            // 1024×1024:    low=0.006 / medium=0.053 / high=0.211
            // 1024×1536:    low=0.005 / medium=0.041 / high=0.165
            $table = [
                'low'    => $isSquare ? '0.006' : '0.005',
                'medium' => $isSquare ? '0.053' : '0.041',
                'high'   => $isSquare ? '0.211' : '0.165',
                'auto'   => $isSquare ? '0.053' : '0.041', // estimación: como medium
            ];
            return isset($table[$quality]) ? $table[$quality] : '0.05';
        }
        return '0.05';
    }
}

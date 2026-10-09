<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Services\ExcelHistoricalImporter;
use Modules\Marketing\Services\GridPatternAnalyzer;

/**
 * Mkt03ImportHistoricalCommand — MKT-03 M1
 *
 * Importa los planners editoriales históricos (Excel) de COMPIPRO,
 * extrae el contenido normalizado y opcionalmente analiza los patrones
 * editoriales con IA para sembrar `mkt_learning_patterns`.
 *
 * Convención de paths:
 *   storage/app/mkt03/historical/planner_{anio}.xlsx
 *
 * Uso:
 *   # Importar y analizar 1 año específico
 *   php artisan marketing:mkt03-import-historical --tenant=1 --year=2024 --analyze
 *
 *   # Importar y analizar los 3 años (2022, 2023, 2024)
 *   php artisan marketing:mkt03-import-historical --tenant=1 --analyze
 *
 *   # Importar 1 archivo desde una ruta custom
 *   php artisan marketing:mkt03-import-historical --tenant=1 --year=2024 --path=/abs/path/file.xlsx
 *
 *   # Solo importar (sin gastar tokens de IA)
 *   php artisan marketing:mkt03-import-historical --tenant=1 --year=2024
 *
 *   # Forzar re-import incluso si ya existe HistoricalGrid para ese año
 *   php artisan marketing:mkt03-import-historical --tenant=1 --year=2024 --force
 *
 * El parsing usa hasta 2 GB de RAM (ya gestionado por ExcelHistoricalImporter).
 * Para el planner de 2023 (~145 MB) el comando tarda varios minutos.
 */
class Mkt03ImportHistoricalCommand extends Command
{
    protected $signature = 'marketing:mkt03-import-historical
                            {--tenant=1 : ID del tenant (Website)}
                            {--year= : Año a importar (2022, 2023, 2024). Sin esto procesa los 3.}
                            {--path= : Ruta custom al archivo .xlsx (requiere --year)}
                            {--analyze : Correr el GridPatternAnalyzer después de importar (gasta tokens IA)}
                            {--force : Re-importar aunque ya exista un HistoricalGrid para ese año}';

    protected $description = 'Importa planners históricos COMPIPRO (Excel) y opcionalmente extrae patrones con IA — MKT-03 M1';

    /** Años cubiertos por defecto (cuando no se pasa --year) */
    const DEFAULT_YEARS = [2022, 2023, 2024];

    /** @var ExcelHistoricalImporter */
    private $importer;

    /** @var GridPatternAnalyzer */
    private $analyzer;

    public function handle(ExcelHistoricalImporter $importer, GridPatternAnalyzer $analyzer)
    {
        $this->importer = $importer;
        $this->analyzer = $analyzer;

        // Resolver tenant
        $tenantId = (int) $this->option('tenant');
        $website  = Website::find($tenantId);
        if (!$website) {
            $this->error("Tenant {$tenantId} no encontrado.");
            return 1;
        }
        app(Environment::class)->tenant($website);
        $this->line(">>> Tenant: {$website->uuid} (ID: {$tenantId})");

        // Resolver años a procesar
        $year = $this->option('year');
        $years = $year !== null ? [(int) $year] : self::DEFAULT_YEARS;

        // --path solo es válido con un único año
        $customPath = $this->option('path');
        if ($customPath !== null && count($years) !== 1) {
            $this->error('La opción --path requiere también --year (un solo año).');
            return 1;
        }

        $analyze = (bool) $this->option('analyze');
        $force   = (bool) $this->option('force');

        $this->info('═══════════════════════════════════════════════');
        $this->info('  MKT-03 M1 — Import histórico');
        $this->info('  Años: ' . implode(', ', $years));
        $this->info('  Analizar con IA: ' . ($analyze ? 'sí' : 'no'));
        $this->info('═══════════════════════════════════════════════');
        $this->line('');

        $totalSummary = [
            'imported'        => 0,
            'analyzed'        => 0,
            'patterns_total'  => 0,
            'errors'          => 0,
            'skipped'         => 0,
        ];

        foreach ($years as $anio) {
            $result = $this->processYear($anio, $customPath, $analyze, $force);

            if (!empty($result['skipped'])) {
                $totalSummary['skipped']++;
                continue;
            }
            if (!empty($result['error'])) {
                $totalSummary['errors']++;
                continue;
            }

            $totalSummary['imported']++;
            if (!empty($result['analyzed'])) {
                $totalSummary['analyzed']++;
                $totalSummary['patterns_total'] += isset($result['patterns_saved']) ? (int) $result['patterns_saved'] : 0;
            }
        }

        $this->line('');
        $this->info('═══════════════════════════════════════════════');
        $this->info('  Resumen');
        $this->info('═══════════════════════════════════════════════');
        $this->line("  Importados:        {$totalSummary['imported']}");
        $this->line("  Analizados con IA: {$totalSummary['analyzed']}");
        $this->line("  Patrones nuevos:   {$totalSummary['patterns_total']}");
        $this->line("  Saltados:          {$totalSummary['skipped']}");
        $this->line("  Errores:           {$totalSummary['errors']}");

        return $totalSummary['errors'] > 0 ? 1 : 0;
    }

    /**
     * Procesar un año concreto.
     *
     * @param  int          $anio
     * @param  string|null  $customPath
     * @param  bool         $analyze
     * @param  bool         $force
     * @return array
     */
    private function processYear($anio, $customPath, $analyze, $force)
    {
        $this->line("─── Año {$anio} ───");

        // 1) Verificar si ya existe un HistoricalGrid analizado para este año
        $existing = HistoricalGrid::where('anio', $anio)->orderByDesc('id')->first();
        if ($existing && !$force) {
            $this->warn("  Ya existe HistoricalGrid #{$existing->id} para {$anio} (estado: {$existing->estado}). Usá --force para re-importar.");
            return ['skipped' => true];
        }

        // 2) Resolver path del archivo
        $sourcePath = $customPath ?: $this->defaultPathForYear($anio);
        if (!file_exists($sourcePath)) {
            $this->error("  Archivo no encontrado: {$sourcePath}");
            return ['error' => 'archivo_no_encontrado'];
        }
        $sizeMb = round(filesize($sourcePath) / (1024 * 1024), 1);
        $this->line("  Archivo: {$sourcePath} ({$sizeMb} MB)");

        // 3) Copiar al storage del tenant si todavía no está ahí
        $disk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $relativeStoragePath = 'mkt03/historical/planner_' . $anio . '.xlsx';
        try {
            $storageFullPath = Storage::disk($disk)->path($relativeStoragePath);
        } catch (\Exception $e) {
            $this->error('  Disk no accesible: ' . $e->getMessage());
            return ['error' => 'disk_inaccesible'];
        }

        if (realpath($sourcePath) !== realpath($storageFullPath)) {
            // Copiar al storage (chunked para archivos grandes)
            $this->line("  Copiando al storage del tenant…");
            $this->copyFileChunked($sourcePath, $storageFullPath);
        }

        // 4) Crear el HistoricalGrid
        $hg = HistoricalGrid::create([
            'anio'                 => $anio,
            'nombre'               => "Planner COMPIPRO {$anio}",
            'archivo_original'     => basename($sourcePath),
            'archivo_storage_path' => $relativeStoragePath,
            'estado'               => HistoricalGrid::ESTADO_IMPORTADO,
            'user_id'              => null,
        ]);
        $this->line("  HistoricalGrid #{$hg->id} creado.");

        // 5) Importar (parsear Excel → contenido_normalizado)
        $this->line('  Parseando Excel… (puede tardar varios minutos para archivos grandes)');
        $startTime = microtime(true);
        $importResult = $this->importer->import($hg);
        $elapsed = round(microtime(true) - $startTime, 1);

        if (!$importResult['success']) {
            $this->error('  Import falló: ' . $importResult['error']);
            return ['error' => 'import_fallido'];
        }

        $this->info("  ✓ Import OK: {$importResult['total_items']} ítems en {$elapsed}s");
        $this->line('  Redes: ' . implode(', ', $importResult['redes']));
        if (!empty($importResult['sheets'])) {
            foreach ($importResult['sheets'] as $sheet => $count) {
                $this->line("    - {$sheet}: {$count} ítems");
            }
        }

        // 6) Analizar con IA si se pidió
        $analyzed = false;
        $patternsSaved = 0;
        if ($analyze) {
            $this->line('  Analizando patrones con IA…');
            $startAnalyze = microtime(true);
            try {
                $analysis = $this->analyzer->analyzeHistoricalGrid($hg->fresh());
                if (!empty($analysis['_fallback'])) {
                    $this->warn('  Análisis IA en fallback: ' . ($analysis['resumen_analisis'] ?? 'sin detalle'));
                } else {
                    $patternsSaved = $this->analyzer->savePatterns($analysis, $hg->fresh());
                    $hg->update(['estado' => HistoricalGrid::ESTADO_ANALIZADO]);
                    $analyzed = true;
                    $elapsedAnalyze = round(microtime(true) - $startAnalyze, 1);
                    $this->info("  ✓ Análisis OK: {$patternsSaved} patrones nuevos guardados en {$elapsedAnalyze}s");

                    // Mostrar muestra de patrones detectados
                    if ($patternsSaved > 0) {
                        $sample = LearningPattern::where('historical_grid_id', $hg->id)
                            ->orderByDesc('confianza')
                            ->limit(5)
                            ->get();
                        $this->line('  Top 5 patrones por confianza:');
                        foreach ($sample as $p) {
                            $red = $p->red_social ?: 'todas';
                            $titulo = mb_strimwidth((string) $p->titulo, 0, 70, '…');
                            $this->line("    [{$p->tipo_patron} | {$red} | conf {$p->confianza}] {$titulo}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error('  Análisis IA falló: ' . $e->getMessage());
                return [
                    'imported'   => true,
                    'analyzed'   => false,
                    'error_analyze' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported'        => true,
            'analyzed'        => $analyzed,
            'patterns_saved'  => $patternsSaved,
            'historical_grid_id' => $hg->id,
            'total_items'     => $importResult['total_items'],
        ];
    }

    /**
     * Path convencional del archivo según el año.
     *
     * @param  int  $anio
     * @return string
     */
    private function defaultPathForYear($anio)
    {
        return storage_path("app/mkt03/historical/planner_{$anio}.xlsx");
    }

    /**
     * Copiar un archivo con buffer (8MB) para no cargar todo en RAM si pesa cientos de MB.
     *
     * @param  string  $sourcePath
     * @param  string  $destPath
     * @return void
     */
    private function copyFileChunked($sourcePath, $destPath)
    {
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $bufferSize = 8 * 1024 * 1024; // 8 MB
        $in  = fopen($sourcePath, 'rb');
        $out = fopen($destPath, 'wb');
        if (!$in || !$out) {
            throw new \RuntimeException("No se pudo abrir el archivo para copiar: {$sourcePath} → {$destPath}");
        }
        try {
            while (!feof($in)) {
                $chunk = fread($in, $bufferSize);
                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }
}

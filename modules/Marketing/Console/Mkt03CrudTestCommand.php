<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Models\PlatformConfig;

/**
 * Test CRUD completo de MKT-03 — los 10 casos funcionales (sin IA).
 *
 * Uso:
 *   php artisan marketing:mkt03-test --tenant=1
 */
class Mkt03CrudTestCommand extends Command
{
    protected $signature = 'marketing:mkt03-test {--tenant=1 : Website ID}';

    protected $description = 'Ejecuta los 10 casos de test CRUD del agente MKT-03 (sin IA)';

    private $results = [];

    public function handle()
    {
        $tenantId = (int) $this->option('tenant');
        $website = Website::find($tenantId);
        if (!$website) {
            $this->error("Tenant {$tenantId} no encontrado.");
            return 1;
        }

        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        $this->info('═══════════════════════════════════════════════');
        $this->info('  MKT-03 — Test Suite CRUD (10 casos)');
        $this->info('  Tenant: ' . $website->uuid);
        $this->info('═══════════════════════════════════════════════');
        $this->line('');

        $testGridId = null;
        $testPieceId = null;
        $testExtraId = null;
        $testFeriaId = null;

        // ── Caso 1: Listar configuración de plataformas ──
        try {
            $configs = PlatformConfig::where('active', true)->get();
            $this->ok('Caso 1: Listar PlatformConfig activas',
                $configs->count() === 4,
                "{$configs->count()}/4 redes (fb/ig/li/tt)");
        } catch (\Exception $e) {
            $this->ok('Caso 1: Listar PlatformConfig', false, $e->getMessage());
        }

        // ── Caso 2: Actualizar configuración de plataforma ──
        try {
            $cfg = PlatformConfig::where('red_social', 'linkedin')->first();
            if ($cfg) {
                $original = $cfg->max_copy_length;
                $cfg->update(['max_copy_length' => 2500]);
                $fresh = $cfg->fresh();
                $updated = $fresh->max_copy_length == 2500;
                $cfg->update(['max_copy_length' => $original]);
                $this->ok('Caso 2: Actualizar PlatformConfig', $updated,
                    "max_copy_length actualizado y restaurado");
            } else {
                $this->ok('Caso 2: Actualizar PlatformConfig', false, "LinkedIn no existe");
            }
        } catch (\Exception $e) {
            $this->ok('Caso 2: Actualizar PlatformConfig', false, $e->getMessage());
        }

        // ── Caso 3: Crear Grid ──
        try {
            $grid = Grid::create([
                'anio'         => (int) date('Y'),
                'periodo'      => date('Y-m'),
                'tipo_periodo' => 'mensual',
                'red_social'   => 'instagram',
                'version'      => 1,
                'estado'       => Grid::ESTADO_BORRADOR,
                'titulo'       => 'Grid Test CRUD ' . date('YmdHis'),
                'descripcion'  => 'Grilla generada por test automatico',
            ]);
            $this->ok('Caso 3: Crear Grid',
                $grid->exists && $grid->id > 0,
                "Grid ID {$grid->id}");
            $testGridId = $grid->id;
        } catch (\Exception $e) {
            $this->ok('Caso 3: Crear Grid', false, $e->getMessage());
        }

        // ── Caso 4: Crear GridItems ──
        try {
            if ($testGridId) {
                for ($i = 1; $i <= 3; $i++) {
                    GridItem::create([
                        'grid_id'            => $testGridId,
                        'fecha_publicacion'  => date('Y-m-d', strtotime("+{$i} days")),
                        'red_social'         => 'instagram',
                        'tematica'           => 'producto',
                        'campania'           => 'catalogo',
                        'tipo_contenido'     => 'imagen',
                        'prioridad'          => 'media',
                        'origen'             => 'grilla',
                        'item_order'         => $i,
                        'observaciones'      => "Item test #{$i}",
                    ]);
                }
                $count = GridItem::where('grid_id', $testGridId)->count();
                $this->ok('Caso 4: Crear GridItems', $count === 3, "{$count}/3 items creados");
            } else {
                $this->ok('Caso 4: Crear GridItems', false, "Grid previo fallo");
            }
        } catch (\Exception $e) {
            $this->ok('Caso 4: Crear GridItems', false, $e->getMessage());
        }

        // ── Caso 5: Crear Piece ──
        try {
            if ($testGridId) {
                $firstItem = GridItem::where('grid_id', $testGridId)->first();
                if ($firstItem) {
                    $piece = Piece::create([
                        'grid_item_id'  => $firstItem->id,
                        'red_social'    => 'instagram',
                        'copy'          => 'Copy test generado por test automatico',
                        'cta'           => 'Escribenos hoy',
                        'hashtags'      => '#test #mkt03',
                        'formato'       => 'imagen',
                        'enfoque_visual'=> 'Minimalista, paleta corporativa',
                        'imagen_prompt' => 'Imagen de prueba estilo minimalista',
                        'imagen_source' => 'ai_generated',
                        'estado_pieza'  => Piece::ESTADO_PENDIENTE,
                        'version'       => 1,
                        'is_current'    => true,
                    ]);
                    $this->ok('Caso 5: Crear Piece', $piece->exists, "Piece ID {$piece->id}");
                    $testPieceId = $piece->id;
                } else {
                    $this->ok('Caso 5: Crear Piece', false, "Sin GridItem base");
                }
            } else {
                $this->ok('Caso 5: Crear Piece', false, "Grid previo fallo");
            }
        } catch (\Exception $e) {
            $this->ok('Caso 5: Crear Piece', false, $e->getMessage());
        }

        // ── Caso 6: ExtraordinaryContent ──
        try {
            $extra = ExtraordinaryContent::create([
                'fecha'           => date('Y-m-d', strtotime('+7 days')),
                'motivo'          => 'Lanzamiento producto nuevo',
                'red_social'      => 'instagram',
                'descripcion'     => 'Descripcion del lanzamiento',
                'clasificacion'   => 'anuncio',
                'estado'          => ExtraordinaryContent::ESTADO_SOLICITADO,
            ]);
            $this->ok('Caso 6: Crear ExtraordinaryContent', $extra->exists, "Extra ID {$extra->id}");
            $testExtraId = $extra->id;
        } catch (\Exception $e) {
            $this->ok('Caso 6: Crear ExtraordinaryContent', false, $e->getMessage());
        }

        // ── Caso 7: FeriaReceived ──
        try {
            $feria = FeriaReceived::create([
                'nombre'                  => 'Expo Test ' . date('Y'),
                'fecha'                   => date('Y-m-d', strtotime('+20 days')),
                'sector'                  => 'Industrial',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'expositor',
                'objetivo_comunicacional' => 'Generar leads B2B',
                'enfoque_sugerido'        => 'Expectativa e invitacion',
                'source'                  => 'manual',
                'estado'                  => FeriaReceived::ESTADO_ACTIVA,
            ]);
            $this->ok('Caso 7: Crear FeriaReceived', $feria->exists, "Feria ID {$feria->id}");
            $testFeriaId = $feria->id;
        } catch (\Exception $e) {
            $this->ok('Caso 7: Crear FeriaReceived', false, $e->getMessage());
        }

        // ── Caso 8: Filtrar LearningPatterns ──
        try {
            $active = LearningPattern::where('active', true)->count();
            $highConf = LearningPattern::where('confianza', '>=', 80)->count();
            $this->ok('Caso 8: Filtrar LearningPatterns',
                $active > 0 && $highConf > 0,
                "activos:{$active}, >=80%:{$highConf}");
        } catch (\Exception $e) {
            $this->ok('Caso 8: Filtrar LearningPatterns', false, $e->getMessage());
        }

        // ── Caso 9: Aprobar LearningPattern ──
        try {
            $pattern = LearningPattern::first();
            if ($pattern) {
                $original = $pattern->aprobado_por_usuario;
                $pattern->update(['aprobado_por_usuario' => true]);
                $fresh = $pattern->fresh();
                $updated = $fresh->aprobado_por_usuario == true;
                $pattern->update(['aprobado_por_usuario' => $original]);
                $this->ok('Caso 9: Aprobar LearningPattern', $updated,
                    "Pattern #{$pattern->id} aprobado/restaurado");
            } else {
                $this->ok('Caso 9: Aprobar LearningPattern', false, "Sin patrones");
            }
        } catch (\Exception $e) {
            $this->ok('Caso 9: Aprobar LearningPattern', false, $e->getMessage());
        }

        // ── Caso 10: Cleanup ──
        try {
            $deleted = 0;
            if ($testExtraId) { ExtraordinaryContent::destroy($testExtraId); $deleted++; }
            if ($testFeriaId) { FeriaReceived::destroy($testFeriaId); $deleted++; }
            if ($testPieceId) { Piece::destroy($testPieceId); $deleted++; }
            if ($testGridId) {
                GridItem::where('grid_id', $testGridId)->delete();
                Grid::destroy($testGridId);
                $deleted++;
            }
            $this->ok('Caso 10: Cleanup', $deleted > 0, "{$deleted} recursos eliminados");
        } catch (\Exception $e) {
            $this->ok('Caso 10: Cleanup', false, $e->getMessage());
        }

        // ── Resumen ──
        $this->line('');
        $this->info('═══════════════════════════════════════════════');
        $total = count($this->results);
        $passed = count(array_filter($this->results, function ($r) { return $r['ok']; }));
        $failed = $total - $passed;

        if ($failed === 0) {
            $this->info("  RESULTADO: {$passed}/{$total} tests OK");
        } else {
            $this->warn("  RESULTADO: {$passed} OK | {$failed} FAIL (de {$total})");
            $this->line('');
            $this->error('Fallos:');
            foreach ($this->results as $r) {
                if (!$r['ok']) $this->line("  - {$r['test']}: {$r['msg']}");
            }
        }
        $this->info('═══════════════════════════════════════════════');

        return $failed === 0 ? 0 : 1;
    }

    private function ok($test, $status, $msg = '')
    {
        $this->results[] = ['test' => $test, 'ok' => $status, 'msg' => $msg];
        if ($status) {
            $this->line("<info>[OK]</info>   {$test}" . ($msg ? " - {$msg}" : ''));
        } else {
            $this->line("<error>[FAIL]</error> {$test}" . ($msg ? " - {$msg}" : ''));
        }
    }
}

<?php
/**
 * Test CRUD de MKT-03 — Los 10 casos funcionales.
 *
 * Ejecuta con:
 *   docker exec compipro-erp-fpm-1 php artisan tinker --execute="require '/var/www/html/modules/Marketing/Tests/mkt03_crud_test.php';"
 */

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Models\PlatformConfig;

// Fijar tenant
$website = Website::find(1);
$tenancy = app(Environment::class);
$tenancy->tenant($website);

$results = [];

function ok($test, $status, $msg = '') {
    global $results;
    $icon = $status ? '[OK]' : '[FAIL]';
    $line = "{$icon} {$test}" . ($msg ? " — {$msg}" : '');
    echo $line . "\n";
    $results[] = ['test' => $test, 'ok' => $status, 'msg' => $msg];
}

echo "\n=== MKT-03 — Test suite (10 casos funcionales) ===\n\n";

// ── Caso 1: Listar configuración de plataformas ──
try {
    $configs = PlatformConfig::where('active', true)->get();
    ok('Caso 1: Listar PlatformConfig activas', $configs->count() === 4,
       "Encontradas: {$configs->count()} redes (esperado 4: fb/ig/li/tt)");
} catch (\Exception $e) {
    ok('Caso 1: Listar PlatformConfig activas', false, $e->getMessage());
}

// ── Caso 2: Actualizar configuración de plataforma ──
try {
    $cfg = PlatformConfig::where('red_social', 'linkedin')->first();
    if ($cfg) {
        $original = $cfg->max_copy_length;
        $cfg->update(['max_copy_length' => 2500]);
        $fresh = $cfg->fresh();
        $updated = $fresh->max_copy_length == 2500;
        // Restaurar
        $cfg->update(['max_copy_length' => $original]);
        ok('Caso 2: Actualizar PlatformConfig', $updated, "max_copy_length actualizado correctamente");
    } else {
        ok('Caso 2: Actualizar PlatformConfig', false, "LinkedIn no existe");
    }
} catch (\Exception $e) {
    ok('Caso 2: Actualizar PlatformConfig', false, $e->getMessage());
}

// ── Caso 3: Crear un Grid (Modo A manual) ──
try {
    $grid = Grid::create([
        'red_social'      => 'instagram',
        'modo_origen'     => 'A',
        'titulo'          => 'Grid Test CRUD ' . date('YmdHis'),
        'mes_inicio'      => date('Y-m-01'),
        'mes_fin'         => date('Y-m-t'),
        'frecuencia'      => 'diaria',
        'estado'          => Grid::ESTADO_BORRADOR,
        'contexto_usado'  => json_encode(['test' => true]),
    ]);
    ok('Caso 3: Crear Grid (Modo A manual)', $grid->exists && $grid->id > 0,
       "Grid ID {$grid->id} creado");
    $testGridId = $grid->id;
} catch (\Exception $e) {
    ok('Caso 3: Crear Grid', false, $e->getMessage());
    $testGridId = null;
}

// ── Caso 4: Agregar GridItems al Grid creado ──
try {
    if ($testGridId) {
        $items = [];
        for ($i = 1; $i <= 3; $i++) {
            $items[] = GridItem::create([
                'grid_id'            => $testGridId,
                'fecha_publicacion'  => date('Y-m-d', strtotime("+{$i} days")),
                'hora_publicacion'   => '10:00',
                'dia_semana'         => 'lunes',
                'tipo_contenido'     => 'producto',
                'subtipo'            => 'catalogo',
                'titulo_breve'       => "Item test #{$i}",
                'mensaje_sugerido'   => "Mensaje test para item {$i}",
                'tono_sugerido'      => 'inspirador',
                'cta_sugerido'       => 'Consulta sin compromiso',
                'hashtags_sugeridos' => json_encode(['#test', '#mkt03']),
                'order'              => $i,
                'origen_contenido'   => 'ia_generado',
            ]);
        }
        $count = GridItem::where('grid_id', $testGridId)->count();
        ok('Caso 4: Crear GridItems', $count === 3, "{$count}/3 items creados");
    } else {
        ok('Caso 4: Crear GridItems', false, "Grid previo fallo");
    }
} catch (\Exception $e) {
    ok('Caso 4: Crear GridItems', false, $e->getMessage());
}

// ── Caso 5: Crear una Piece vinculada al primer GridItem ──
try {
    if ($testGridId) {
        $firstItem = GridItem::where('grid_id', $testGridId)->first();
        if ($firstItem) {
            $piece = Piece::create([
                'grid_item_id'   => $firstItem->id,
                'estado_pieza'   => Piece::ESTADO_PENDIENTE,
                'red_social'     => 'instagram',
                'modo_produccion'=> 'D',
                'copy_final'     => 'Copy test generado',
                'hashtags_final' => json_encode(['#test']),
                'cta_final'      => 'Escríbenos hoy',
                'prompt_imagen'  => 'Imagen de prueba estilo minimalista',
            ]);
            ok('Caso 5: Crear Piece', $piece->exists, "Piece ID {$piece->id} creada");
            $testPieceId = $piece->id;
        } else {
            ok('Caso 5: Crear Piece', false, "Sin GridItem base");
            $testPieceId = null;
        }
    } else {
        ok('Caso 5: Crear Piece', false, "Grid previo fallo");
        $testPieceId = null;
    }
} catch (\Exception $e) {
    ok('Caso 5: Crear Piece', false, $e->getMessage());
    $testPieceId = null;
}

// ── Caso 6: Crear un ExtraordinaryContent ──
try {
    $extra = ExtraordinaryContent::create([
        'tipo'               => 'anuncio',
        'titulo'             => 'Lanzamiento producto nuevo',
        'descripcion'        => 'Descripcion del lanzamiento',
        'urgencia'           => 'alta',
        'fecha_evento'       => date('Y-m-d', strtotime('+7 days')),
        'redes_destino'      => json_encode(['instagram', 'facebook']),
        'incorporado_a_grid' => false,
        'estado'             => ExtraordinaryContent::ESTADO_PENDIENTE,
    ]);
    ok('Caso 6: Crear ExtraordinaryContent', $extra->exists, "Extra ID {$extra->id}");
    $testExtraId = $extra->id;
} catch (\Exception $e) {
    ok('Caso 6: Crear ExtraordinaryContent', false, $e->getMessage());
    $testExtraId = null;
}

// ── Caso 7: Crear una FeriaReceived ──
try {
    $feria = FeriaReceived::create([
        'nombre'               => 'Expo Test ' . date('Y'),
        'descripcion'          => 'Feria de prueba',
        'fecha_inicio'         => date('Y-m-d', strtotime('+20 days')),
        'fecha_fin'            => date('Y-m-d', strtotime('+25 days')),
        'ubicacion'            => 'Lima',
        'fase_editorial_actual'=> FeriaReceived::FASE_EXPECTATIVA,
        'estado'               => FeriaReceived::ESTADO_ACTIVA,
        'participacion'        => 'expositor',
        'contactos_objetivo'   => 'Empresas industriales',
    ]);
    ok('Caso 7: Crear FeriaReceived', $feria->exists, "Feria ID {$feria->id}");
    $testFeriaId = $feria->id;
} catch (\Exception $e) {
    ok('Caso 7: Crear FeriaReceived', false, $e->getMessage());
    $testFeriaId = null;
}

// ── Caso 8: Listar LearningPatterns con filtros ──
try {
    $allPatterns = LearningPattern::where('active', true)->count();
    $instPatterns = LearningPattern::where('red_social', 'instagram')->orWhereNull('red_social')->count();
    $highConfidence = LearningPattern::where('confianza', '>=', 80)->count();
    ok('Caso 8: Filtrar LearningPatterns',
       $allPatterns > 0 && $highConfidence > 0,
       "activos:{$allPatterns}, ig+globales:{$instPatterns}, >=80% conf:{$highConfidence}");
} catch (\Exception $e) {
    ok('Caso 8: Filtrar LearningPatterns', false, $e->getMessage());
}

// ── Caso 9: Aprobar/desactivar un LearningPattern ──
try {
    $pattern = LearningPattern::first();
    if ($pattern) {
        $originalApproved = $pattern->aprobado_por_usuario;
        $pattern->update(['aprobado_por_usuario' => true]);
        $fresh = $pattern->fresh();
        $updated = $fresh->aprobado_por_usuario == true;
        $pattern->update(['aprobado_por_usuario' => $originalApproved]);
        ok('Caso 9: Aprobar LearningPattern', $updated, "Pattern #{$pattern->id} aprobado/restaurado");
    } else {
        ok('Caso 9: Aprobar LearningPattern', false, "Sin patrones");
    }
} catch (\Exception $e) {
    ok('Caso 9: Aprobar LearningPattern', false, $e->getMessage());
}

// ── Caso 10: Cleanup — Borrar todos los registros de test ──
try {
    $deleted = 0;
    if (isset($testExtraId) && $testExtraId) {
        ExtraordinaryContent::destroy($testExtraId); $deleted++;
    }
    if (isset($testFeriaId) && $testFeriaId) {
        FeriaReceived::destroy($testFeriaId); $deleted++;
    }
    if (isset($testPieceId) && $testPieceId) {
        Piece::destroy($testPieceId); $deleted++;
    }
    if (isset($testGridId) && $testGridId) {
        GridItem::where('grid_id', $testGridId)->delete();
        Grid::destroy($testGridId); $deleted++;
    }
    ok('Caso 10: Cleanup / eliminar tests', $deleted > 0, "{$deleted} recursos eliminados");
} catch (\Exception $e) {
    ok('Caso 10: Cleanup', false, $e->getMessage());
}

// ── Resumen ──
echo "\n=== RESUMEN ===\n";
$total = count($results);
$passed = count(array_filter($results, function ($r) { return $r['ok']; }));
$failed = $total - $passed;
echo "Total: {$total} | OK: {$passed} | FAIL: {$failed}\n";
if ($failed > 0) {
    echo "\nFallos:\n";
    foreach ($results as $r) {
        if (!$r['ok']) echo "  - {$r['test']}: {$r['msg']}\n";
    }
}
echo "\n=== FIN ===\n";

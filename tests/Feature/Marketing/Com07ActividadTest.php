<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Services\Com07ActividadResolver;
use Tests\TestCase;

/**
 * Com07ActividadTest — Última actividad comercial válida por contacto (COM-07).
 *
 * De este dato se deriva toda la antigüedad: los rangos del panel, el umbral de
 * 8 semanas de la detección y el factor de antigüedad del Potencial. Un error
 * aquí no revienta nada — solo clasifica mal las cuentas en silencio.
 *
 * Además de cubrir cada fuente, se prueban las dos reglas de exclusión de la
 * Especificación: las ediciones administrativas del contacto no cuentan (3.4)
 * y la actividad de OTRO contacto de la misma empresa no reinicia la antigüedad
 * del evaluado (3.2).
 */
class Com07ActividadTest extends TestCase
{
    /** @var Com07ActividadResolver */
    private $resolver;

    private $contactoIds      = [];
    private $empresaIds       = [];
    private $requerimientoIds = [];
    private $prequoteIds      = [];
    private $oportunidadIds   = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        DB::purge('system');
        DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);

        $this->resolver = new Com07ActividadResolver();
    }

    protected function tearDown(): void
    {
        try {
            $t = function ($tabla) { return DB::connection('tenant')->table($tabla); };
            if ($this->oportunidadIds) {
                $t('com07_gestiones')->whereIn('oportunidad_id', $this->oportunidadIds)->delete();
                $t('com07_oportunidades')->whereIn('id', $this->oportunidadIds)->delete();
            }
            if ($this->prequoteIds) {
                $t('quotation_proposals')->whereIn('prequote_id', $this->prequoteIds)->delete();
                $t('quotation_prequotes')->whereIn('id', $this->prequoteIds)->delete();
            }
            if ($this->requerimientoIds) {
                $t('requerimiento_interaccion')->whereIn('requerimiento_id', $this->requerimientoIds)->delete();
                $t('cotizaciones_requerimiento')->whereIn('requerimiento_id', $this->requerimientoIds)->delete();
                $t('requerimiento')->whereIn('id', $this->requerimientoIds)->delete();
            }
            if ($this->contactoIds) {
                $t('contacto_interaccion')->whereIn('contacto_id', $this->contactoIds)->delete();
                $t('contacto')->whereIn('id', $this->contactoIds)->delete();
            }
            if ($this->empresaIds) {
                $t('empresa')->whereIn('id', $this->empresaIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Cada fuente cuenta
    |--------------------------------------------------------------------------
    */

    public function test_cuenta_la_interaccion_directa_del_contacto()
    {
        $c = $this->contacto();
        $f = $this->hace(10);
        $this->interaccionDeContacto($c, $f);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_creacion_de_un_requerimiento()
    {
        $c = $this->contacto();
        $f = $this->hace(15);
        $this->requerimiento($c, ['created_at' => $f, 'updated_at' => $f]);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_ultima_interaccion_registrada_en_el_requerimiento()
    {
        $c = $this->contacto();
        $vieja = $this->hace(60);
        $nueva = $this->hace(7);
        $this->requerimiento($c, [
            'created_at'               => $vieja,
            'updated_at'               => $vieja,
            'fecha_ultima_interaccion' => $nueva,
        ]);

        $this->assertSame($nueva, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_interaccion_sobre_el_requerimiento()
    {
        $c = $this->contacto();
        $r = $this->requerimiento($c, ['created_at' => $this->hace(90), 'updated_at' => $this->hace(90)]);
        $f = $this->hace(12);
        $this->tabla('requerimiento_interaccion')->insert([
            'requerimiento_id' => $r,
            'canal'            => 'Correo',
            'resumen'          => 'Prueba',
            'created_at'       => $f,
            'updated_at'       => $f,
        ]);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_cotizacion_subida_al_requerimiento()
    {
        $c = $this->contacto();
        $r = $this->requerimiento($c, ['created_at' => $this->hace(80), 'updated_at' => $this->hace(80)]);
        $f = $this->hace(9);
        $this->tabla('cotizaciones_requerimiento')->insert([
            'requerimiento_id' => $r,
            'version'          => 'V1',
            'monto'            => 1500,
            'estado'           => 'Enviada',
            'created_at'       => $f,
            'updated_at'       => $f,
        ]);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_precotizacion_com02()
    {
        $c = $this->contacto();
        $f = $this->hace(6);
        $this->prequote($c, $this->hace(30), $f);   // creada hace 30, trabajada hace 6

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    public function test_cuenta_la_gestion_del_propio_com07()
    {
        $c = $this->contacto();
        $o = $this->tabla('com07_oportunidades')->insertGetId([
            'contacto_id'  => $c,
            'origen'       => 'sin_movimiento',
            'estado'       => 'en_gestion',
            'detectada_at' => $this->hace(20),
            'created_at'   => $this->hace(20),
            'updated_at'   => $this->hace(20),
        ]);
        $this->oportunidadIds[] = $o;

        $f = $this->hace(2);
        $this->tabla('com07_gestiones')->insert([
            'oportunidad_id' => $o,
            'resultado'      => 'sin_respuesta',
            'created_at'     => $f,
            'updated_at'     => $f,
        ]);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c));
    }

    /*
    |--------------------------------------------------------------------------
    | La más reciente gana; las exclusiones se respetan
    |--------------------------------------------------------------------------
    */

    public function test_entre_todas_las_fuentes_gana_la_mas_reciente()
    {
        $c = $this->contacto();
        $this->interaccionDeContacto($c, $this->hace(50));
        $r = $this->requerimiento($c, ['created_at' => $this->hace(40), 'updated_at' => $this->hace(40)]);
        $masReciente = $this->hace(3);
        $this->tabla('cotizaciones_requerimiento')->insert([
            'requerimiento_id' => $r,
            'version'          => 'V1',
            'monto'            => 900,
            'estado'           => 'Enviada',
            'created_at'       => $masReciente,
            'updated_at'       => $masReciente,
        ]);

        $this->assertSame($masReciente, $this->resolver->ultimaActividadDe($c));
    }

    /**
     * Regla 3.4: una edición administrativa del contacto no es gestión
     * comercial. Editar la ficha no debe mover la antigüedad.
     */
    public function test_editar_la_ficha_del_contacto_no_cuenta_como_actividad()
    {
        $c = $this->contacto();
        $f = $this->hace(70);
        $this->interaccionDeContacto($c, $f);

        // "Corrección administrativa": se toca la ficha hoy.
        $this->tabla('contacto')->where('id', $c)->update([
            'nombres'    => 'Nombre Corregido',
            'updated_at' => now(),
        ]);

        $this->assertSame($f, $this->resolver->ultimaActividadDe($c), 'La edición de la ficha no debe reiniciar la antigüedad');
    }

    /**
     * Regla 3.2: la actividad de OTRO contacto de la misma empresa no reinicia
     * la antigüedad del contacto evaluado.
     */
    public function test_la_actividad_de_otro_contacto_de_la_misma_empresa_no_cuenta()
    {
        $a = $this->contacto();
        $empresaId = $this->tabla('contacto')->where('id', $a)->value('empresa_id');

        // Segundo contacto de la MISMA empresa, con actividad de hoy.
        $b = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto B',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contactoIds[] = $b;
        $this->interaccionDeContacto($b, $this->hace(0));

        $fA = $this->hace(100);
        $this->interaccionDeContacto($a, $fA);

        $this->assertSame($fA, $this->resolver->ultimaActividadDe($a), 'La actividad del contacto B no debe contarle al contacto A');
    }

    public function test_sin_actividad_registrada_devuelve_null()
    {
        $c = $this->contacto();

        $this->assertNull($this->resolver->ultimaActividadDe($c));
    }

    public function test_resolucion_en_lote()
    {
        $conActividad = $this->contacto();
        $f = $this->hace(5);
        $this->interaccionDeContacto($conActividad, $f);
        $sinActividad = $this->contacto();

        $mapa = $this->resolver->ultimaActividadPara([$conActividad, $sinActividad]);

        $this->assertCount(2, $mapa);
        $this->assertSame($f, $mapa[$conActividad]);
        $this->assertNull($mapa[$sinActividad]);
    }

    public function test_lote_vacio()
    {
        $this->assertSame([], $this->resolver->ultimaActividadPara([]));
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    private function tabla($t)
    {
        return DB::connection('tenant')->table($t);
    }

    /** Datetime de hace N días, en el formato de MySQL. */
    private function hace($dias)
    {
        return now()->subDays($dias)->format('Y-m-d H:i:s');
    }

    /** @return int id del contacto */
    private function contacto()
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST ACTIVIDAD',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto Actividad',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);
        $this->contactoIds[] = $contactoId;

        return $contactoId;
    }

    /** @return int id del requerimiento */
    private function requerimiento($contactoId, array $attrs = [])
    {
        $empresaId = $this->tabla('contacto')->where('id', $contactoId)->value('empresa_id');

        $id = $this->tabla('requerimiento')->insertGetId(array_merge([
            'empresa_id'  => $empresaId,
            'contacto_id' => $contactoId,
            'titulo'      => 'REQ test actividad',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $attrs));
        $this->requerimientoIds[] = $id;

        return $id;
    }

    private function prequote($contactoId, $creada, $trabajada)
    {
        $id = $this->tabla('quotation_prequotes')->insertGetId([
            'contacto_id' => $contactoId,
            'version'     => 1,
            'estado'      => 'borrador',
            'titulo'      => 'Prequote test actividad',
            'created_at'  => $creada,
            'updated_at'  => $trabajada,
        ]);
        $this->prequoteIds[] = $id;

        return $id;
    }

    private function interaccionDeContacto($contactoId, $fecha)
    {
        $this->tabla('contacto_interaccion')->insert([
            'contacto_id' => $contactoId,
            'canal'       => 'Teléfono',
            'resumen'     => 'Prueba de actividad',
            'created_at'  => $fecha,
            'updated_at'  => $fecha,
        ]);
    }
}

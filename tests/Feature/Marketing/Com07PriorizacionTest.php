<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;
use Modules\Marketing\Services\Com07PriorizacionService;
use Tests\TestCase;

/**
 * Com07PriorizacionTest — Modelo de Priorización Comercial (COM-07).
 *
 * Las tablas de puntos son EL CONTRATO: están en la Especificación de Compipro
 * y en nuestra Conformidad, y la validación final será "cumple / no cumple"
 * contra ellas. Cada regla de puntaje se prueba con sus valores de borde.
 *
 * La distinción más delicada: Urgencia "No aplica" (sin necesidad actual) NO
 * es Urgencia 0. Vale 0 solo dentro de la fórmula de la Prioridad, pero el
 * indicador debe decir "No aplica" — está expresamente exigido.
 */
class Com07PriorizacionTest extends TestCase
{
    /** @var Com07PriorizacionService */
    private $servicio;

    private $contactoIds      = [];
    private $empresaIds       = [];
    private $oportunidadIds   = [];
    private $requerimientoIds = [];
    private $prequoteIds      = [];
    private $proyectoIds      = [];

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

        $this->servicio = new Com07PriorizacionService();
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
                $t('quotation_prequote_items')->whereIn('prequote_id', $this->prequoteIds)->delete();
                $t('quotation_prequotes')->whereIn('id', $this->prequoteIds)->delete();
            }
            if ($this->proyectoIds) {
                $t('proyecto')->whereIn('id', $this->proyectoIds)->delete();
            }
            if ($this->requerimientoIds) {
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
    | Potencial Comercial
    |--------------------------------------------------------------------------
    */

    /** Cuenta con todo a favor: 3 + 3 + 2 + 2 = 10, nivel Muy Alto. */
    public function test_potencial_maximo()
    {
        $e = $this->empresa(['n_trabajadores' => 300]);
        // Historial: cotización de S/ 5,000 hace 3 años y reqs cada ~3 meses.
        $this->hechosComerciales($e, ['-36 months', '-33 months', '-30 months'], 5000);

        $o = $this->oportunidadSinMovimiento($e);
        $this->servicio->calcularPara([$o]);

        $fila = Com07Oportunidad::find($o);
        $this->assertSame(10, (int) $fila->potencial_puntaje);
        $this->assertSame('muy_alta', $fila->nivel_potencial);
    }

    /** Cuenta nueva y vacía: 0 puntos, nivel Bajo. */
    public function test_potencial_sin_informacion()
    {
        $e = $this->empresa(['n_trabajadores' => null]);

        $o = $this->oportunidadSinMovimiento($e);
        $this->servicio->calcularPara([$o]);

        $fila = Com07Oportunidad::find($o);
        $this->assertSame(0, (int) $fila->potencial_puntaje);
        $this->assertSame('baja', $fila->nivel_potencial);
    }

    /** Los bordes de las tablas de monto y trabajadores puntúan como dice el contrato. */
    public function test_bordes_de_monto_y_trabajadores()
    {
        // S/ 2,000 es 2 puntos (borde inferior del rango medio);
        // 99 trabajadores es 1 punto (borde superior del rango bajo).
        $e = $this->empresa(['n_trabajadores' => 99]);
        $this->hechosComerciales($e, ['-2 months'], 2000);

        $o = $this->oportunidadSinMovimiento($e);
        $this->servicio->calcularPara([$o]);

        $fila     = Com07Oportunidad::find($o);
        $sustento = json_decode($fila->prioridad_sustento, true);
        $puntos   = [];
        foreach ($sustento['potencial'] as $f) {
            $puntos[$f['factor']] = $f['puntos'];
        }

        $this->assertSame(2, $puntos['Historial comercial por monto']);
        $this->assertSame(1, $puntos['Número de trabajadores']);
    }

    /** Un solo hecho comercial no alcanza para medir frecuencia. */
    public function test_frecuencia_con_un_solo_hecho_es_insuficiente()
    {
        $e = $this->empresa([]);
        $this->hechosComerciales($e, ['-14 months'], 500);   // 1 req: antigüedad 1, frecuencia 0

        $o = $this->oportunidadSinMovimiento($e);
        $this->servicio->calcularPara([$o]);

        $fila = Com07Oportunidad::find($o);
        // monto 0 (S/ 500) + trabajadores 0 + antigüedad 1 (>= 1 año) + frecuencia 0
        $this->assertSame(1, (int) $fila->potencial_puntaje);
    }

    /*
    |--------------------------------------------------------------------------
    | Urgencia Comercial y "No aplica"
    |--------------------------------------------------------------------------
    */

    /** Sin necesidad actual, la Urgencia es No aplica — no 0 — y no bloquea la Prioridad. */
    public function test_sin_movimiento_la_urgencia_no_aplica()
    {
        $e = $this->empresa(['n_trabajadores' => 300]);
        // Intervalo de ~4 meses: claramente dentro del "<= 6 meses" (con 6
        // exactos el redondeo de los meses de calendario deja el punto al azar).
        $this->hechosComerciales($e, ['-36 months', '-32 months'], 5000);   // potencial 10

        $o = $this->oportunidadSinMovimiento($e);
        $this->servicio->calcularPara([$o]);

        $fila = Com07Oportunidad::find($o);
        $this->assertNull($fila->urgencia_puntaje, 'No aplica se guarda como NULL, no como 0');
        $this->assertSame('no_aplica', $fila->nivel_urgencia);
        // Prioridad = 10 × 0.4 + 0 × 0.6 = 4.0
        $this->assertSame('4.0', (string) $fila->prioridad_puntaje);
        $this->assertSame('media', $fila->nivel_prioridad);
    }

    /** Cotización caída con todo definido: urgencia 10 y la fórmula 40/60 exacta. */
    public function test_urgencia_completa_de_una_cotizacion_caida()
    {
        $e = $this->empresa(['n_trabajadores' => 10]);
        $r = $this->requerimiento($e, ['fecha_requerida' => now()->addDays(10)->toDateString()]);
        $this->cotizacion($r, 4500);
        $this->prequoteConItem($e, $r);

        $o = $this->oportunidadCaida($e, $r);
        $this->servicio->calcularPara([$o]);

        $fila = Com07Oportunidad::find($o);
        // valor 3 (>= 4,000) + proximidad 3 (<= 15 días) + intención 2 + definición 2
        $this->assertSame(10, (int) $fila->urgencia_puntaje);
        $this->assertSame('muy_alta', $fila->nivel_urgencia);

        // La fórmula exacta: potencial × 0.4 + 6.0, y su nivel derivado.
        $esperado = round($fila->potencial_puntaje * 0.4 + 6.0, 1);
        $this->assertEquals($esperado, (float) $fila->prioridad_puntaje);
        $this->assertSame(Com07Oportunidad::nivelPorPuntaje($esperado), $fila->nivel_prioridad);
    }

    /** Los rangos de la Proximidad puntúan por tramos, y sin fecha es 0. */
    public function test_rangos_de_proximidad()
    {
        $e = $this->empresa([]);

        $casos = [
            [now()->addDays(20)->toDateString(), 2],   // 16-30
            [now()->addDays(45)->toDateString(), 1],   // 31-60
            [now()->addDays(90)->toDateString(), 0],   // > 60
            [null, 0],                                  // sin fecha
        ];

        foreach ($casos as $caso) {
            list($fecha, $esperado) = $caso;
            $r = $this->requerimiento($e, ['fecha_requerida' => $fecha]);
            $o = $this->oportunidadCaida($e, $r);
            $this->servicio->calcularPara([$o]);

            $sustento = json_decode(Com07Oportunidad::find($o)->prioridad_sustento, true);
            $proximidad = null;
            foreach ($sustento['urgencia'] as $f) {
                if ($f['factor'] === 'Proximidad') {
                    $proximidad = $f['puntos'];
                }
            }
            $this->assertSame($esperado, $proximidad, 'Fecha: ' . var_export($fecha, true));
        }
    }

    /** Una fecha requerida ya vencida es lo más próximo que existe: 3 puntos. */
    public function test_fecha_requerida_vencida_puntua_maximo()
    {
        $e = $this->empresa([]);
        $r = $this->requerimiento($e, ['fecha_requerida' => now()->subDays(5)->toDateString()]);
        $o = $this->oportunidadCaida($e, $r);

        $this->servicio->calcularPara([$o]);

        $sustento = json_decode(Com07Oportunidad::find($o)->prioridad_sustento, true);
        foreach ($sustento['urgencia'] as $f) {
            if ($f['factor'] === 'Proximidad') {
                $this->assertSame(3, $f['puntos']);
            }
        }
    }

    /** Ítems sin fecha: definición parcial (1). Sin ítems: sin información (0). */
    public function test_definicion_parcial_y_sin_informacion()
    {
        $e = $this->empresa([]);

        // Con ítems pero sin fecha requerida → 1.
        $r1 = $this->requerimiento($e, ['fecha_requerida' => null]);
        $this->prequoteConItem($e, $r1);
        $o1 = $this->oportunidadCaida($e, $r1);

        // Sin ítems → 0.
        $r2 = $this->requerimiento($e, ['fecha_requerida' => now()->addDays(5)->toDateString()]);
        $o2 = $this->oportunidadCaida($e, $r2);

        $this->servicio->calcularPara([$o1, $o2]);

        $this->assertSame(1, $this->puntosDeFactor($o1, 'urgencia', 'Definición de la necesidad'));
        $this->assertSame(0, $this->puntosDeFactor($o2, 'urgencia', 'Definición de la necesidad'));
    }

    /** El valor estimado toma la MAYOR valorización relacionada con la necesidad. */
    public function test_el_valor_estimado_toma_la_mayor_valorizacion()
    {
        $e = $this->empresa([]);
        $r = $this->requerimiento($e, ['monto_estimado' => 800]);
        $this->cotizacion($r, 1500);
        $this->prequoteConItem($e, $r, 2500);   // la más alta

        $o = $this->oportunidadCaida($e, $r);
        $this->servicio->calcularPara([$o]);

        // S/ 2,500 → 2 puntos
        $this->assertSame(2, $this->puntosDeFactor($o, 'urgencia', 'Valor estimado de la necesidad'));
    }

    /*
    |--------------------------------------------------------------------------
    | Prioridad y sustento
    |--------------------------------------------------------------------------
    */

    /** El sustento guarda los 8 factores con sus puntos: es lo que muestra el detalle. */
    public function test_el_sustento_documenta_todos_los_factores()
    {
        $e = $this->empresa(['n_trabajadores' => 150]);
        $r = $this->requerimiento($e, []);
        $o = $this->oportunidadCaida($e, $r);

        $this->servicio->calcularPara([$o]);

        $sustento = json_decode(Com07Oportunidad::find($o)->prioridad_sustento, true);
        $this->assertCount(4, $sustento['potencial']);
        $this->assertCount(4, $sustento['urgencia']);
        foreach (array_merge($sustento['potencial'], $sustento['urgencia']) as $f) {
            $this->assertArrayHasKey('factor', $f);
            $this->assertArrayHasKey('valor', $f);
            $this->assertArrayHasKey('puntos', $f);
        }
    }

    /** El lote calcula varias oportunidades de una pasada. */
    public function test_calculo_en_lote()
    {
        $e1 = $this->empresa(['n_trabajadores' => 300]);
        $e2 = $this->empresa([]);
        $o1 = $this->oportunidadSinMovimiento($e1);
        $o2 = $this->oportunidadSinMovimiento($e2);

        $this->servicio->calcularPara([$o1, $o2]);

        $this->assertNotNull(Com07Oportunidad::find($o1)->prioridad_puntaje);
        $this->assertNotNull(Com07Oportunidad::find($o2)->prioridad_puntaje);
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

    private function puntosDeFactor($oportunidadId, $bloque, $factor)
    {
        $sustento = json_decode(Com07Oportunidad::find($oportunidadId)->prioridad_sustento, true);
        foreach ($sustento[$bloque] as $f) {
            if ($f['factor'] === $factor) {
                return $f['puntos'];
            }
        }

        $this->fail("Factor {$factor} no encontrado en el sustento");
    }

    /** @return int id de la empresa */
    private function empresa(array $attrs)
    {
        $id = $this->tabla('empresa')->insertGetId(array_merge([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST PRIORIZACION',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $attrs));
        $this->empresaIds[] = $id;

        return $id;
    }

    /** Requerimientos históricos espaciados + una cotización con el monto dado. */
    private function hechosComerciales($empresaId, array $fechas, $monto)
    {
        $primero = null;
        foreach ($fechas as $expresion) {
            $fecha = date('Y-m-d H:i:s', strtotime($expresion));
            $reqId = $this->tabla('requerimiento')->insertGetId([
                'empresa_id' => $empresaId,
                'titulo'     => 'REQ historico',
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
            $this->requerimientoIds[] = $reqId;
            if ($primero === null) {
                $primero = $reqId;
            }
        }

        if ($monto !== null && $primero !== null) {
            $this->tabla('cotizaciones_requerimiento')->insert([
                'requerimiento_id' => $primero,
                'version'          => 'V1',
                'monto'            => $monto,
                'estado'           => 'Enviada',
                'created_at'       => date('Y-m-d H:i:s', strtotime($fechas[0])),
                'updated_at'       => date('Y-m-d H:i:s', strtotime($fechas[0])),
            ]);
        }
    }

    /** @return int id del requerimiento */
    private function requerimiento($empresaId, array $attrs)
    {
        $id = $this->tabla('requerimiento')->insertGetId(array_merge([
            'empresa_id' => $empresaId,
            'titulo'     => 'REQ necesidad actual',
            'etapa'      => 'Cotización caída',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
        $this->requerimientoIds[] = $id;

        return $id;
    }

    private function cotizacion($reqId, $monto)
    {
        $this->tabla('cotizaciones_requerimiento')->insert([
            'requerimiento_id' => $reqId,
            'version'          => 'V1',
            'monto'            => $monto,
            'estado'           => 'Caida',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function prequoteConItem($empresaId, $reqId, $total = null)
    {
        $prequoteId = $this->tabla('quotation_prequotes')->insertGetId([
            'empresa_id'       => $empresaId,
            'requerimiento_id' => $reqId,
            'version'          => 1,
            'estado'           => 'borrador',
            'titulo'           => 'Prequote priorizacion',
            'total'            => $total !== null ? $total : 0,   // la columna es NOT NULL
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->prequoteIds[] = $prequoteId;

        $this->tabla('quotation_prequote_items')->insert([
            'prequote_id'  => $prequoteId,
            'product_name' => 'Polos corporativos',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return $prequoteId;
    }

    private function contactoDe($empresaId)
    {
        $id = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto Priorizacion',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contactoIds[] = $id;

        return $id;
    }

    /** @return int id de la oportunidad */
    private function oportunidadSinMovimiento($empresaId)
    {
        return $this->oportunidad($empresaId, Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO, null);
    }

    private function oportunidadCaida($empresaId, $reqId)
    {
        return $this->oportunidad($empresaId, Com07Oportunidad::ORIGEN_COTIZACION_CAIDA, $reqId);
    }

    private function oportunidad($empresaId, $origen, $reqId)
    {
        $id = $this->tabla('com07_oportunidades')->insertGetId([
            'contacto_id'         => $this->contactoDe($empresaId),
            'empresa_id'          => $empresaId,
            'origen'              => $origen,
            'requerimiento_id'    => $reqId,
            'estado'              => Com07Oportunidad::ESTADO_PENDIENTE,
            'ultima_actividad_at' => now()->subDays(120)->toDateString(),
            'detectada_at'        => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        $this->oportunidadIds[] = $id;

        return $id;
    }
}

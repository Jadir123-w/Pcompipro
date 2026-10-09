<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Gestion;
use Modules\Marketing\Models\Com07Oportunidad;
use Tests\TestCase;

/**
 * Com07EntidadesTest — Entidades de oportunidad y gestiones (COM-07, Fase 1).
 *
 * Lo que se prueba es sobre todo lo DERIVADO, que es donde puede haber errores
 * silenciosos: la antigüedad y su rango, y la condición del seguimiento. Nada
 * de eso se guarda en la base — se calcula contra la fecha de hoy — así que un
 * error no rompe nada visiblemente, sólo muestra el dato equivocado.
 */
class Com07EntidadesTest extends TestCase
{
    /** @var array IDs creados, para limpiar */
    private $oportunidadIds = [];

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
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->oportunidadIds)) {
                Com07Gestion::whereIn('oportunidad_id', $this->oportunidadIds)->delete();
                Com07Oportunidad::whereIn('id', $this->oportunidadIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Antigüedad
    |--------------------------------------------------------------------------
    */

    /** La antigüedad en días se cuenta desde la última actividad comercial. */
    public function test_antiguedad_en_dias()
    {
        $o = $this->oportunidad(['ultima_actividad_at' => now()->subDays(70)->toDateString()]);

        $this->assertSame(70, $o->antiguedad_dias);
    }

    /** Sin última actividad conocida, la antigüedad y su rango quedan indefinidos. */
    public function test_sin_ultima_actividad_no_hay_antiguedad()
    {
        $o = $this->oportunidad(['ultima_actividad_at' => null]);

        $this->assertNull($o->antiguedad_dias);
        $this->assertNull($o->rango_antiguedad);
    }

    /**
     * Los tres rangos de la Especificación, probados en sus bordes: el mes 4 y
     * el mes 6 son los que deciden, y un error de un día ahí clasifica mal la
     * cuenta en el panel.
     */
    public function test_rangos_de_antiguedad_en_sus_bordes()
    {
        $casos = [
            // [meses atrás, rango esperado]
            [2,  Com07Oportunidad::RANGO_RECIENTE],  // 8 semanas cumplidas
            [3,  Com07Oportunidad::RANGO_RECIENTE],
            [4,  Com07Oportunidad::RANGO_MEDIO],     // borde: cumple 4 meses
            [5,  Com07Oportunidad::RANGO_MEDIO],
            [6,  Com07Oportunidad::RANGO_ANTIGUO],   // borde: cumple 6 meses
            [14, Com07Oportunidad::RANGO_ANTIGUO],
        ];

        foreach ($casos as list($meses, $esperado)) {
            $o = $this->oportunidad(['ultima_actividad_at' => now()->subMonths($meses)->toDateString()]);
            $this->assertSame(
                $esperado,
                $o->rango_antiguedad,
                "Con {$meses} meses de inactividad el rango deberia ser {$esperado}"
            );
        }
    }

    /** El umbral de ingreso al proceso son 8 semanas. */
    public function test_umbral_de_inactividad_son_ocho_semanas()
    {
        $this->assertSame(56, Com07Oportunidad::DIAS_INACTIVIDAD);
    }

    /*
    |--------------------------------------------------------------------------
    | Condición del seguimiento
    |--------------------------------------------------------------------------
    */

    /** Fecha futura: el seguimiento está pendiente y no hay atraso. */
    public function test_seguimiento_pendiente()
    {
        $o = $this->oportunidad(['proxima_atencion_at' => now()->addDays(3)->toDateString()]);

        $this->assertSame('pendiente', $o->condicion_seguimiento);
        $this->assertSame(0, $o->getDiasAtraso());
    }

    /** Fecha pasada sin atender: vencido, con sus días de atraso. */
    public function test_seguimiento_vencido_con_dias_de_atraso()
    {
        $o = $this->oportunidad(['proxima_atencion_at' => now()->subDays(5)->toDateString()]);

        $this->assertSame('vencido', $o->condicion_seguimiento);
        $this->assertSame(5, $o->getDiasAtraso());
    }

    /** Hoy todavía no está vencido: se vence al día siguiente. */
    public function test_la_fecha_de_hoy_no_esta_vencida()
    {
        $o = $this->oportunidad(['proxima_atencion_at' => now()->toDateString()]);

        $this->assertSame('pendiente', $o->condicion_seguimiento);
        $this->assertSame(0, $o->getDiasAtraso());
    }

    /** Sin fecha de atención no hay seguimiento que vencer. */
    public function test_sin_proxima_atencion_no_hay_seguimiento()
    {
        $o = $this->oportunidad(['proxima_atencion_at' => null]);

        $this->assertSame('sin_seguimiento', $o->condicion_seguimiento);
        $this->assertSame(0, $o->getDiasAtraso());
    }

    /*
    |--------------------------------------------------------------------------
    | Estado de la oportunidad
    |--------------------------------------------------------------------------
    */

    /** Sólo pendiente y en gestión bloquean una nueva detección. */
    public function test_vigencia_de_la_oportunidad()
    {
        $pendiente = $this->oportunidad(['estado' => Com07Oportunidad::ESTADO_PENDIENTE]);
        $enGestion = $this->oportunidad(['estado' => Com07Oportunidad::ESTADO_EN_GESTION]);
        $concluida = $this->oportunidad(['estado' => Com07Oportunidad::ESTADO_CONCLUIDA]);

        $this->assertTrue($pendiente->estaVigente());
        $this->assertTrue($enGestion->estaVigente());
        $this->assertFalse($concluida->estaVigente());
    }

    /** Sin responsable, la oportunidad no entra al panel de ninguna ejecutiva. */
    public function test_oportunidad_sin_responsable()
    {
        $sin  = $this->oportunidad(['responsable_comercial_id' => null]);
        $con  = $this->oportunidad(['responsable_comercial_id' => 1]);

        $this->assertFalse($sin->tieneResponsable());
        $this->assertTrue($con->tieneResponsable());
    }

    /** El scope de vigentes deja fuera las concluidas. */
    public function test_scope_vigentes()
    {
        $viva     = $this->oportunidad(['estado' => Com07Oportunidad::ESTADO_EN_GESTION]);
        $cerrada  = $this->oportunidad(['estado' => Com07Oportunidad::ESTADO_CONCLUIDA]);

        $ids = Com07Oportunidad::vigentes()->whereIn('id', $this->oportunidadIds)->pluck('id')->all();

        $this->assertContains($viva->id, $ids);
        $this->assertNotContains($cerrada->id, $ids);
    }

    /*
    |--------------------------------------------------------------------------
    | Gestiones y seguimientos
    |--------------------------------------------------------------------------
    */

    /** Una gestión sin seguimiento no deja continuidad. */
    public function test_gestion_sin_seguimiento()
    {
        $g = $this->gestion(['resultado' => Com07Gestion::RESULTADO_CONCLUIDA]);

        $this->assertNull($g->condicion_seguimiento);
        $this->assertFalse($g->dejaContinuidad());
    }

    /** Un seguimiento programado a futuro deja continuidad y figura pendiente. */
    public function test_gestion_con_seguimiento_pendiente()
    {
        $g = $this->gestion([
            'resultado'          => Com07Gestion::RESULTADO_NUEVO_SEGUIMIENTO,
            'seguimiento_at'     => now()->addDays(4)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);

        $this->assertSame('pendiente', $g->condicion_seguimiento);
        $this->assertTrue($g->dejaContinuidad());
    }

    /** Un seguimiento con fecha pasada se muestra vencido sin haber guardado ese estado. */
    public function test_gestion_con_seguimiento_vencido()
    {
        $g = $this->gestion([
            'seguimiento_at'     => now()->subDays(2)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);

        $this->assertSame('vencido', $g->condicion_seguimiento);
        $this->assertSame(Com07Gestion::SEG_PENDIENTE, $g->seguimiento_estado, 'El estado guardado no cambia');
    }

    /** Atendido y cancelado son definitivos: una fecha pasada ya no los vuelve vencidos. */
    public function test_atendido_y_cancelado_no_se_vencen()
    {
        $atendido = $this->gestion([
            'seguimiento_at'     => now()->subDays(10)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_ATENDIDO,
        ]);
        $cancelado = $this->gestion([
            'seguimiento_at'     => now()->subDays(10)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_CANCELADO,
        ]);

        $this->assertSame(Com07Gestion::SEG_ATENDIDO, $atendido->condicion_seguimiento);
        $this->assertSame(Com07Gestion::SEG_CANCELADO, $cancelado->condicion_seguimiento);
    }

    /** Reprogramar conserva la fecha anterior. */
    public function test_reprogramar_conserva_la_fecha_anterior()
    {
        $original = now()->addDays(2)->toDateString();
        $nueva    = now()->addDays(9)->toDateString();

        $g = $this->gestion([
            'seguimiento_at'     => $original,
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);

        $g->reprogramar($nueva);
        $g = $g->fresh();

        $this->assertSame($nueva, $g->seguimiento_at->toDateString());
        $this->assertSame($original, $g->seguimiento_at_anterior->toDateString());
        $this->assertSame(Com07Gestion::SEG_PENDIENTE, $g->seguimiento_estado);
    }

    /** Cancelar conserva el registro y el motivo; no borra nada. */
    public function test_cancelar_conserva_registro_y_motivo()
    {
        $g = $this->gestion([
            'seguimiento_at'     => now()->addDays(3)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);
        $id = $g->id;

        $g->cancelarSeguimiento('El cliente compró a otro proveedor');
        $g = Com07Gestion::find($id);

        $this->assertNotNull($g, 'La cancelacion no debe eliminar la gestion');
        $this->assertSame(Com07Gestion::SEG_CANCELADO, $g->seguimiento_estado);
        $this->assertSame('El cliente compró a otro proveedor', $g->seguimiento_motivo);
        $this->assertFalse($g->dejaContinuidad());
    }

    /** El scope de vencidos sólo trae seguimientos pendientes con fecha pasada. */
    public function test_scope_vencidos()
    {
        $vencido = $this->gestion([
            'seguimiento_at'     => now()->subDays(3)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);
        $futuro = $this->gestion([
            'seguimiento_at'     => now()->addDays(3)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_PENDIENTE,
        ]);
        $atendido = $this->gestion([
            'seguimiento_at'     => now()->subDays(3)->toDateString(),
            'seguimiento_estado' => Com07Gestion::SEG_ATENDIDO,
        ]);

        $ids = Com07Gestion::vencidos()
            ->whereIn('oportunidad_id', $this->oportunidadIds)
            ->pluck('id')->all();

        $this->assertContains($vencido->id, $ids);
        $this->assertNotContains($futuro->id, $ids);
        $this->assertNotContains($atendido->id, $ids);
    }

    /** La oportunidad expone sus gestiones y la última registrada. */
    public function test_relacion_con_gestiones()
    {
        $o = $this->oportunidad();
        Com07Gestion::create([
            'oportunidad_id' => $o->id,
            'resultado'      => Com07Gestion::RESULTADO_SIN_RESPONDER,
        ]);
        $ultima = Com07Gestion::create([
            'oportunidad_id' => $o->id,
            'resultado'      => Com07Gestion::RESULTADO_EXISTE_INTERES,
        ]);

        $o = $o->fresh(['gestiones', 'ultimaGestion']);

        $this->assertCount(2, $o->gestiones);
        $this->assertSame($ultima->id, $o->ultimaGestion->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function oportunidad(array $attrs = [])
    {
        $o = Com07Oportunidad::create(array_merge([
            'contacto_id'  => 999999,
            'empresa_id'   => 999999,
            'origen'       => Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO,
            'estado'       => Com07Oportunidad::ESTADO_PENDIENTE,
            'detectada_at' => now(),
        ], $attrs));

        $this->oportunidadIds[] = $o->id;

        return $o;
    }

    private function gestion(array $attrs = [])
    {
        $o = $this->oportunidad();

        return Com07Gestion::create(array_merge([
            'oportunidad_id' => $o->id,
            'resultado'      => Com07Gestion::RESULTADO_NUEVO_SEGUIMIENTO,
        ], $attrs));
    }
}

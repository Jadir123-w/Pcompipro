<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;
use Modules\Marketing\Services\Com07DetectorService;
use Tests\TestCase;

/**
 * Com07DeteccionTest — Proceso automático de detección (COM-07).
 *
 * Nadie carga oportunidades a mano: si el detector no las crea, el agente no
 * existe. Y si las crea de más, las ejecutivas ven ruido y dejan de usarlo.
 *
 * Por eso se prueban las dos mitades: que los tres disparadores encuentren lo
 * que deben, y que las guardas impidan duplicar, resucitar lo concluido o
 * molestar a un cliente que está siendo atendido.
 *
 * El tenant local tiene datos reales, así que cada prueba mide el efecto sobre
 * SUS propios contactos, no el total de la tabla.
 */
class Com07DeteccionTest extends TestCase
{
    /** @var Com07DetectorService */
    private $detector;

    private $contactoIds      = [];
    private $empresaIds       = [];
    private $requerimientoIds = [];
    private $proyectoIds      = [];

    /**
     * Última oportunidad existente antes de la prueba.
     *
     * El detector recorre TODO el tenant, así que también crea oportunidades
     * legítimas sobre los contactos reales de la base local. Se registra la
     * marca para poder borrar después todo lo que nació durante la prueba, sin
     * tocar lo que ya estaba.
     */
    private $marcaOportunidades = 0;

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

        $this->detector = new Com07DetectorService();

        $this->marcaOportunidades = (int) DB::connection('tenant')
            ->table('com07_oportunidades')->max('id');
    }

    protected function tearDown(): void
    {
        try {
            $t = function ($tabla) { return DB::connection('tenant')->table($tabla); };

            // Todo lo que el detector creó durante la prueba, sea de contactos
            // de prueba o reales.
            $nacidas = $t('com07_oportunidades')->where('id', '>', $this->marcaOportunidades)->pluck('id')->all();
            if ($nacidas) {
                $t('com07_gestiones')->whereIn('oportunidad_id', $nacidas)->delete();
                $t('com07_oportunidades')->whereIn('id', $nacidas)->delete();
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
    | Los tres disparadores
    |--------------------------------------------------------------------------
    */

    /** Un Requerimiento en etapa "Cotización caída" genera una oportunidad. */
    public function test_detecta_cotizacion_caida_por_etapa()
    {
        $c = $this->contactoActivo();
        $r = $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o, 'Debía detectarse la cotización caída');
        $this->assertSame(Com07Oportunidad::ORIGEN_COTIZACION_CAIDA, $o->origen);
        $this->assertSame($r, (int) $o->requerimiento_id);
        $this->assertSame(Com07Oportunidad::ESTADO_PENDIENTE, $o->estado);
    }

    /** También cuando la caída se marcó en la cotización y no en la etapa. */
    public function test_detecta_cotizacion_caida_por_estado_de_la_cotizacion()
    {
        $c = $this->contactoActivo();
        $r = $this->requerimiento($c, ['etapa' => 'Cotización enviada']);
        $this->cotizacion($r, Com07DetectorService::COTIZACION_CAIDA, 5000);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o);
        $this->assertSame(Com07Oportunidad::ORIGEN_COTIZACION_CAIDA, $o->origen);
    }

    /** Un Requerimiento marcado como no cotizado también entra. */
    public function test_detecta_no_cotizado()
    {
        $c = $this->contactoActivo();
        $r = $this->requerimiento($c, ['etapa' => 'Pendiente atender']);
        // Un "No cotizado" se registra sin valorizar: el monto queda en cero.
        $this->cotizacion($r, Com07DetectorService::COTIZACION_NO_COTIZADO, 0);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o);
        $this->assertSame(Com07Oportunidad::ORIGEN_NO_COTIZADO, $o->origen);
    }

    /** Un contacto con más de 8 semanas sin actividad entra por inactividad. */
    public function test_detecta_inactividad_de_ocho_semanas()
    {
        $c = $this->contactoActivo();
        $this->interaccion($c, $this->hace(100));

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o, 'Debía detectarse la inactividad');
        $this->assertSame(Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO, $o->origen);
        $this->assertNull($o->requerimiento_id);
        $this->assertSame($this->hace(100, 'Y-m-d'), $o->ultima_actividad_at);
    }

    /** Un contacto gestionado la semana pasada no es una oportunidad. */
    public function test_no_detecta_contacto_con_actividad_reciente()
    {
        $c = $this->contactoActivo();
        $this->interaccion($c, $this->hace(7));

        $this->detector->detectar();

        $this->assertNull($this->oportunidadDe($c), 'Un contacto activo no debe entrar');
    }

    /**
     * Una cotización caída pesa más que el silencio: si el contacto califica
     * por los dos, gana el disparador que dice qué hacer.
     */
    public function test_la_cotizacion_caida_gana_sobre_la_inactividad()
    {
        $c = $this->contactoActivo();
        $this->interaccion($c, $this->hace(200));
        $this->requerimiento($c, [
            'etapa'      => Com07DetectorService::ETAPA_COTIZACION_CAIDA,
            'created_at' => $this->hace(200),
            'updated_at' => $this->hace(200),
        ]);

        $this->detector->detectar();

        $this->assertSame(1, $this->cuantasOportunidadesDe($c), 'Debe crearse una sola oportunidad');
        $this->assertSame(Com07Oportunidad::ORIGEN_COTIZACION_CAIDA, $this->oportunidadDe($c)->origen);
    }

    /*
    |--------------------------------------------------------------------------
    | Requerimientos sin contacto asignado
    |
    | Buena parte del historial quedó cargado sin contacto, y es justamente el
    | que COM-07 debe recuperar. Sin este respaldo los dos disparadores más
    | valiosos serían ciegos a esos casos.
    |--------------------------------------------------------------------------
    */

    /** Sin contacto en el Requerimiento, se retoma por el contacto de la empresa. */
    public function test_deduce_el_contacto_desde_la_empresa()
    {
        $c = $this->contactoActivo();
        $empresaId = $this->tabla('contacto')->where('id', $c)->value('empresa_id');

        $r = $this->tabla('requerimiento')->insertGetId([
            'empresa_id'  => $empresaId,
            'contacto_id' => null,          // el historial suele venir así
            'titulo'      => 'REQ sin contacto',
            'etapa'       => Com07DetectorService::ETAPA_COTIZACION_CAIDA,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->requerimientoIds[] = $r;

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o, 'La venta perdida no debe perderse por un contacto vacío');
        $this->assertSame($r, (int) $o->requerimiento_id);
    }

    /** Con varios contactos, se elige el de actividad más reciente. */
    public function test_entre_varios_contactos_elige_al_de_actividad_mas_reciente()
    {
        $antiguo = $this->contactoActivo();
        $empresaId = $this->tabla('contacto')->where('id', $antiguo)->value('empresa_id');
        $this->interaccion($antiguo, $this->hace(300));

        $vigente = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto Vigente',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contactoIds[] = $vigente;
        $this->interaccion($vigente, $this->hace(20));

        $r = $this->tabla('requerimiento')->insertGetId([
            'empresa_id'  => $empresaId,
            'contacto_id' => null,
            'titulo'      => 'REQ sin contacto',
            'etapa'       => Com07DetectorService::ETAPA_COTIZACION_CAIDA,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->requerimientoIds[] = $r;

        $this->detector->detectar();

        $this->assertNotNull($this->oportunidadDe($vigente), 'Debe retomarse por el interlocutor vigente');
        $oAntiguo = $this->oportunidadDe($antiguo);
        $this->assertNotSame(
            Com07Oportunidad::ORIGEN_COTIZACION_CAIDA,
            $oAntiguo ? $oAntiguo->origen : null,
            'La cotización caída no debe asignarse al contacto sin actividad reciente'
        );
    }

    /** Si la empresa no tiene ningún contacto, no hay a quién llamar. */
    public function test_sin_contactos_en_la_empresa_no_se_crea_nada()
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA SIN CONTACTOS',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $r = $this->tabla('requerimiento')->insertGetId([
            'empresa_id'  => $empresaId,
            'contacto_id' => null,
            'titulo'      => 'REQ huerfano',
            'etapa'       => Com07DetectorService::ETAPA_COTIZACION_CAIDA,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->requerimientoIds[] = $r;

        $this->detector->detectar();

        $this->assertSame(
            0,
            (int) $this->tabla('com07_oportunidades')->where('requerimiento_id', $r)->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusiones
    |--------------------------------------------------------------------------
    */

    /**
     * Un cliente con proyecto activo está siendo atendido, no abandonado: no
     * entra por inactividad.
     */
    public function test_no_detecta_inactividad_si_hay_proyecto_activo()
    {
        $c = $this->contactoActivo();
        $this->interaccion($c, $this->hace(150));
        $this->proyecto($c, 'activo');

        $this->detector->detectar();

        $this->assertNull($this->oportunidadDe($c), 'Un cliente con proyecto activo no está abandonado');
    }

    /**
     * Pero su cotización caída SIGUE siendo recuperable: la exclusión del
     * proyecto activo aplica solo a la inactividad.
     */
    public function test_la_cotizacion_caida_entra_aunque_haya_proyecto_activo()
    {
        $c = $this->contactoActivo();
        $this->proyecto($c, 'activo');
        $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o, 'La venta perdida sigue siendo recuperable');
        $this->assertSame(Com07Oportunidad::ORIGEN_COTIZACION_CAIDA, $o->origen);
    }

    /** Una venta ganada no es una oportunidad de recuperación. */
    public function test_no_detecta_requerimiento_ya_ganado()
    {
        $c = $this->contactoActivo();
        $this->requerimiento($c, [
            'etapa'               => Com07DetectorService::ETAPA_COTIZACION_CAIDA,
            'cotizacion_aprobada' => 1,
        ]);

        $this->detector->detectar();

        $this->assertNull($this->oportunidadDe($c));
    }

    /*
    |--------------------------------------------------------------------------
    | Guardas contra la repetición
    |--------------------------------------------------------------------------
    */

    /** Correrlo dos veces no puede duplicar nada. */
    public function test_correrlo_dos_veces_no_duplica()
    {
        $c = $this->contactoActivo();
        $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $this->detector->detectar();
        $this->detector->detectar();

        $this->assertSame(1, $this->cuantasOportunidadesDe($c));
    }

    /**
     * Concluir una oportunidad no debe hacer que reaparezca al día siguiente:
     * cada Requerimiento genera una sola en toda su vida.
     */
    public function test_una_oportunidad_concluida_no_resucita()
    {
        $c = $this->contactoActivo();
        $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $this->detector->detectar();
        $this->concluirOportunidadesDe($c);
        $this->detector->detectar();

        $this->assertSame(1, $this->cuantasOportunidadesDe($c), 'No debe volver a crearse');
    }

    /**
     * Por inactividad sí vuelve a entrar, pero solo si la relación se movió y
     * se enfrió otra vez.
     */
    public function test_la_inactividad_reaparece_solo_si_hubo_movimiento_nuevo()
    {
        $c = $this->contactoActivo();
        $this->interaccion($c, $this->hace(200));

        $this->detector->detectar();
        $this->concluirOportunidadesDe($c);

        // Sin novedades: no debe reaparecer.
        $this->detector->detectar();
        $this->assertSame(1, $this->cuantasOportunidadesDe($c), 'Sin movimiento nuevo no debe reaparecer');

        // La relación se movió... y volvió a enfriarse.
        $this->interaccion($c, $this->hace(90));
        $this->detector->detectar();

        $this->assertSame(2, $this->cuantasOportunidadesDe($c), 'Tras moverse y enfriarse debe volver a entrar');
    }

    /*
    |--------------------------------------------------------------------------
    | Datos que la oportunidad arrastra
    |--------------------------------------------------------------------------
    */

    /** La oportunidad nace con empresa, responsable y monto histórico resueltos. */
    public function test_la_oportunidad_arrastra_empresa_responsable_y_monto()
    {
        $usuario = DB::connection('tenant')->table('users')->orderBy('id')->value('id');
        $c = $this->contactoActivo(['responsable_comercial_id' => $usuario]);
        $r = $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);
        $this->cotizacion($r, Com07DetectorService::COTIZACION_CAIDA, 12500.50);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o->empresa_id);
        $this->assertSame((int) $usuario, (int) $o->responsable_comercial_id);
        $this->assertSame('12500.50', $o->monto_historico);
    }

    /** Sin responsable, la oportunidad existe igual y queda pendiente de asignación. */
    public function test_sin_responsable_queda_pendiente_de_asignacion()
    {
        $c = $this->contactoActivo();
        $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $this->detector->detectar();

        $o = $this->oportunidadDe($c);
        $this->assertNotNull($o, 'Debe existir aunque no tenga dueño');
        $this->assertNull($o->responsable_comercial_id);
    }

    /** La simulación calcula todo pero no escribe. */
    public function test_la_simulacion_no_escribe()
    {
        $c = $this->contactoActivo();
        $this->requerimiento($c, ['etapa' => Com07DetectorService::ETAPA_COTIZACION_CAIDA]);

        $resumen = $this->detector->detectar(true);

        $this->assertGreaterThan(0, $resumen['creadas'], 'Debe reportar lo que crearía');
        $this->assertNull($this->oportunidadDe($c), 'Pero no debe escribir nada');
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

    private function hace($dias, $formato = 'Y-m-d H:i:s')
    {
        return now()->subDays($dias)->format($formato);
    }

    private function oportunidadDe($contactoId)
    {
        return $this->tabla('com07_oportunidades')
            ->where('contacto_id', $contactoId)
            ->orderByDesc('id')
            ->first();
    }

    private function cuantasOportunidadesDe($contactoId)
    {
        return (int) $this->tabla('com07_oportunidades')->where('contacto_id', $contactoId)->count();
    }

    private function concluirOportunidadesDe($contactoId)
    {
        $this->tabla('com07_oportunidades')
            ->where('contacto_id', $contactoId)
            ->update(['estado' => Com07Oportunidad::ESTADO_CONCLUIDA]);
    }

    /**
     * Contacto nuevo. Se crea con fecha de hoy a propósito: así no dispara la
     * inactividad por sí solo y cada prueba controla qué lo hace entrar.
     *
     * @return int
     */
    private function contactoActivo(array $attrs = [])
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST DETECCION',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId(array_merge([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto Deteccion',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
        $this->contactoIds[] = $contactoId;

        return $contactoId;
    }

    /** @return int */
    private function requerimiento($contactoId, array $attrs = [])
    {
        $empresaId = $this->tabla('contacto')->where('id', $contactoId)->value('empresa_id');

        $id = $this->tabla('requerimiento')->insertGetId(array_merge([
            'empresa_id'  => $empresaId,
            'contacto_id' => $contactoId,
            'titulo'      => 'REQ test deteccion',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $attrs));
        $this->requerimientoIds[] = $id;

        return $id;
    }

    private function cotizacion($requerimientoId, $estado, $monto)
    {
        $this->tabla('cotizaciones_requerimiento')->insert([
            'requerimiento_id' => $requerimientoId,
            'version'          => 'V1',
            'monto'            => $monto,
            'estado'           => $estado,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function proyecto($contactoId, $estado)
    {
        $empresaId = $this->tabla('contacto')->where('id', $contactoId)->value('empresa_id');

        $id = $this->tabla('proyecto')->insertGetId([
            'empresa_id'  => $empresaId,
            'contacto_id' => $contactoId,
            'codigo'      => 'TEST-' . random_int(100000, 999999),
            'nombre'      => 'Proyecto test deteccion',
            'estado'      => $estado,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->proyectoIds[] = $id;

        return $id;
    }

    private function interaccion($contactoId, $fecha)
    {
        $this->tabla('contacto_interaccion')->insert([
            'contacto_id' => $contactoId,
            'canal'       => 'Teléfono',
            'resumen'     => 'Prueba de detección',
            'created_at'  => $fecha,
            'updated_at'  => $fecha,
        ]);
    }
}

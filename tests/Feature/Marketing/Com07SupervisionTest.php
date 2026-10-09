<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Gestion;
use Modules\Marketing\Models\Com07Oportunidad;
use Tests\TestCase;

/**
 * Com07SupervisionTest — Vista de Supervisión Comercial (COM-07, Pantalla 3).
 *
 * La razón de ser de esta vista es lo que el panel oculta: acá se ven TODAS
 * las carteras y, sobre todo, las oportunidades pendientes de asignación, que
 * no aparecen en el panel de nadie. Si esta vista no las mostrara, serían
 * invisibles para todo el mundo.
 *
 * El tenant local tiene datos reales: las pruebas comparan contra sus propios
 * registros, no contra totales absolutos.
 */
class Com07SupervisionTest extends TestCase
{
    private $usuarioA;
    private $usuarioB;

    private $contactoIds    = [];
    private $empresaIds     = [];
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

        $usuarios = DB::connection('tenant')->table('users')->orderBy('id')->limit(2)->pluck('id')->all();
        if (count($usuarios) < 2) {
            $this->markTestSkipped('Se necesitan al menos 2 usuarios en el tenant');
        }
        list($this->usuarioA, $this->usuarioB) = array_map('intval', $usuarios);
    }

    protected function tearDown(): void
    {
        try {
            $t = function ($tabla) { return DB::connection('tenant')->table($tabla); };
            if ($this->oportunidadIds) {
                $t('com07_gestiones')->whereIn('oportunidad_id', $this->oportunidadIds)->delete();
                $t('com07_oportunidades')->whereIn('id', $this->oportunidadIds)->delete();
            }
            if ($this->contactoIds) {
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
    | Lo que el panel oculta, acá se ve
    |--------------------------------------------------------------------------
    */

    /** Supervisión muestra las carteras de todas las ejecutivas juntas. */
    public function test_muestra_todas_las_carteras()
    {
        $deA = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $deB = $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        $ids = $this->idsDeSupervision();

        $this->assertContains($deA, $ids);
        $this->assertContains($deB, $ids);
    }

    /** Las pendientes de asignación son visibles — solo acá existen. */
    public function test_muestra_las_pendientes_de_asignacion()
    {
        $sinDuenio = $this->oportunidad(['responsable_comercial_id' => null]);

        $fila = $this->filaDeSupervision($sinDuenio);

        $this->assertNull($fila['ejecutiva']);
        $this->assertSame('pendiente_asignacion', $fila['seguimiento']);
        $this->assertSame('Pendiente de asignación', $fila['seguimiento_label']);
    }

    /** Las concluidas ya no son parte del consolidado. */
    public function test_no_muestra_las_concluidas()
    {
        $concluida = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'estado'                   => Com07Oportunidad::ESTADO_CONCLUIDA,
        ]);

        $this->assertNotContains($concluida, $this->idsDeSupervision());
    }

    /*
    |--------------------------------------------------------------------------
    | Contadores
    |--------------------------------------------------------------------------
    */

    /** Los cuatro contadores de la maqueta cuentan lo que deben. */
    public function test_los_contadores_reflejan_lo_creado()
    {
        $antes = $this->resumenDeSupervision();

        $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->addDays(3)->toDateString(),   // pendiente
        ]);
        $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->subDays(2)->toDateString(),   // vencido
        ]);
        $this->oportunidad(['responsable_comercial_id' => null]);              // sin asignar

        $despues = $this->resumenDeSupervision();

        $this->assertSame($antes['activas'] + 3, $despues['activas']);
        $this->assertSame($antes['seguimientos_pendientes'] + 1, $despues['seguimientos_pendientes']);
        $this->assertSame($antes['seguimientos_vencidos'] + 1, $despues['seguimientos_vencidos']);
        $this->assertSame($antes['pendientes_asignacion'] + 1, $despues['pendientes_asignacion']);
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    public function test_filtra_por_ejecutiva()
    {
        $deA = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $deB = $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        $ids = $this->idsDeSupervision(['ejecutiva' => (string) $this->usuarioA]);

        $this->assertContains($deA, $ids);
        $this->assertNotContains($deB, $ids);
    }

    public function test_filtra_las_sin_asignar()
    {
        $sinDuenio = $this->oportunidad(['responsable_comercial_id' => null]);
        $conDuenio = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $ids = $this->idsDeSupervision(['ejecutiva' => 'sin_asignar']);

        $this->assertContains($sinDuenio, $ids);
        $this->assertNotContains($conDuenio, $ids);
    }

    /** El filtro de seguimiento acepta también "pendiente de asignación". */
    public function test_filtra_por_pendiente_de_asignacion()
    {
        $sinDuenio = $this->oportunidad(['responsable_comercial_id' => null]);
        $vencida   = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->subDays(4)->toDateString(),
        ]);

        $ids = $this->idsDeSupervision(['seguimiento' => 'pendiente_asignacion']);
        $this->assertContains($sinDuenio, $ids);
        $this->assertNotContains($vencida, $ids);

        $vencidas = $this->idsDeSupervision(['seguimiento' => 'vencido']);
        $this->assertContains($vencida, $vencidas);
        $this->assertNotContains($sinDuenio, $vencidas);
    }

    /*
    |--------------------------------------------------------------------------
    | Columnas propias de esta vista
    |--------------------------------------------------------------------------
    */

    /** La última gestión se muestra como "fecha · resultado". */
    public function test_la_fila_trae_la_ultima_gestion_y_los_dias_de_atraso()
    {
        $id = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->subDays(5)->toDateString(),
        ]);

        DB::connection('tenant')->table('com07_gestiones')->insert([
            'oportunidad_id' => $id,
            'user_id'        => $this->usuarioA,
            'resultado'      => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'created_at'     => now()->subDays(5),
            'updated_at'     => now()->subDays(5),
        ]);

        $fila = $this->filaDeSupervision($id);

        $this->assertSame(now()->subDays(5)->format('d/m/Y') . ' · Sin respuesta', $fila['ultima_gestion']);
        $this->assertSame(5, $fila['dias_atraso']);
        $this->assertSame('Vencido', $fila['seguimiento_label']);
        $this->assertNotNull($fila['ejecutiva']);
    }

    /** Nunca gestionada: última gestión vacía y sin días de atraso. */
    public function test_sin_gestiones_la_ultima_gestion_queda_vacia()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $fila = $this->filaDeSupervision($id);

        $this->assertNull($fila['ultima_gestion']);
        $this->assertSame(0, $fila['dias_atraso']);
    }

    /** El catálogo de ejecutivas lista a quienes tienen cartera vigente. */
    public function test_el_catalogo_de_ejecutivas_lista_a_quienes_tienen_cartera()
    {
        $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        $respuesta = $this->respuestaDeSupervision();
        $ids = array_map(function ($e) { return (int) $e['id']; }, $respuesta['ejecutivas']);

        $this->assertContains($this->usuarioB, $ids);
    }

    /*
    |--------------------------------------------------------------------------
    | Apoyo
    |--------------------------------------------------------------------------
    */

    private function tabla($t)
    {
        return DB::connection('tenant')->table($t);
    }

    private function respuestaDeSupervision(array $filtros = [])
    {
        $this->actingAs(\App\Models\Tenant\User::find($this->usuarioA));
        $filtros['por_pagina'] = 500;   // el tenant tiene datos reales; traer todo
        $peticion    = new \Illuminate\Http\Request($filtros);
        $controlador = new \Modules\Marketing\Http\Controllers\Api\Com07SupervisionController();

        return json_decode($controlador->index($peticion)->getContent(), true);
    }

    private function idsDeSupervision(array $filtros = [])
    {
        $filas = $this->respuestaDeSupervision($filtros)['datos'];

        return array_map(function ($f) { return (int) $f['id']; }, $filas);
    }

    private function filaDeSupervision($oportunidadId)
    {
        foreach ($this->respuestaDeSupervision()['datos'] as $fila) {
            if ((int) $fila['id'] === (int) $oportunidadId) {
                return $fila;
            }
        }

        $this->fail("La oportunidad {$oportunidadId} no apareció en la supervisión");
    }

    private function resumenDeSupervision()
    {
        return $this->respuestaDeSupervision()['resumen'];
    }

    /** @return int id de la oportunidad */
    private function oportunidad(array $attrs = [])
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST SUPERVISION',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto',
            'apellidos'  => 'Supervision',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->contactoIds[] = $contactoId;

        $id = $this->tabla('com07_oportunidades')->insertGetId(array_merge([
            'contacto_id'         => $contactoId,
            'empresa_id'          => $empresaId,
            'origen'              => Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO,
            'estado'              => Com07Oportunidad::ESTADO_PENDIENTE,
            'ultima_actividad_at' => now()->subDays(120)->toDateString(),
            'detectada_at'        => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ], $attrs));
        $this->oportunidadIds[] = $id;

        return $id;
    }
}

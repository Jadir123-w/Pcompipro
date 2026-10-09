<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;
use Tests\TestCase;

/**
 * Com07PanelTest — Panel de Trabajo de la ejecutiva (COM-07).
 *
 * Lo que más importa acá es el aislamiento: cada ejecutiva ve SU cartera y solo
 * la suya, y las oportunidades sin dueño no aparecen en el panel de nadie —
 * mostrarlas sería asignárselas sin que nadie lo decida.
 *
 * Después, que el orden de la maqueta se cumpla: lo vencido primero, porque es
 * un compromiso ya asumido con el cliente y no cumplido.
 */
class Com07PanelTest extends TestCase
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
    | Aislamiento de cartera
    |--------------------------------------------------------------------------
    */

    /** Cada ejecutiva ve solo lo suyo. */
    public function test_solo_muestra_la_cartera_del_usuario_conectado()
    {
        $mia   = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $ajena = $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        $ids = $this->idsDelPanel($this->usuarioA);

        $this->assertContains($mia, $ids);
        $this->assertNotContains($ajena, $ids, 'No debe ver la cartera de otra ejecutiva');
    }

    /** Las oportunidades sin dueño no entran al panel de nadie. */
    public function test_no_muestra_las_pendientes_de_asignacion()
    {
        $sinDuenio = $this->oportunidad(['responsable_comercial_id' => null]);

        $this->assertNotContains($sinDuenio, $this->idsDelPanel($this->usuarioA));
        $this->assertNotContains($sinDuenio, $this->idsDelPanel($this->usuarioB));
    }

    /** Una oportunidad concluida ya no es trabajo por hacer. */
    public function test_no_muestra_las_concluidas()
    {
        $concluida = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'estado'                   => Com07Oportunidad::ESTADO_CONCLUIDA,
        ]);

        $this->assertNotContains($concluida, $this->idsDelPanel($this->usuarioA));
    }

    /*
    |--------------------------------------------------------------------------
    | Orden contractual: Prioridad Comercial con sus desempates
    |--------------------------------------------------------------------------
    */

    /** La Prioridad Comercial es el criterio principal de ordenamiento. */
    public function test_ordena_por_prioridad_comercial()
    {
        $baja = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => 2.4,
            'potencial_puntaje'        => 6,
        ]);
        $alta = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => 7.6,
            'potencial_puntaje'        => 4,
            'urgencia_puntaje'         => 10,
        ]);

        $ids = $this->idsDelPanel($this->usuarioA);

        $this->assertLessThan(
            array_search($baja, $ids, true),
            array_search($alta, $ids, true),
            'La mayor Prioridad debe encabezar la lista'
        );
    }

    /** Empate de Prioridad: gana la mayor Urgencia (primer desempate del contrato). */
    public function test_el_empate_lo_resuelve_la_urgencia()
    {
        $sinUrgencia = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => 4.0,
            'potencial_puntaje'        => 10,
            'urgencia_puntaje'         => null,   // No aplica
        ]);
        $conUrgencia = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => 4.0,
            'potencial_puntaje'        => 1,
            'urgencia_puntaje'         => 6,
        ]);

        $ids = $this->idsDelPanel($this->usuarioA);

        $this->assertLessThan(
            array_search($sinUrgencia, $ids, true),
            array_search($conUrgencia, $ids, true),
            'A igual Prioridad, la necesidad actual va primero'
        );
    }

    /** Las aún no calculadas van al final, no arriba. */
    public function test_las_sin_calcular_van_al_final()
    {
        $calculada = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => 1.2,
            'potencial_puntaje'        => 3,
        ]);
        $sinCalcular = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'prioridad_puntaje'        => null,
        ]);

        $ids = $this->idsDelPanel($this->usuarioA);

        $this->assertLessThan(
            array_search($sinCalcular, $ids, true),
            array_search($calculada, $ids, true)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    public function test_filtra_por_origen()
    {
        $caida = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'origen'                   => Com07Oportunidad::ORIGEN_COTIZACION_CAIDA,
        ]);
        $sinMovimiento = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'origen'                   => Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO,
        ]);

        $ids = $this->idsDelPanel($this->usuarioA, ['origen' => Com07Oportunidad::ORIGEN_COTIZACION_CAIDA]);

        $this->assertContains($caida, $ids);
        $this->assertNotContains($sinMovimiento, $ids);
    }

    /** El rango de antigüedad se deriva de la fecha, y el filtro debe respetarlo. */
    public function test_filtra_por_rango_de_antiguedad()
    {
        $reciente = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'ultima_actividad_at'      => $this->hace(60),    // < 4 meses
        ]);
        $antigua = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'ultima_actividad_at'      => $this->hace(300),   // > 6 meses
        ]);

        $ids = $this->idsDelPanel($this->usuarioA, ['antiguedad' => Com07Oportunidad::RANGO_ANTIGUO]);

        $this->assertContains($antigua, $ids);
        $this->assertNotContains($reciente, $ids);
    }

    public function test_filtra_por_condicion_de_seguimiento()
    {
        $vencida = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->subDays(2)->toDateString(),
        ]);
        $sinSeguimiento = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => null,
        ]);

        $vencidas = $this->idsDelPanel($this->usuarioA, ['seguimiento' => 'vencido']);
        $this->assertContains($vencida, $vencidas);
        $this->assertNotContains($sinSeguimiento, $vencidas);

        $sin = $this->idsDelPanel($this->usuarioA, ['seguimiento' => 'sin_seguimiento']);
        $this->assertContains($sinSeguimiento, $sin);
        $this->assertNotContains($vencida, $sin);
    }

    /** Se busca por empresa o por contacto, que es como la ejecutiva los recuerda. */
    public function test_busca_por_empresa_y_por_contacto()
    {
        $buscada = $this->oportunidad(
            ['responsable_comercial_id' => $this->usuarioA],
            ['razon_social' => 'CONSTRUCTORA HORIZONTE SAC'],
            ['nombres' => 'Rosamel', 'apellidos' => 'Quispe']
        );
        $otra = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $porEmpresa = $this->idsDelPanel($this->usuarioA, ['buscar' => 'HORIZONTE']);
        $this->assertContains($buscada, $porEmpresa);
        $this->assertNotContains($otra, $porEmpresa);

        $porContacto = $this->idsDelPanel($this->usuarioA, ['buscar' => 'Rosamel']);
        $this->assertContains($buscada, $porContacto);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentación
    |--------------------------------------------------------------------------
    */

    /** La fila trae las columnas de la maqueta más la Prioridad, con etiquetas resueltas. */
    public function test_la_fila_trae_las_columnas_de_la_maqueta()
    {
        $id = $this->oportunidad(
            [
                'responsable_comercial_id' => $this->usuarioA,
                'origen'                   => Com07Oportunidad::ORIGEN_COTIZACION_CAIDA,
                'ultima_actividad_at'      => $this->hace(300),
                'monto_historico'          => 18450.00,
                'proxima_atencion_at'      => now()->subDays(4)->toDateString(),
                'potencial_puntaje'        => 6,
                'urgencia_puntaje'         => 9,
                'prioridad_puntaje'        => 7.8,
            ],
            ['razon_social' => 'ACCIONA PERU SAC'],
            ['nombres' => 'Mariana', 'apellidos' => 'Cortez', 'cargo' => 'Jefa de Compras']
        );

        $fila = $this->filaDelPanel($this->usuarioA, $id);

        $this->assertSame('ACCIONA PERU SAC', $fila['empresa']);
        $this->assertSame('Mariana Cortez', $fila['contacto']);
        $this->assertSame('Jefa de Compras', $fila['cargo']);
        $this->assertSame('Cotización caída', $fila['origen_label']);
        $this->assertSame('Más de 6 meses', $fila['antiguedad_label']);
        $this->assertSame('Vencido', $fila['seguimiento_label']);
        $this->assertSame(4, $fila['dias_atraso']);
        $this->assertSame(now()->subDays(300)->format('d/m/Y'), $fila['ultima_actividad_at']);
        $this->assertSame(7.8, $fila['prioridad_puntaje']);
        $this->assertSame('Alta', $fila['prioridad_label']);
    }

    /** Sin seguimiento definido, la columna queda vacía y no atrasada. */
    public function test_sin_seguimiento_no_figura_como_vencida()
    {
        $id = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => null,
        ]);

        $fila = $this->filaDelPanel($this->usuarioA, $id);

        $this->assertSame('sin_seguimiento', $fila['seguimiento']);
        $this->assertNull($fila['proxima_atencion_at']);
        $this->assertSame(0, $fila['dias_atraso']);
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

    private function hace($dias)
    {
        return now()->subDays($dias)->toDateString();
    }

    /** Consulta el panel como un usuario y devuelve los ids en orden. */
    private function idsDelPanel($usuarioId, array $filtros = [])
    {
        $filas = $this->respuestaDelPanel($usuarioId, $filtros);

        return array_map(function ($f) { return (int) $f['id']; }, $filas);
    }

    private function filaDelPanel($usuarioId, $oportunidadId)
    {
        foreach ($this->respuestaDelPanel($usuarioId) as $fila) {
            if ((int) $fila['id'] === (int) $oportunidadId) {
                return $fila;
            }
        }

        $this->fail("La oportunidad {$oportunidadId} no apareció en el panel");
    }

    private function respuestaDelPanel($usuarioId, array $filtros = [])
    {
        $usuario = \App\Models\Tenant\User::find($usuarioId);
        $this->actingAs($usuario);

        $peticion   = new \Illuminate\Http\Request($filtros);
        $controlador = new \Modules\Marketing\Http\Controllers\Api\Com07PanelController();
        $respuesta   = $controlador->index($peticion);

        $cuerpo = json_decode($respuesta->getContent(), true);

        return $cuerpo['datos'];
    }

    /** @return int id de la oportunidad creada */
    private function oportunidad(array $attrs = [], array $empresaAttrs = [], array $contactoAttrs = [])
    {
        $empresaId = $this->tabla('empresa')->insertGetId(array_merge([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST PANEL',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $empresaAttrs));
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId(array_merge([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto',
            'apellidos'  => 'Panel',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $contactoAttrs));
        $this->contactoIds[] = $contactoId;

        $id = $this->tabla('com07_oportunidades')->insertGetId(array_merge([
            'contacto_id'         => $contactoId,
            'empresa_id'          => $empresaId,
            'origen'              => Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO,
            'estado'              => Com07Oportunidad::ESTADO_PENDIENTE,
            'ultima_actividad_at' => $this->hace(120),
            'detectada_at'        => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ], $attrs));
        $this->oportunidadIds[] = $id;

        return $id;
    }
}

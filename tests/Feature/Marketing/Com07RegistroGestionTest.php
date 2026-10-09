<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Marketing\Models\Com07Gestion;
use Modules\Marketing\Models\Com07Oportunidad;
use Tests\TestCase;

/**
 * Com07RegistroGestionTest — Registro de la gestión (COM-07, Pantalla 2).
 *
 * Los efectos importan más que el registro en sí: la fecha del seguimiento
 * pasa a ser la próxima atención, el resultado decide si la oportunidad sigue
 * viva, la reprogramación conserva la fecha anterior, y la gestión queda
 * también en el historial del contacto con su referencia de origen — todo eso
 * es lo comprometido con Compipro en la Especificación y la Conformidad.
 */
class Com07RegistroGestionTest extends TestCase
{
    private $usuarioA;
    private $usuarioB;

    private $contactoIds    = [];
    private $empresaIds     = [];
    private $oportunidadIds = [];
    private $requerimientoIds = [];

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
    | El modal (show)
    |--------------------------------------------------------------------------
    */

    /** El contexto trae lo que la ejecutiva necesita antes de llamar. */
    public function test_el_modal_trae_contexto_antecedentes_y_resultados()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $o  = Com07Oportunidad::find($id);

        $this->requerimientoDeEmpresa($o->empresa_id, [
            'titulo' => 'Libretas ecológicas',
            'etapa'  => 'Cotización caída',
        ], 18450.00);
        $this->requerimientoDeEmpresa($o->empresa_id, [
            'titulo'              => 'Polos corporativos',
            'escalado_a_proyecto' => 1,
        ], 7600.00);

        $cuerpo = $this->abrirModal($this->usuarioA, $id);

        $this->assertSame('EMPRESA TEST GESTION', $cuerpo['contexto']['empresa']);
        $this->assertSame('Contacto Gestion', $cuerpo['contexto']['contacto']);
        $this->assertCount(6, $cuerpo['resultados']);

        $this->assertCount(2, $cuerpo['antecedentes']);
        $antecedentes = collect($cuerpo['antecedentes'])->keyBy('situacion');
        $this->assertStringStartsWith('REQ-', $antecedentes['Cotización caída']['antecedente']);
        $this->assertContains('Libretas ecológicas', $antecedentes['Cotización caída']['antecedente']);
        $this->assertEquals(18450.00, $antecedentes['Cotización caída']['monto']);
        $this->assertArrayHasKey('Convertido a proyecto', $antecedentes, 'El escalado debe mostrarse como convertido');
    }

    /** El modal muestra la Prioridad con su sustento — lo comprometido en la Conformidad. */
    public function test_el_modal_trae_el_sustento_de_la_prioridad()
    {
        $id = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'potencial_puntaje'        => 7,
            'urgencia_puntaje'         => null,   // No aplica
            'prioridad_puntaje'        => 2.8,
            'prioridad_sustento'       => json_encode([
                'potencial' => [['factor' => 'Historial comercial por monto', 'valor' => 'S/ 5,000.00', 'puntos' => 3]],
                'urgencia'  => null,
            ]),
        ]);

        $cuerpo = $this->abrirModal($this->usuarioA, $id);

        $this->assertSame(2.8, $cuerpo['prioridad']['puntaje']);
        $this->assertSame('Baja', $cuerpo['prioridad']['nivel_label']);
        $this->assertSame('No aplica', $cuerpo['prioridad']['urgencia_label']);
        $this->assertNull($cuerpo['prioridad']['factores']['urgencia']);
        $this->assertSame(3, $cuerpo['prioridad']['factores']['potencial'][0]['puntos']);
    }

    /** Antes de la corrida diaria no hay Prioridad, y el modal no revienta. */
    public function test_sin_calculo_el_modal_no_trae_prioridad()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $cuerpo = $this->abrirModal($this->usuarioA, $id);

        $this->assertNull($cuerpo['prioridad']);
    }

    /** La oportunidad de otra ejecutiva "no existe" para este usuario. */
    public function test_no_se_puede_abrir_una_oportunidad_ajena()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        try {
            $this->abrirModal($this->usuarioA, $id);
            $this->fail('Debía rechazarse con 404');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Efectos de guardar (store)
    |--------------------------------------------------------------------------
    */

    /** La fecha del seguimiento pasa a ser la próxima atención, y el estado a en gestión. */
    public function test_guardar_con_seguimiento_programa_la_proxima_atencion()
    {
        $id    = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $fecha = now()->addDays(6)->toDateString();

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_EXISTE_INTERES,
            'proxima_accion'       => 'Enviar propuesta actualizada',
            'observacion'          => 'Solicita retomar la cotización.',
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => $fecha,
        ]);

        $o = Com07Oportunidad::find($id);
        $this->assertSame(Com07Oportunidad::ESTADO_EN_GESTION, $o->estado);
        $this->assertSame($fecha, $o->proxima_atencion_at->toDateString());

        $gestion = $o->gestiones()->first();
        $this->assertSame($this->usuarioA, (int) $gestion->user_id);
        $this->assertSame(Com07Gestion::SEG_PENDIENTE, $gestion->seguimiento_estado);
    }

    /** "Gestión concluida" sin seguimiento cierra la oportunidad y la saca del panel. */
    public function test_concluir_sin_seguimiento_cierra_la_oportunidad()
    {
        $id = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'proxima_atencion_at'      => now()->addDays(3)->toDateString(),
        ]);

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_CONCLUIDA,
            'requiere_seguimiento' => 0,
        ]);

        $o = Com07Oportunidad::find($id);
        $this->assertSame(Com07Oportunidad::ESTADO_CONCLUIDA, $o->estado);
        $this->assertNull($o->proxima_atencion_at, 'Concluida no debe dejar atención colgada');
    }

    /** El mismo resultado CON seguimiento mantiene viva la gestión. */
    public function test_concluir_con_seguimiento_no_cierra()
    {
        $id    = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $fecha = now()->addDays(10)->toDateString();

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_CONCLUIDA,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => $fecha,
        ]);

        $this->assertSame(Com07Oportunidad::ESTADO_EN_GESTION, Com07Oportunidad::find($id)->estado);
    }

    /**
     * Reprogramar conserva la fecha anterior (punto 10 de la Especificación) y
     * atiende el seguimiento que estaba pendiente.
     */
    public function test_reprogramar_conserva_la_fecha_anterior_y_atiende_el_pendiente()
    {
        $id     = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $fecha1 = now()->addDays(2)->toDateString();
        $fecha2 = now()->addDays(9)->toDateString();

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => $fecha1,
        ]);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_NUEVO_SEGUIMIENTO,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => $fecha2,
        ]);

        $o = Com07Oportunidad::find($id);
        // Directo al modelo: la relación gestiones() trae su propio orden
        // descendente y un orderBy adicional no lo reemplaza.
        $gestiones = Com07Gestion::where('oportunidad_id', $id)->orderBy('id')->get();

        $this->assertSame(Com07Gestion::SEG_ATENDIDO, $gestiones[0]->seguimiento_estado, 'La segunda gestión atiende a la primera');
        $this->assertSame($fecha1, $gestiones[1]->seguimiento_at_anterior->toDateString(), 'La fecha anterior debe conservarse');
        $this->assertSame($fecha2, $o->proxima_atencion_at->toDateString());
    }

    /** La gestión queda en el historial del contacto con su referencia de origen. */
    public function test_la_gestion_queda_en_el_historial_del_contacto()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $contactoId = Com07Oportunidad::find($id)->contacto_id;

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_EXISTE_INTERES,
            'observacion'          => 'Retomar en fin de año.',
            'requiere_seguimiento' => 0,
        ]);

        $interaccion = DB::connection('tenant')->table('contacto_interaccion')
            ->where('contacto_id', $contactoId)
            ->where('canal', 'COM-07')
            ->first();

        $this->assertNotNull($interaccion, 'Debe centralizarse en contacto_interaccion');
        $this->assertContains('Existe interés', $interaccion->resumen);
        $this->assertContains('Retomar en fin de año', $interaccion->resumen);
        $this->assertSame($this->usuarioA, (int) $interaccion->user_id);
    }

    /** Registrar una gestión es actividad comercial: la antigüedad parte de hoy. */
    public function test_la_gestion_actualiza_la_ultima_actividad()
    {
        $id = $this->oportunidad([
            'responsable_comercial_id' => $this->usuarioA,
            'ultima_actividad_at'      => now()->subDays(200)->toDateString(),
        ]);

        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 0,
        ]);

        $this->assertSame(
            now()->toDateString(),
            Com07Oportunidad::find($id)->ultima_actividad_at->toDateString()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cancelación del seguimiento (Especificación punto 10)
    |--------------------------------------------------------------------------
    */

    /**
     * Cancelar conserva el motivo y NO cierra la oportunidad: el compromiso
     * concreto dejó de aplicar, pero el cliente sigue siendo recuperable.
     */
    public function test_cancelar_el_seguimiento_conserva_el_motivo_y_la_oportunidad()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => now()->addDays(7)->toDateString(),
        ]);

        $this->cancelar($this->usuarioA, $id, ['motivo' => 'El cliente compró a otro proveedor.']);

        $o = Com07Oportunidad::find($id);
        $this->assertSame(Com07Oportunidad::ESTADO_EN_GESTION, $o->estado, 'Cancelar no debe cerrar la oportunidad');
        $this->assertNull($o->proxima_atencion_at, 'No debe quedar una fecha comprometida');

        $gestion = Com07Gestion::where('oportunidad_id', $id)->orderByDesc('id')->first();
        $this->assertSame(Com07Gestion::SEG_CANCELADO, $gestion->seguimiento_estado);
        $this->assertSame('El cliente compró a otro proveedor.', $gestion->seguimiento_motivo);
        $this->assertNotNull($gestion->seguimiento_at, 'La fecha original se conserva en el historial');
    }

    /** La cancelación queda en el historial del contacto. */
    public function test_cancelar_queda_en_el_historial_del_contacto()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $contactoId = Com07Oportunidad::find($id)->contacto_id;
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => now()->addDays(5)->toDateString(),
        ]);

        $this->cancelar($this->usuarioA, $id, ['motivo' => 'Proyecto postergado.']);

        $interaccion = DB::connection('tenant')->table('contacto_interaccion')
            ->where('contacto_id', $contactoId)
            ->where('resumen', 'like', '%Seguimiento cancelado%')
            ->first();

        $this->assertNotNull($interaccion);
        $this->assertContains('Proyecto postergado', $interaccion->resumen);
    }

    /** Sin seguimiento vigente no hay nada que cancelar. */
    public function test_no_se_puede_cancelar_sin_seguimiento_vigente()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 0,
        ]);

        $respuesta = $this->cancelar($this->usuarioA, $id, ['motivo' => 'Ya no aplica.']);

        $this->assertFalse($respuesta['ok']);
    }

    public function test_cancelar_exige_motivo()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => now()->addDays(3)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->cancelar($this->usuarioA, $id, []);
    }

    /*
    |--------------------------------------------------------------------------
    | Historial y enlaces en el modal
    |--------------------------------------------------------------------------
    */

    /** El modal muestra las gestiones previas para retomar sin repetir lo intentado. */
    public function test_el_modal_trae_el_historial_de_gestiones()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'observacion'          => 'No contestó el teléfono.',
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => now()->addDays(4)->toDateString(),
        ]);

        $cuerpo = $this->abrirModal($this->usuarioA, $id);

        $this->assertCount(1, $cuerpo['gestiones']);
        $this->assertSame('Sin respuesta', $cuerpo['gestiones'][0]['resultado']);
        $this->assertSame('No contestó el teléfono.', $cuerpo['gestiones'][0]['observacion']);
        $this->assertSame('Pendiente', $cuerpo['gestiones'][0]['seguimiento_label']);
        $this->assertNotNull($cuerpo['seguimiento'], 'Debe reportar el seguimiento vigente');
    }

    /** El modal trae los enlaces a los registros del ERP relacionados. */
    public function test_el_modal_trae_los_enlaces_relacionados()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $cuerpo = $this->abrirModal($this->usuarioA, $id);

        $this->assertContains('comercial/contactos', $cuerpo['enlaces']['contacto']);
        $this->assertContains('comercial/empresas', $cuerpo['enlaces']['empresa']);
        $this->assertNull($cuerpo['enlaces']['requerimiento'], 'Sin requerimiento no debe ofrecer enlace');
    }

    /*
    |--------------------------------------------------------------------------
    | Validaciones y aislamiento
    |--------------------------------------------------------------------------
    */

    public function test_rechaza_un_resultado_desconocido()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $this->expectException(ValidationException::class);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => 'cliente_feliz',
            'requiere_seguimiento' => 0,
        ]);
    }

    public function test_rechaza_seguimiento_sin_fecha()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $this->expectException(ValidationException::class);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
        ]);
    }

    public function test_rechaza_una_fecha_en_el_pasado()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioA]);

        $this->expectException(ValidationException::class);
        $this->guardar($this->usuarioA, $id, [
            'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
            'requiere_seguimiento' => 1,
            'seguimiento_at'       => now()->subDays(2)->toDateString(),
        ]);
    }

    public function test_no_se_puede_gestionar_una_oportunidad_ajena()
    {
        $id = $this->oportunidad(['responsable_comercial_id' => $this->usuarioB]);

        try {
            $this->guardar($this->usuarioA, $id, [
                'resultado'            => Com07Gestion::RESULTADO_SIN_RESPONDER,
                'requiere_seguimiento' => 0,
            ]);
            $this->fail('Debía rechazarse con 404');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame(0, (int) DB::connection('tenant')->table('com07_gestiones')->where('oportunidad_id', $id)->count());
        }
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

    private function abrirModal($usuarioId, $oportunidadId)
    {
        $this->actingAs(\App\Models\Tenant\User::find($usuarioId));
        $controlador = new \Modules\Marketing\Http\Controllers\Api\Com07GestionController();

        return json_decode($controlador->show($oportunidadId)->getContent(), true);
    }

    private function cancelar($usuarioId, $oportunidadId, array $datos)
    {
        $this->actingAs(\App\Models\Tenant\User::find($usuarioId));
        $peticion = new \Illuminate\Http\Request();
        $peticion->setMethod('POST');
        $peticion->replace($datos);
        app()->instance('request', $peticion);

        $controlador = new \Modules\Marketing\Http\Controllers\Api\Com07GestionController();

        return json_decode($controlador->cancelarSeguimiento($peticion, $oportunidadId)->getContent(), true);
    }

    private function guardar($usuarioId, $oportunidadId, array $datos)
    {
        $this->actingAs(\App\Models\Tenant\User::find($usuarioId));
        $peticion = new \Illuminate\Http\Request();
        $peticion->setMethod('POST');
        $peticion->replace($datos);
        // La validación de Laravel resuelve reglas contra el request "actual".
        app()->instance('request', $peticion);

        $controlador = new \Modules\Marketing\Http\Controllers\Api\Com07GestionController();

        return json_decode($controlador->store($peticion, $oportunidadId)->getContent(), true);
    }

    /** @return int id de la oportunidad */
    private function oportunidad(array $attrs = [])
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST GESTION',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto',
            'apellidos'  => 'Gestion',
            'cargo'      => 'Compras',
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

    private function requerimientoDeEmpresa($empresaId, array $attrs, $montoCotizacion = null)
    {
        $id = $this->tabla('requerimiento')->insertGetId(array_merge([
            'empresa_id' => $empresaId,
            'titulo'     => 'REQ antecedente',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
        $this->requerimientoIds[] = $id;

        if ($montoCotizacion !== null) {
            $this->tabla('cotizaciones_requerimiento')->insert([
                'requerimiento_id' => $id,
                'version'          => 'V1',
                'monto'            => $montoCotizacion,
                'estado'           => 'Enviada',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        return $id;
    }
}

<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Services\Com07ResponsableResolver;
use Tests\TestCase;

/**
 * Com07ResponsableTest — A qué ejecutiva corresponde cada contacto (COM-07).
 *
 * El criterio tiene tres escalones (Especificación Puntual, punto 4.3): el
 * responsable cargado en el contacto, la última ejecutiva que lo gestionó, y
 * "pendiente de asignación" si no hay forma de saberlo.
 *
 * Lo que más importa probar es que el escalón 1 NO se deje pisar por el 2: la
 * Especificación es explícita en que una interacción puntual de otra ejecutiva
 * no reemplaza al responsable registrado. Si eso falla, las oportunidades
 * cambiarían de dueño solas cada vez que alguien más toca la cuenta.
 *
 * Los usuarios de las pruebas se toman de la propia base: `requerimiento`
 * tiene una foreign key real hacia `users` (fk_requerimiento_usuario_interaccion),
 * así que un id inventado no se puede insertar.
 */
class Com07ResponsableTest extends TestCase
{
    /** @var Com07ResponsableResolver */
    private $resolver;

    /** Tres ejecutivas reales del tenant, para poder distinguirlas entre sí. */
    private $ejecA;
    private $ejecB;
    private $ejecC;

    private $contactoIds      = [];
    private $empresaIds       = [];
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

        $usuarios = DB::connection('tenant')->table('users')->orderBy('id')->limit(3)->pluck('id')->all();
        if (count($usuarios) < 3) {
            $this->markTestSkipped('Se necesitan al menos 3 usuarios en el tenant');
        }
        list($this->ejecA, $this->ejecB, $this->ejecC) = array_map('intval', $usuarios);

        $this->resolver = new Com07ResponsableResolver();
    }

    protected function tearDown(): void
    {
        try {
            $t = function ($tabla) { return DB::connection('tenant')->table($tabla); };
            if ($this->requerimientoIds) {
                $t('requerimiento_interaccion')->whereIn('requerimiento_id', $this->requerimientoIds)->delete();
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
    | Escalón 1 — asignación explícita
    |--------------------------------------------------------------------------
    */

    /** El responsable cargado en el contacto se usa tal cual. */
    public function test_usa_el_responsable_registrado_en_el_contacto()
    {
        $c = $this->contacto(['responsable_comercial_id' => $this->ejecA]);

        $this->assertSame($this->ejecA, $this->resolver->paraContacto($c));
    }

    /**
     * Una interacción puntual de otra ejecutiva NO reemplaza al responsable
     * registrado. Es la regla explícita del punto 4.3 y la que evita que las
     * cuentas cambien de dueño solas.
     */
    public function test_una_interaccion_de_otra_ejecutiva_no_reemplaza_al_responsable()
    {
        $c = $this->contacto(['responsable_comercial_id' => $this->ejecA]);
        $this->interaccionDeContacto($c, $this->ejecB, now());   // otra ejecutiva, hoy mismo

        $this->assertSame($this->ejecA, $this->resolver->paraContacto($c));
    }

    /*
    |--------------------------------------------------------------------------
    | Escalón 2 — respaldo por historial
    |--------------------------------------------------------------------------
    */

    /** Sin responsable registrado, se toma quien registró la última interacción. */
    public function test_respaldo_por_interaccion_del_contacto()
    {
        $c = $this->contacto();
        $this->interaccionDeContacto($c, $this->ejecA, now()->subDays(30));
        $this->interaccionDeContacto($c, $this->ejecC, now()->subDays(5));   // la más reciente

        $this->assertSame($this->ejecC, $this->resolver->paraContacto($c));
    }

    /** También sirve una interacción registrada sobre uno de sus requerimientos. */
    public function test_respaldo_por_interaccion_de_requerimiento()
    {
        $c = $this->contacto();
        $r = $this->requerimiento($c);
        $this->interaccionDeRequerimiento($r, $this->ejecB, now()->subDays(10));

        $this->assertSame($this->ejecB, $this->resolver->paraContacto($c));
    }

    /** Y el usuario de la última interacción que guarda el propio requerimiento. */
    public function test_respaldo_por_ultima_interaccion_del_requerimiento()
    {
        $c = $this->contacto();
        $this->requerimiento($c, [
            'usuario_ultima_interaccion' => $this->ejecC,
            'fecha_ultima_interaccion'   => now()->subDays(20)->toDateTimeString(),
        ]);

        $this->assertSame($this->ejecC, $this->resolver->paraContacto($c));
    }

    /** Entre las tres fuentes gana la más reciente, venga de donde venga. */
    public function test_entre_fuentes_gana_la_mas_reciente()
    {
        $c = $this->contacto();
        // La más antigua es la interacción directa del contacto...
        $this->interaccionDeContacto($c, $this->ejecA, now()->subDays(40));
        // ...y la más reciente viene por el requerimiento.
        $r = $this->requerimiento($c, [
            'usuario_ultima_interaccion' => $this->ejecC,
            'fecha_ultima_interaccion'   => now()->subDays(30)->toDateTimeString(),
        ]);
        $this->interaccionDeRequerimiento($r, $this->ejecB, now()->subDays(3));

        $this->assertSame($this->ejecB, $this->resolver->paraContacto($c));
    }

    /*
    |--------------------------------------------------------------------------
    | Escalón 3 — pendiente de asignación
    |--------------------------------------------------------------------------
    */

    /** Sin responsable ni historial, la oportunidad queda sin dueño. */
    public function test_sin_historial_queda_pendiente_de_asignacion()
    {
        $c = $this->contacto();

        $this->assertNull($this->resolver->paraContacto($c));
    }

    /** Una interacción sin usuario no alcanza para asignar. */
    public function test_interaccion_sin_usuario_no_asigna()
    {
        $c = $this->contacto();
        $this->interaccionDeContacto($c, null, now()->subDays(2));

        $this->assertNull($this->resolver->paraContacto($c));
    }

    /*
    |--------------------------------------------------------------------------
    | Resolución en lote
    |--------------------------------------------------------------------------
    */

    /**
     * El proceso diario resuelve muchos contactos de una pasada: todos deben
     * aparecer en el resultado, incluidos los que quedan sin responsable.
     */
    public function test_resolucion_en_lote()
    {
        $conResponsable = $this->contacto(['responsable_comercial_id' => $this->ejecA]);
        $conHistorial   = $this->contacto();
        $this->interaccionDeContacto($conHistorial, $this->ejecC, now()->subDays(5));
        $sinNada        = $this->contacto();

        $mapa = $this->resolver->paraContactos([$conResponsable, $conHistorial, $sinNada]);

        $this->assertCount(3, $mapa);
        $this->assertSame($this->ejecA, $mapa[$conResponsable]);
        $this->assertSame($this->ejecC, $mapa[$conHistorial]);
        $this->assertNull($mapa[$sinNada]);
    }

    /** Una lista vacía no consulta nada ni revienta. */
    public function test_lote_vacio()
    {
        $this->assertSame([], $this->resolver->paraContactos([]));
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

    /** @return int id del contacto */
    private function contacto(array $attrs = [])
    {
        $empresaId = $this->tabla('empresa')->insertGetId([
            'ruc'          => '20' . random_int(100000000, 999999999),
            'razon_social' => 'EMPRESA TEST COM07',
            'estado'       => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->empresaIds[] = $empresaId;

        $contactoId = $this->tabla('contacto')->insertGetId(array_merge([
            'empresa_id' => $empresaId,
            'nombres'    => 'Contacto Test',
            'estado'     => 1,
            'origen'     => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
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
            'titulo'      => 'REQ test COM07',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $attrs));
        $this->requerimientoIds[] = $id;

        return $id;
    }

    private function interaccionDeContacto($contactoId, $userId, $fecha)
    {
        $this->tabla('contacto_interaccion')->insert([
            'contacto_id' => $contactoId,
            'user_id'     => $userId,
            'canal'       => 'Teléfono',
            'resumen'     => 'Interaccion de prueba',
            'created_at'  => $fecha,
            'updated_at'  => $fecha,
        ]);
    }

    private function interaccionDeRequerimiento($requerimientoId, $userId, $fecha)
    {
        $this->tabla('requerimiento_interaccion')->insert([
            'requerimiento_id' => $requerimientoId,
            'user_id'          => $userId,
            'canal'            => 'Correo',
            'resumen'          => 'Interaccion de prueba',
            'created_at'       => $fecha,
            'updated_at'       => $fecha,
        ]);
    }
}

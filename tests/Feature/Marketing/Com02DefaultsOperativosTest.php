<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Services\QuotationOrchestrator;
use Tests\TestCase;

/**
 * Com02DefaultsOperativosTest — COM-02 S3
 *
 * Verifica que las claves "defop_*" en quotation_config existen y se
 * resuelven correctamente, y que el QuotationOrchestrator usa el
 * normalizeCategoryKey para elegir el bucket correcto.
 */
class Com02DefaultsOperativosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ── DB override (phpunit.xml hardcodea multifacturalo_dusk) ──
        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        \Illuminate\Support\Facades\DB::purge('system');
        \Illuminate\Support\Facades\DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);
    }

    /**
     * Caso 1 — Las 18 claves defop_* del seed están presentes en quotation_config.
     */
    public function test_caso1_seed_contiene_18_claves_defop()
    {
        $count = DB::connection('tenant')->table('quotation_config')
            ->where('config_key', 'LIKE', 'defop\\_%')
            ->count();

        $this->assertGreaterThanOrEqual(18, $count,
            'Deben existir al menos 18 claves defop_* en quotation_config');
    }

    /**
     * Caso 2 — QuotationConfig::getValue resuelve correctamente las claves
     * con su tipo (integer/string/boolean/decimal).
     */
    public function test_caso2_getvalue_devuelve_tipos_correctos()
    {
        // Integer
        $tiempoTextil = QuotationConfig::getValue('defop_tiempo_textil_dias', 0);
        $this->assertGreaterThan(0, (int) $tiempoTextil);

        // String
        $tecnica = QuotationConfig::getValue('defop_tecnica_textil', '');
        $this->assertIsString($tecnica);
        $this->assertNotEmpty($tecnica);

        // Boolean (almacenado como '1'/'0')
        $historicoEnabled = QuotationConfig::getValue('defop_historico_enabled', '0');
        $this->assertContains((string) $historicoEnabled, ['0', '1']);

        // Decimal
        $minScore = QuotationConfig::getValue('defop_historico_min_score', 0.0);
        $this->assertGreaterThanOrEqual(0.0, (float) $minScore);
        $this->assertLessThanOrEqual(1.0, (float) $minScore);
    }

    /**
     * Caso 3 — normalizeCategoryKey mapea correctamente categorías comunes
     * a los buckets esperados. Probamos vía reflection porque es private.
     */
    public function test_caso3_normalize_category_key_mapea_correctamente()
    {
        $orchestrator = app(QuotationOrchestrator::class);
        $reflection = new \ReflectionMethod($orchestrator, 'normalizeCategoryKey');
        $reflection->setAccessible(true);

        $cases = [
            'Textil'           => 'textil',
            'TEXTIL'           => 'textil',
            'Mochilas'         => 'textil',
            'Polos personalizados' => 'textil',
            'Impresos'         => 'impresos',
            'libreta a4'       => 'impresos',
            'Agendas'          => 'impresos',
            'Eco'              => 'eco',
            'Ecológica'        => 'eco',
            'Reciclados'       => 'eco',
            'Bambú'            => 'eco',
            'Packs'            => 'packs',
            'Kit corporativo'  => 'packs',
            'Cualquier cosa'   => 'otros',
            ''                 => 'otros',
            null               => 'otros',
        ];

        foreach ($cases as $input => $expected) {
            $actual = $reflection->invoke($orchestrator, $input);
            $this->assertEquals($expected, $actual,
                "Categoría '{$input}' debería mapear a '{$expected}', dió '{$actual}'");
        }
    }

    /**
     * Caso 4 — Cambiar un valor desde "config" se persiste y vuelve a leerse
     * correctamente (simulando el saveTab del UI).
     */
    public function test_caso4_setvalue_persiste_y_recupera()
    {
        $key = 'defop_tiempo_textil_dias';
        $original = QuotationConfig::getValue($key);

        QuotationConfig::setValue($key, '20');
        $newVal = QuotationConfig::getValue($key);
        $this->assertEquals('20', (string) $newVal);

        // Restaurar
        QuotationConfig::setValue($key, (string) $original);
    }

    /**
     * Caso 5 — Cada categoría tiene sus 3 defaults operativos completos.
     */
    public function test_caso5_cada_categoria_tiene_defaults_completos()
    {
        $categorias = ['textil', 'impresos', 'eco', 'packs'];

        foreach ($categorias as $cat) {
            // Todas excepto packs tienen tecnica
            $tecnica = QuotationConfig::getValue('defop_tecnica_' . $cat);
            $this->assertNotNull($tecnica, "Categoría {$cat} debe tener defop_tecnica_*");

            $empaque = QuotationConfig::getValue('defop_empaque_' . $cat);
            $this->assertNotNull($empaque, "Categoría {$cat} debe tener defop_empaque_*");

            $tiempo = QuotationConfig::getValue('defop_tiempo_' . $cat . '_dias');
            $this->assertGreaterThan(0, (int) $tiempo, "Categoría {$cat} debe tener defop_tiempo_*_dias > 0");
        }

        // 'otros' solo debe tener tiempo
        $tiempoOtros = QuotationConfig::getValue('defop_tiempo_otros_dias');
        $this->assertGreaterThan(0, (int) $tiempoOtros);
    }
}

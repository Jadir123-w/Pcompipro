<?php

namespace Tests\Feature\Marketing;

use Modules\Marketing\Support\QuotationCondiciones;
use Tests\TestCase;

/**
 * Com02Condiciones11Test — "Términos y Condiciones Generales" de 11 secciones.
 *
 * Cubre el helper markdown-lite (viñetas, párrafos "> ", negrita **, escape de
 * HTML), el catálogo de las 11 secciones y el ensamblado por "condiciones
 * incluidas" (contrato: la IA elige qué secciones aparecen; los textos salen de
 * los defaults). El render y el catálogo son puros; assemble cae al texto de
 * fábrica cuando no hay config en BD, así que el test es determinista.
 */
class Com02Condiciones11Test extends TestCase
{
    /** Las 11 secciones, en orden, con las claves esperadas. */
    public function test_hay_11_secciones_en_orden()
    {
        $keys = QuotationCondiciones::keys();
        $this->assertCount(11, $keys);
        $this->assertSame('costo_servicio', $keys[0]);
        $this->assertSame('validez_oferta', $keys[5]);
        $this->assertSame('especificacion_upcycling', $keys[10]);
        // La validez reusa cotizacion_validez_default (no una clave propia).
        $cfg = QuotationCondiciones::configKeys();
        $this->assertSame('cotizacion_validez_default', $cfg['validez_oferta']);
    }

    /** Las condicionales (instalación/upcycling) NO van en el set por defecto. */
    public function test_default_incluidas_excluye_condicionales()
    {
        $def = QuotationCondiciones::defaultIncluidas();
        $this->assertContains('costo_servicio', $def);
        $this->assertContains('garantia_servicio', $def);
        $this->assertNotContains('terminos_instalacion', $def);
        $this->assertNotContains('especificacion_upcycling', $def);
    }

    /** Vacío → '' (la fila no se renderiza). */
    public function test_render_vacio_devuelve_string_vacio()
    {
        $this->assertSame('', QuotationCondiciones::render(''));
        $this->assertSame('', QuotationCondiciones::render('   '));
        $this->assertSame('', QuotationCondiciones::render([]));
        $this->assertSame('', QuotationCondiciones::render(['   ', '']));
    }

    /** Una línea → una viñeta; varias líneas → un solo <ul> con varios <li>. */
    public function test_render_lineas_a_vinetas()
    {
        $this->assertSame('<ul><li>Hola</li></ul>', QuotationCondiciones::render('Hola'));

        $out = QuotationCondiciones::render("Uno\nDos\nTres");
        $this->assertSame('<ul><li>Uno</li><li>Dos</li><li>Tres</li></ul>', $out);
    }

    /** "> " al inicio → párrafo sin viñeta; corta la lista previa. */
    public function test_render_parrafo_con_gt()
    {
        $out = QuotationCondiciones::render("> Intro sin viñeta");
        $this->assertSame('<p>Intro sin viñeta</p>', $out);

        // Mezcla: párrafo + 2 viñetas.
        $out = QuotationCondiciones::render("> Intro\nUno\nDos");
        $this->assertSame('<p>Intro</p><ul><li>Uno</li><li>Dos</li></ul>', $out);

        // Viñetas y LUEGO un párrafo: la lista se cierra antes del <p>.
        $out = QuotationCondiciones::render("Uno\n> Nota final");
        $this->assertSame('<ul><li>Uno</li></ul><p>Nota final</p>', $out);
    }

    /** **texto** → <strong>texto</strong> (varias negritas por línea). */
    public function test_render_negrita()
    {
        $out = QuotationCondiciones::render('Precio **importante** y **otro** dato');

        // Dos negritas aplicadas, sin marcadores markdown crudos.
        $this->assertSame(2, substr_count($out, '<strong>'));
        $this->assertSame(2, substr_count($out, '</strong>'));
        $this->assertNotContains('**', $out);

        // "importante" y "otro" quedan envueltos en <strong>.
        $this->assertRegExp('/<strong>\s*importante\s*<\/strong>/', $out);
        $this->assertRegExp('/<strong>\s*otro\s*<\/strong>/', $out);

        // El texto visible (sin etiquetas ni espacios de más) se conserva: las
        // palabras NO se pegan en los bordes de la negrita.
        $plain = preg_replace('/\s+/', ' ', trim(strip_tags($out)));
        $this->assertSame('Precio importante y otro dato', $plain);
    }

    /** El contenido se escapa ANTES del markdown-lite: no hay HTML inyectado. */
    public function test_render_escapa_html()
    {
        $out = QuotationCondiciones::render('<script>alert(1)</script>');
        $this->assertNotContains('<script>', $out);
        $this->assertContains('&lt;script&gt;', $out);

        // La negrita envuelve contenido ya escapado (seguro).
        $out = QuotationCondiciones::render('**a<b & c**');
        $this->assertContains('<strong>a&lt;b &amp; c</strong>', $out);
        $this->assertNotContains('a<b', $out);
    }

    /** Retro-compat: un array (JSON viejo de entrega) se pinta como viñetas. */
    public function test_render_array_retrocompat()
    {
        $out = QuotationCondiciones::render(['Primero', 'Segundo']);
        $this->assertSame('<ul><li>Primero</li><li>Segundo</li></ul>', $out);
    }

    /** assemble: incluidas → texto (no vacío); no incluidas → ''; siempre 11. */
    public function test_assemble_incluye_solo_las_elegidas()
    {
        $out = QuotationCondiciones::assemble(['costo_servicio', 'forma_pago']);

        // Siempre las 11 claves presentes.
        $this->assertCount(11, $out);
        foreach (QuotationCondiciones::keys() as $k) {
            $this->assertArrayHasKey($k, $out);
        }

        // Las elegidas traen texto; las demás quedan vacías (ocultas).
        $this->assertNotSame('', trim($out['costo_servicio']));
        $this->assertNotSame('', trim($out['forma_pago']));
        $this->assertSame('', $out['tiempo_ejecucion']);
        $this->assertSame('', $out['terminos_instalacion']);
    }

    /** El texto de fábrica de costo_servicio es markdown-lite renderizable. */
    public function test_factory_default_renderiza()
    {
        $factory = QuotationCondiciones::factoryDefaults();
        $this->assertArrayHasKey('costo_servicio', $factory);

        $html = QuotationCondiciones::render($factory['costo_servicio']);
        $this->assertContains('<p>Expuesto en cuadro superior.</p>', $html);
        $this->assertContains('<strong>No incluye servicio de diseño. (Costo adicional)</strong>', $html);
    }
}

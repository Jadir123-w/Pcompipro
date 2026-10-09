<?php

namespace Tests\Feature\Marketing;

use Modules\Marketing\Services\ProposalPdfGenerator;
use Tests\TestCase;

/**
 * Com02FotoPdfTest — Foto del producto más grande en el PDF de la cotización.
 *
 * La foto estaba limitada por TRES topes y el que mandaba era el primero:
 *   1. el raster del backend (se embebía a 80px de ancho),
 *   2. el max-width/max-height del <img> en el blade,
 *   3. el ancho de la columna "Foto".
 * Subir solo el CSS estiraba un raster de 80px (se veía borroso), así que se
 * suben los tres. El ancho extra sale de "Cód. Artíc." y "Artículo", NO de
 * "Detalles".
 *
 * Estos tests son deterministas: no necesitan tenant ni datos (el raster se
 * prueba con imágenes generadas con GD, y los anchos se leen del blade).
 */
class Com02FotoPdfTest extends TestCase
{
    /** @var string[] Archivos temporales a limpiar */
    private $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }

    /** El raster ahora reduce a 150px de ancho (antes 80), manteniendo proporción. */
    public function test_el_raster_reduce_a_150_px_de_ancho()
    {
        $src = $this->makePng(300, 200);

        list($w, $h) = $this->rasterize($src, 150);

        $this->assertSame(150, $w, 'El ancho del raster debe ser 150px');
        $this->assertSame(100, $h, 'El alto debe mantener la proporción 3:2');
    }

    /**
     * El raster NUNCA amplía: una foto de origen más chica que el tope se embebe
     * en su tamaño real. Es la limitación conocida y aceptada: preferimos una
     * foto chica antes que una pixelada.
     */
    public function test_el_raster_nunca_amplia()
    {
        $src = $this->makePng(100, 80);

        list($w, $h) = $this->rasterize($src, 150);

        $this->assertSame(100, $w, 'Una foto más chica que el tope no se amplía');
        $this->assertSame(80, $h);
    }

    /**
     * Tope de ALTO: una foto vertical no debe generar un JPEG altísimo que el
     * PDF luego muestra, como mucho, a 115px de alto.
     */
    public function test_el_raster_respeta_el_tope_de_alto()
    {
        $src = $this->makePng(150, 600);

        list($w, $h) = $this->rasterize($src, 150, 150);

        $this->assertSame(150, $h, 'El alto se topea en 150px');
        $this->assertSame(38, $w, 'El ancho se reduce proporcionalmente (150/600)');
    }

    /** El blade pinta la foto a 120x115 (antes 85x85). */
    public function test_el_blade_pinta_la_foto_mas_grande()
    {
        $blade = $this->bladeSource();

        $this->assertContains('max-width: 120px; max-height: 115px;', $blade);
        $this->assertNotContains('max-width: 85px; max-height: 85px;', $blade);
    }

    /**
     * Regresión barata contra la trampa principal de este cambio: los anchos
     * están DUPLICADOS (width inline del <th> y width del td en el CSS). Si se
     * cambia un solo lado, dompdf toma el mayor y el cambio no surte efecto.
     */
    public function test_los_anchos_de_columna_estan_sincronizados_en_th_y_td()
    {
        $blade = $this->bladeSource();

        // Lado <th> (incluye el <br> que permite bajar la columna a 40px).
        $this->assertContains('style="width: 40px;">Cód.<br>Artíc.', $blade);
        $this->assertContains('style="width: 58px;">Artículo', $blade);
        $this->assertContains('style="width: 124px;">Foto', $blade);

        // Lado CSS de los <td> — deben coincidir con los de arriba.
        $this->assertContains('td.codigo { width: 40px;', $blade);
        $this->assertContains('td.articulo { font-weight: bold; width: 58px; }', $blade);
        $this->assertContains('td.foto { font-style: italic; color: #888; width: 124px;', $blade);
    }

    /**
     * "Detalles" no debe perder ancho: es la columna sin width que absorbe el
     * resto. La suma de las columnas fijas no puede crecer respecto de antes
     * (458px), si no Detalles se estrangula.
     */
    public function test_las_columnas_fijas_no_crecen_y_detalles_no_pierde()
    {
        $blade = $this->bladeSource();

        // Todos los width declarados en los <th> de la tabla de productos.
        preg_match_all('/<th[^>]*style="width:\s*(\d+)px;"/', $blade, $m);
        $anchos = array_map('intval', $m[1]);

        $this->assertCount(8, $anchos, 'Deben ser 8 columnas con ancho fijo (Detalles no tiene)');

        $suma = array_sum($anchos);
        $this->assertSame(450, $suma, 'Nº22 + Cód40 + Art58 + Foto124 + Cant34 + Tiempo60 + Unit52 + Total60');
        $this->assertLessThan(458, $suma, 'Las fijas no pueden sumar más que antes: Detalles no debe achicarse');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Código fuente del blade del PDF. */
    private function bladeSource()
    {
        $path = base_path('modules/Marketing/Resources/views/quotation/proposal-pdf.blade.php');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /**
     * Invocar el rasterizador privado y devolver [ancho, alto] de la imagen
     * resultante, decodificando el data URI.
     *
     * @return array [int $w, int $h]
     */
    private function rasterize($absPath, $maxWidth, $maxHeight = null)
    {
        $gen = new ProposalPdfGenerator();
        $ref = new \ReflectionMethod($gen, 'imageToResizedDataUri');
        $ref->setAccessible(true);

        $dataUri = $ref->invoke($gen, $absPath, $maxWidth, $maxHeight);
        $this->assertInternalType('string', $dataUri);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $dataUri);

        $bin = base64_decode(substr($dataUri, strlen('data:image/jpeg;base64,')));
        $info = getimagesizefromstring($bin);
        $this->assertNotFalse($info, 'El data URI debe contener una imagen válida');

        return [(int) $info[0], (int) $info[1]];
    }

    /** Crear un PNG temporal de las dimensiones dadas. */
    private function makePng($w, $h)
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD no disponible');
        }

        $img = imagecreatetruecolor($w, $h);
        // Un poco de color para que el JPEG no sea degenerado.
        $c = imagecolorallocate($img, 200, 120, 60);
        imagefilledrectangle($img, 0, 0, $w, $h, $c);

        $path = sys_get_temp_dir() . '/com02_foto_' . $w . 'x' . $h . '_' . getmypid() . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        $this->tmpFiles[] = $path;

        return $path;
    }
}

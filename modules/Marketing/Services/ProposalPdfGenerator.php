<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Support\PublicUrl;
use Modules\Marketing\Support\QuotationCondiciones;

/**
 * ProposalPdfGenerator — Generador del PDF de la Cotización Final (COM-02 §26).
 *
 * Toma una `QuotationProposal` con su `PreQuote` asociada y produce un PDF
 * con el mismo formato que hoy COMPIPRO arma manualmente desde Excel.
 *
 * Usa la librería barryvdh/laravel-dompdf (ya instalada en el ERP) para
 * consistencia visual con las demás cotizaciones del sistema.
 *
 * El PDF se guarda en `storage/app/public/marketing/proposals/{numero}.pdf`
 * y se actualiza el campo `pdf_path` de la proposal.
 */
class ProposalPdfGenerator
{
    /**
     * Generar el PDF de una proposal y devolver la URL pública.
     *
     * @param  \Modules\Marketing\Models\QuotationProposal  $proposal
     * @return array  ['success' => bool, 'pdf_path' => string, 'pdf_url' => string, 'message' => string]
     */
    public function generate(QuotationProposal $proposal)
    {
        try {
            // Cargar datos necesarios
            $proposal->load(['prequote.items', 'prequote.empresa', 'prequote.contacto', 'bankAccount.bank', 'ejecutiva']);

            if (!$proposal->prequote) {
                return [
                    'success' => false,
                    'message' => 'La proposal no tiene una precotización asociada.',
                ];
            }

            // Construir data para la vista
            $data = $this->buildViewData($proposal);

            // Generar HTML con la vista Blade
            $pdf = \PDF::loadView('marketing::quotation.proposal-pdf', $data);
            $pdf->setPaper('a4', 'portrait');

            // Nombre del archivo
            $safeNumero = preg_replace('/[^A-Za-z0-9_\-]/', '_', $proposal->numero_cotizacion);
            $filename = "marketing/proposals/{$safeNumero}.pdf";

            // Guardar en storage/app/public
            $disk = 'public';
            Storage::disk($disk)->put($filename, $pdf->output());

            // Actualizar la proposal
            $proposal->update([
                'pdf_path' => $filename,
            ]);

            // La URL NO se pide a Storage::disk()->url(): ese helper la deriva de
            // APP_URL y, si el .env del servidor lo trae mal armado, el navegador
            // no puede abrir el PDF. Ver Support\PublicUrl para el detalle.
            $url = PublicUrl::forDisk($disk, $filename);

            Log::info('ProposalPdfGenerator: PDF generado', [
                'proposal_id'        => $proposal->id,
                'numero_cotizacion'  => $proposal->numero_cotizacion,
                'pdf_path'           => $filename,
            ]);

            return [
                'success'  => true,
                'pdf_path' => $filename,
                'pdf_url'  => $url,
                'message'  => 'PDF generado exitosamente.',
            ];

        } catch (\Exception $e) {
            Log::error('ProposalPdfGenerator: error generando PDF', [
                'proposal_id' => $proposal->id,
                'error'       => $e->getMessage(),
                'trace'       => substr($e->getTraceAsString(), 0, 1000),
            ]);

            return [
                'success' => false,
                'message' => 'Error generando PDF: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Construir el array de data que recibe la vista Blade del PDF.
     *
     * @param  \Modules\Marketing\Models\QuotationProposal  $proposal
     * @return array
     */
    private function buildViewData(QuotationProposal $proposal)
    {
        $prequote = $proposal->prequote;

        // ── Datos de la empresa emisora (GRUPO COMPINA SAC) desde quotation_config ──
        $empresa = $this->loadEmpresaConfig();

        // ── Datos bancarios opcionales ──
        $banco = null;
        if ($proposal->incluye_datos_bancarios && $proposal->bank_account_id) {
            $bankAccount = $proposal->bankAccount;
            if ($bankAccount) {
                $banco = [
                    'entidad'  => $bankAccount->bank ? $bankAccount->bank->description : 'Banco',
                    'tipo'     => 'Cta. Cte. Nuevos Soles (S/.)',
                    'numero'   => $bankAccount->number,
                    'cci'      => $bankAccount->cci,
                    'titular'  => $empresa['razon_social'],
                ];
            }
        }

        // ── Condiciones (11 secciones markdown-lite → HTML) ──
        // Se lee el snapshot guardado en la proposal y NO se rellena con defaults:
        // así una cotización EMITIDA se renderiza fiel a lo que se envió al cliente
        // (las secciones nuevas que no existían quedan '' = fila oculta), y las
        // secciones que la ejecutiva borró por cotización siguen ocultas.
        $condicionesRaw = [];
        if ($proposal->condiciones) {
            $decoded = json_decode($proposal->condiciones, true);
            if (is_array($decoded)) {
                $condicionesRaw = $decoded;
            }
        }

        // Pre-renderizar cada sección en su orden del PDF; las vacías se omiten.
        $condicionesRows = [];
        foreach (QuotationCondiciones::SECTIONS as $sec) {
            $key  = $sec['key'];
            $val  = isset($condicionesRaw[$key]) ? $condicionesRaw[$key] : '';
            $html = QuotationCondiciones::render($val);
            if ($html !== '') {
                $condicionesRows[] = ['label' => $sec['label'], 'html' => $html];
            }
        }

        // ── Notas de encabezado (array) ──
        $notas = [];
        if ($proposal->notas_encabezado) {
            $decoded = json_decode($proposal->notas_encabezado, true);
            if (is_array($decoded)) $notas = $decoded;
        }

        // ── Agosto 2026 (punto 14) · Costos SIN IGV o CON IGV ──
        //
        // "permitir elegir si los costos se muestran SIN IGV o CON IGV, tanto
        //  unitario como total, y que el rótulo de la columna cambie de forma
        //  dinámica."
        //
        // El campo `precios_incluyen_igv` ya existía en el modelo y hasta lo
        // completaba la IA, pero nunca se usaba: el PDF mostraba siempre los
        // valores sin IGV. Acá se cablea.
        //
        // El total con IGV sale de `total_item`, que el motor ya calcula y
        // persiste; el unitario se deriva de él dividiendo por la cantidad, y NO
        // recargando el unitario sin IGV por separado. Es la única forma de que
        // "unitario × cantidad" siga cuadrando con el total en el PDF: si se
        // redondearan los dos por su cuenta, podrían diferir en céntimos y el
        // cliente vería una multiplicación que no da.
        $conIgv = (bool) $proposal->precios_incluyen_igv;

        // ── Ítems con datos listos para mostrar (limpios, sin costos internos) ──
        $items = [];
        if ($prequote->items) {
            foreach ($prequote->items as $i => $item) {
                $cantidad    = max(1, (int) $item->quantity);
                $totalItem   = $conIgv ? (float) $item->total_item : (float) $item->valor_total;
                $unitarioItem = $conIgv ? ($totalItem / $cantidad) : (float) $item->valor_unitario;

                $items[] = [
                    'orden'            => $item->item_order > 0 ? $item->item_order : ($i + 1),
                    'codigo'           => $this->inferCodigoArticulo($item),
                    // Agosto 2026 (punto 10): "el campo Artículo debe salir siempre
                    // en MAYÚSCULAS, sin importar cómo lo haya escrito el usuario".
                    // Se hace acá y no en el blade para que el PDF y cualquier otra
                    // salida que consuma este arreglo muestren lo mismo.
                    // mb_strtoupper y no strtoupper: con acentos y Ñ, la versión sin
                    // multibyte deja "TARJETA DE PRESENTACIóN".
                    'nombre'           => self::partirTramosLargos(mb_strtoupper((string) $item->product_name, 'UTF-8'), self::ARTICULO_TRAMO_MAX),
                    // Imagen embebida como data URI base64 para que dompdf la renderice
                    // sin problemas de rutas/chroot. Resuelve la imagen propia del ítem
                    // y, si no tiene, hereda la del catálogo (items.image). null = "imagen referencial".
                    'imagen_url'       => $this->resolveItemImageDataUri($item),
                    'cantidad'         => (int) $item->quantity,
                    'costo_unitario'   => $unitarioItem,
                    // Agosto 2026 (punto 9): el PDF lo lee el CLIENTE, así que va el
                    // plazo que se le ofrece, no el que necesita el proveedor. Si el
                    // ítem no tiene uno propio, tiempoEntregaEfectivo() cae al de
                    // producción, que es lo que se mostraba antes de separarlos.
                    'tiempo_entrega'   => $this->formatTiempoEntrega($item->tiempoEntregaEfectivo()),
                    // Agosto 2026 (punto 11): los marcadores de formato que la
                    // ejecutiva puso en el Preview se convierten acá a HTML seguro.
                    // Escapa primero y aplica los marcadores después, así nada de
                    // lo que se escribió puede convertirse en una etiqueta.
                    'detalles'         => $this->detallesConSaltos(
                        \Modules\Marketing\Support\TextoEnriquecido::aHtml(
                            self::partirTramosLargos($this->buildDetallesItem($item), self::DETALLE_TRAMO_MAX)
                        )
                    ),
                    'costo_total'      => $totalItem,
                ];
            }
        }

        // ── Ejecutiva que firma (R6) ──
        // La firma sale de los "Datos personales" del usuario: Cargo (position),
        // Correo CORPORATIVO (corporate_email) y Celular CORPORATIVO
        // (corporate_cell_phone). El Tel es el del config del cotizador.
        // Si la cotización NO tiene ejecutiva → $firma = null y NO se imprime
        // ninguna firma (el blade la omite). Los apellidos se separan para
        // pintarlos en naranja.
        $firma = null;
        if ($proposal->ejecutiva) {
            $u = $proposal->ejecutiva;
            $firma = [
                // mb_strtoupper y no strtoupper: sin multibyte, una ejecutiva
                // llamada "María Rodríguez Peña" firma como "MARíA RODRíGUEZ PEñA"
                // en el documento que recibe el cliente.
                'nombres'   => mb_strtoupper(trim($u->name ?: ''), 'UTF-8'),
                'apellidos' => mb_strtoupper(trim(($u->paternal_lastname ?: '') . ' ' . ($u->maternal_lastname ?: '')), 'UTF-8'),
                'cargo'     => $u->position ?: '',
                'email'     => $u->corporate_email ?: '',
                'celular'   => $u->corporate_cell_phone ?: '',
                'tel'       => $empresa['telefono'],
            ];
        }

        // ── Subtotales (copiar de prequote) ──
        $subtotal = (float) $prequote->subtotal;
        $igv      = (float) $prequote->total_igv;
        $total    = (float) $prequote->total;

        return [
            'proposal'     => $proposal,
            'prequote'     => $prequote,
            'empresa'      => $empresa,
            'banco'        => $banco,
            'condiciones_rows' => $condicionesRows,
            'notas'        => $notas,
            'items'        => $items,
            'firma'        => $firma,
            'subtotal'     => $subtotal,
            'igv'          => $igv,
            'total'        => $total,
            // Agosto 2026 (punto 14): rótulo dinámico de la columna de costos. Se
            // resuelve acá y no en el blade para que la vista no tenga que decidir
            // nada: recibe el texto ya listo.
            'costos_con_igv'  => $conIgv,
            // "Costos" en plural, como lo escribe el requerimiento: "el texto
            // Costos SIN IGV (S/.) cambiará de forma dinámica según corresponda".
            // La cabecera anterior decía "Costo" en singular; se alinea al documento.
            'costos_label'    => $conIgv ? 'Costos CON IGV' : 'Costos SIN IGV',
            'moneda_signo' => $proposal->moneda === 'USD' ? '$' : 'S/',
            'fecha_larga'  => $this->formatFechaLarga($proposal->fecha_emision),
            // Logos de marca embebidos como data URI base64 (lo más estable para
            // dompdf — evita problemas de ruta/chroot que ya vimos con la imagen
            // de producto). Null = el blade cae al texto de respaldo.
            'logo_compina'   => $this->logoDataUri('Logo_Compina.png'),
            'logo_compipro'  => $this->logoDataUri('Logo_Compipro.png'),
            'logo_compihost' => $this->logoDataUri('Logo_Compihost.jpg'),
            // Sello "ECOLÓGICO SOSTENIBLE" (badge verde) para la cabecera maestra.
            // Es un PNG grande (~1200px) con transparencia: se redimensiona ANTES
            // de componerlo sobre blanco (helper propio), para no disparar el bucle
            // de aplanado ni ensuciar los bordes con JPEG. Se embebe una sola vez
            // (dompdf deduplica) aunque la cabecera se repita en cada página.
            'sello_eco'      => $this->selloDataUri('Logo_Sello_Ecologico.png'),
            // Pie repetido (plantilla maestra): línea de contacto COMPIPRO.
            'pie_email'      => $empresa['pie_email'],
            'pie_telefono'   => $empresa['pie_telefono'],
            'pie_web'        => $empresa['pie_web'],
            // Bloque de sostenibilidad (plantilla maestra).
            'sost_p1'        => $empresa['sost_p1'],
            'sost_p2'        => $empresa['sost_p2'],
        ];
    }

    /**
     * Resolver un logo de marca (public/logo/{archivo}) como data URI PNG.
     *
     * Los logos "con slogan" traen texto fino anti-aliaseado; se componen con el
     * MISMO resampleo + alpha-blending sobre blanco que el sello (`selloDataUri`),
     * que da bordes suaves. El viejo aplanado por umbral duro de alfa dentaba el
     * texto del slogan, por eso ya no se usa para los logos.
     *
     * @param  string  $filename
     * @return string|null  data URI o null si el archivo no existe/no se lee.
     */
    private function logoDataUri($filename)
    {
        return $this->selloDataUri($filename, 160);
    }

    /**
     * Resolver una imagen de marca (logo o sello) de public/logo/{archivo} como
     * data URI PNG opaco, listo para la cabecera del PDF.
     *
     *   1. Se REDIMENSIONA primero por altura (retina), evitando cargar/embeber
     *      PNGs grandes a resolución completa.
     *   2. Se COMPONE sobre blanco con imagecopyresampled + alpha blending real,
     *      lo que da bordes suaves anti-aliaseados. Clave para los logos "con
     *      slogan" (texto fino) y para el sello: un umbral duro por-píxel dentaría
     *      esos bordes.
     *   3. Se emite PNG (no JPEG), para no ensuciar los bordes nítidos.
     *
     * @param  string  $filename
     * @param  int     $targetHeight  altura destino en px (retina respecto al display).
     * @return string|null  data URI PNG o null si no existe/GD no disponible.
     */
    private function selloDataUri($filename, $targetHeight = 160)
    {
        try {
            $path = public_path('logo/' . $filename);
            if (!is_file($path)) {
                Log::warning('ProposalPdfGenerator: sello no encontrado', ['path' => $path]);
                return null;
            }
            if (!function_exists('imagecreatetruecolor')) {
                // Sin GD: embeber el PNG tal cual (pesado, pero funciona).
                $raw = @file_get_contents($path);
                return ($raw !== false && $raw !== '')
                    ? 'data:image/png;base64,' . base64_encode($raw)
                    : null;
            }

            $info = @getimagesize($path);
            if ($info === false) {
                return null;
            }
            list($srcW, $srcH) = $info;
            if ($srcW <= 0 || $srcH <= 0) {
                return null;
            }

            $src = $this->createGdFromFile($path, $info[2]);
            if ($src === null) {
                return null;
            }

            // Escalar por altura (solo reducir, nunca ampliar).
            if ($srcH > $targetHeight) {
                $dstH = $targetHeight;
                $dstW = (int) round($srcW * ($targetHeight / $srcH));
            } else {
                $dstH = $srcH;
                $dstW = $srcW;
            }
            $dstW = max(1, $dstW);
            $dstH = max(1, $dstH);

            $dst = imagecreatetruecolor($dstW, $dstH);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
            // Alpha blending ON: compone el alfa del sello sobre el blanco de fondo.
            imagealphablending($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            ob_start();
            imagepng($dst);
            $png = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return ($png !== false && $png !== '')
                ? 'data:image/png;base64,' . base64_encode($png)
                : null;
        } catch (\Exception $e) {
            Log::warning('ProposalPdfGenerator: error procesando imagen de marca', [
                'filename' => $filename,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cargar los datos de empresa (GRUPO COMPINA SAC) desde quotation_config.
     *
     * @return array
     */
    private function loadEmpresaConfig()
    {
        try {
            $rows = DB::connection('tenant')
                ->table('quotation_config')
                ->whereIn('config_key', [
                    'empresa_razon_social', 'empresa_ruc', 'empresa_direccion',
                    'empresa_telefono', 'empresa_email_info', 'empresa_web_principal',
                    'empresa_catalogo_url', 'empresa_facebook',
                    // Pie repetido de la cotización (plantilla maestra). Son datos
                    // COMPIPRO (no Compina): se editan por config sin tocar código.
                    'empresa_pie_email', 'empresa_pie_telefono', 'empresa_pie_web',
                    // Bloque de sostenibilidad (plantilla maestra), editable por config.
                    'empresa_sost_parrafo1', 'empresa_sost_parrafo2',
                ])
                ->pluck('config_value', 'config_key');
        } catch (\Exception $e) {
            $rows = collect();
        }

        return [
            'razon_social' => $rows->get('empresa_razon_social', 'GRUPO COMPINA SAC'),
            'ruc'          => $rows->get('empresa_ruc', ''),
            'direccion'    => $rows->get('empresa_direccion', ''),
            'telefono'     => $rows->get('empresa_telefono', ''),
            'email'        => $rows->get('empresa_email_info', 'info@compina.net'),
            'web'          => $rows->get('empresa_web_principal', 'www.compina.net'),
            'catalogo_url' => $rows->get('empresa_catalogo_url', ''),
            'facebook'     => $rows->get('empresa_facebook', 'COMPIPRO'),
            // Pie repetido (plantilla maestra). Defaults = datos del PDF del cliente.
            'pie_email'    => $rows->get('empresa_pie_email', 'ventas@compipro.net'),
            'pie_telefono' => $rows->get('empresa_pie_telefono', '7804649 / 792613'),
            'pie_web'      => $rows->get('empresa_pie_web', 'www.compipro.net'),
            // Bloque de sostenibilidad (plantilla maestra). Texto fijo de marca;
            // default = el del PDF del cliente. Editable/ocultable por config.
            'sost_p1'      => $rows->get('empresa_sost_parrafo1', 'Con su compra, usted apoya el crecimiento económico de madres y familias emprendedoras, artesanos y la comunidad Shipibo-Conibo, brindándoles oportunidades de trabajo digno, capacitación técnica y empoderamiento. Esto les permite trabajar desde casa, equilibrando su vida laboral y familiar.'),
            'sost_p2'      => $rows->get('empresa_sost_parrafo2', 'Gracias a ustedes, venimos sembrando 10,000 árboles en la comunidad Shipibo-Conibo (Shawan Rama), que pertenece a la Reserva de Biosfera Bosques de Neblina – Selva Central, ubicada en San Ramón, Chanchamayo, región Junín, Perú. Es una de las áreas de conservación más significativas del país, reconocida por la UNESCO en 2020 como la sexta Reserva de Biosfera del Perú.'),
        ];
    }

    /**
     * Construir el bloque "Detalles" multilínea (como en el PDF real).
     * Ejemplo: "LIBRETA ECOLOGICA...\nMedidas: 13 x 18 cm\nMaterial: ...".
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    /**
     * Saltos de línea de los detalles como <br>. dompdf (0.8) pierde `white-space: pre-line`
     * en la fila que pasa a la página siguiente y junta todo el texto; con <br> no.
     * Antes se unifican los saltos raros (CR solo, separadores Unicode) que llegan al
     * pegar texto de otros programas.
     *
     * @param  string  $html  HTML seguro de TextoEnriquecido::aHtml()
     * @return string
     */
    /**
     * Largo máximo de un tramo sin espacios. Más largo, dompdf no puede partirlo y ensancha
     * la tabla fuera del A4 (se cortaban las columnas de precio). Se calcula para el ancho
     * de cada columna con su tamaño de letra.
     */
    const DETALLE_TRAMO_MAX = 32;
    const ARTICULO_TRAMO_MAX = 14;

    /**
     * Parte con un espacio los tramos sin espacios más largos que $max (un enlace o una lista
     * pegada sin espacios). El texto normal no cambia: sus palabras son más cortas.
     *
     * @param  string  $texto  Texto plano (antes de convertirlo a HTML)
     * @param  int     $max
     * @return string
     */
    public static function partirTramosLargos($texto, $max)
    {
        // Corchetes y asteriscos cortan el tramo: así nunca se parte un marcador de formato.
        return preg_replace_callback('/[^\s\[\]*]{' . ($max + 1) . ',}/u', function ($m) use ($max) {
            return trim(implode(' ', preg_split('/(.{' . $max . '})/u', $m[0], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)));
        }, (string) $texto);
    }

    private function detallesConSaltos($html)
    {
        $html = preg_replace('/\r\n|\r|\x{2028}|\x{2029}|\x{0085}|\x{000B}/u', "\n", (string) $html);
        return nl2br($html, false);
    }

    private function buildDetallesItem($item)
    {
        $parts = [];
        // mb_strtoupper y no strtoupper: sin multibyte quedaba "TAZA DE CERáMICA"
        // y "TOMATODO METáLICO", con la vocal acentuada en minúscula en medio de
        // las mayúsculas. Mismo criterio que la columna Artículo.
        $parts[] = mb_strtoupper((string) $item->product_name, 'UTF-8');

        if (!empty($item->material)) {
            $parts[] = 'Material: ' . $item->material;
        }
        if (!empty($item->medida)) {
            $parts[] = 'Medidas: ' . $item->medida;
        }
        if (!empty($item->tecnica_impresion)) {
            $parts[] = 'Técnica: ' . $item->tecnica_impresion;
        }
        if (!empty($item->cantidad_colores) && $item->cantidad_colores > 1) {
            $parts[] = 'Colores: ' . $item->cantidad_colores;
        }
        if (!empty($item->cantidad_lados) && $item->cantidad_lados > 1) {
            $parts[] = 'Lados: ' . $item->cantidad_lados;
        }
        if (!empty($item->empaque)) {
            $parts[] = 'Presentación: ' . $item->empaque;
        }
        if (!empty($item->personalizacion)) {
            $parts[] = 'Personalización: ' . $item->personalizacion;
        }
        if (!empty($item->observaciones)) {
            $parts[] = $item->observaciones;
        }
        if (!empty($item->product_description)) {
            $parts[] = $item->product_description;
        }

        return implode("\n", $parts);
    }

    /**
     * Código de artículo a mostrar en la columna "Cód. Artíc." del PDF.
     *
     * La cascada la resuelve el accessor del modelo (única fuente de verdad,
     * para que el preview editable y el PDF muestren SIEMPRE lo mismo):
     *   1. Override manual de la ejecutiva (`codigo_articulo_manual`).
     *   2. Código del catálogo (`items.internal_id`), salvo los 'COM02-ADHOC-…'
     *      que genera la conversión, que son internos y no se muestran.
     *   3. Cadena vacía.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    private function inferCodigoArticulo($item)
    {
        return (string) $item->codigo_articulo;
    }

    /**
     * Resolver la imagen de un ítem como data URI base64 lista para dompdf.
     *
     * dompdf NO resuelve bien rutas relativas ni URLs http (de ahí el bug de la
     * "X / image not found"). La forma robusta es embeber la imagen como
     * `data:image/jpeg;base64,...`. Además redimensionamos a un ancho máximo
     * (~150px) para que el PDF no se infle con fotos grandes del cliente. El
     * blade la muestra a 120px de ancho, así que 150 deja margen de nitidez
     * (~120 dpi efectivos) sin inflar el archivo.
     *
     * Orden de resolución:
     *   1. Imagen propia del ítem (product_reference_image, disco local).
     *   2. Fallback: imagen del catálogo (items.image) si hay item_id (punto C).
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string|null  data URI o null si no hay imagen utilizable.
     */
    private function resolveItemImageDataUri($item)
    {
        $absPath = $this->resolveItemImageAbsolutePath($item);
        if ($absPath === null) {
            return null;
        }

        try {
            // Cuadro fijo con la foto contenida (sin estirar ni recortar): la plantilla la
            // muestra a todo el ancho de la columna Foto y el alto sale de esta proporción.
            return $this->imageToResizedDataUri($absPath, 232, 200, true);
        } catch (\Exception $e) {
            Log::warning('ProposalPdfGenerator: no se pudo procesar imagen del ítem', [
                'item_id' => $item->id,
                'path'    => $absPath,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolver la RUTA ABSOLUTA en el filesystem de la imagen de un ítem.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string|null
     */
    private function resolveItemImageAbsolutePath($item)
    {
        // 1) Imagen propia subida por la ejecutiva (disco local).
        if (!empty($item->product_reference_image)) {
            try {
                if (Storage::disk('local')->exists($item->product_reference_image)) {
                    return Storage::disk('local')->path($item->product_reference_image);
                }
            } catch (\Exception $e) {
                // seguir al fallback
            }
        }

        // 2) Fallback: imagen del catálogo (items.image). Se guarda como nombre
        //    de archivo en storage/app/public/uploads/items/{image}. El centinela
        //    'imagen-no-disponible.jpg' significa "sin imagen".
        if ($item->item_id) {
            try {
                $catItem = \App\Models\Tenant\Item::find($item->item_id);
                if ($catItem
                    && !empty($catItem->image)
                    && $catItem->image !== 'imagen-no-disponible.jpg') {
                    $abs = storage_path('app/public/uploads/items/' . $catItem->image);
                    if (is_file($abs)) {
                        return $abs;
                    }
                }
            } catch (\Exception $e) {
                // sin imagen
            }
        }

        return null;
    }

    /**
     * Leer una imagen del filesystem, redimensionarla a un ancho máximo y
     * devolverla como data URI base64 (JPEG). Usa GD.
     *
     * Solo REDUCE, nunca amplía: una foto de origen más chica que el tope se
     * embebe en su tamaño real (preferimos "chica" antes que "pixelada").
     *
     * @param  string    $absPath
     * @param  int       $maxWidth
     * @param  int|null  $maxHeight  tope de alto opcional (para fotos verticales)
     * @return string|null
     */
    private function imageToResizedDataUri($absPath, $maxWidth = 80, $maxHeight = null, $encajar = false)
    {
        if (!is_file($absPath) || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        // Guard de tamaño: evitar cargar archivos enormes en memoria.
        if (filesize($absPath) > 12 * 1024 * 1024) { // 12 MB
            return null;
        }

        $info = @getimagesize($absPath);
        if ($info === false) {
            return null;
        }

        list($srcW, $srcH) = $info;
        if ($srcW <= 0 || $srcH <= 0) {
            return null;
        }

        // Cargar el source según el tipo.
        $src = $this->createGdFromFile($absPath, $info[2]);
        if ($src === null) {
            return null;
        }

        // Calcular destino manteniendo proporción (solo reducir, nunca ampliar).
        if ($srcW > $maxWidth) {
            $dstW = $maxWidth;
            $dstH = (int) round($srcH * ($maxWidth / $srcW));
        } else {
            $dstW = $srcW;
            $dstH = $srcH;
        }

        // Tope de ALTO opcional: evita generar un JPEG de 150x600 para una foto
        // vertical que el PDF va a mostrar, como mucho, a 115px de alto.
        if ($maxHeight !== null && $dstH > $maxHeight) {
            $dstW = (int) round($dstW * ($maxHeight / $dstH));
            $dstH = (int) $maxHeight;
        }

        $dstW = max(1, $dstW);
        $dstH = max(1, $dstH);

        // Encajar: lienzo fijo de $maxWidth x $maxHeight con la foto entera centrada, ampliada o
        // reducida sin deformarla. Así el PDF la muestra siempre con la misma proporción: una
        // foto vertical ya no se estira a lo ancho ni una pequeña queda diminuta.
        $lienzoW = $dstW;
        $lienzoH = $dstH;
        $x = 0;
        $y = 0;
        if ($encajar && $maxHeight !== null) {
            $escala = min($maxWidth / $srcW, $maxHeight / $srcH);
            $dstW = max(1, (int) round($srcW * $escala));
            $dstH = max(1, (int) round($srcH * $escala));
            $lienzoW = (int) $maxWidth;
            $lienzoH = (int) $maxHeight;
            $x = (int) floor(($lienzoW - $dstW) / 2);
            $y = (int) floor(($lienzoH - $dstH) / 2);
        }

        $dst = imagecreatetruecolor($lienzoW, $lienzoH);
        // Fondo blanco (aplana transparencias de PNG/GIF para JPEG de salida).
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $lienzoW, $lienzoH, $white);
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagejpeg($dst, null, 82);
        $jpegData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if ($jpegData === false || $jpegData === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($jpegData);
    }

    /**
     * Crear un recurso GD desde un archivo según su tipo IMAGETYPE_*.
     *
     * @param  string  $absPath
     * @param  int     $type     Constante IMAGETYPE_*
     * @return resource|\GdImage|null
     */
    private function createGdFromFile($absPath, $type)
    {
        try {
            switch ($type) {
                case IMAGETYPE_JPEG:
                    return imagecreatefromjpeg($absPath);
                case IMAGETYPE_PNG:
                    return imagecreatefrompng($absPath);
                case IMAGETYPE_GIF:
                    return imagecreatefromgif($absPath);
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        return imagecreatefromwebp($absPath);
                    }
                    return null;
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formatear el tiempo de entrega para el PDF.
     *
     * COM08 — tiempo_estimado_dias es TEXTO LIBRE (ej. "Mié 02 Abril *",
     * "1 día útil", "15 días útiles"). Se muestra tal cual lo escribió la
     * ejecutiva; el PDF no debe reinterpretarlo como número de días.
     *
     * @param  string|null  $tiempo
     * @return string
     */
    private function formatTiempoEntrega($tiempo)
    {
        $tiempo = trim((string) $tiempo);
        return $tiempo !== '' ? $tiempo : '-';
    }

    /**
     * Formatear la fecha en el estilo del PDF real: "Lima, 17 de Setiembre del 2024".
     *
     * @param  mixed  $fecha
     * @return string
     */
    private function formatFechaLarga($fecha)
    {
        if (!$fecha) $fecha = now();

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $ts = is_string($fecha) ? strtotime($fecha) : $fecha->getTimestamp();
        $dia = (int) date('j', $ts);
        $mes = $meses[(int) date('n', $ts)];
        $anio = date('Y', $ts);

        return "Lima, {$dia} de {$mes} del {$anio}";
    }
}

<?php

namespace Modules\Marketing\Support;

use Illuminate\Support\Facades\DB;

/**
 * QuotationCondiciones — Fuente única de las "Términos y Condiciones Generales"
 * del PDF de la cotización (COM-02).
 *
 * Centraliza (para que el generador de PDF, el motor de IA y las migraciones no
 * dupliquen ni se desincronicen):
 *
 *   1. La lista ORDENADA de las 11 secciones (clave, rótulo, clave de config de
 *      donde sale el texto por defecto, y si va incluida por defecto).
 *   2. Los textos por defecto de fábrica (transcritos de la plantilla del
 *      cliente `PLATILLA_MAESTRA`), en formato "markdown-lite".
 *   3. La carga de defaults desde `quotation_config` (con fallback de fábrica).
 *   4. El ensamblado de las condiciones de una proposal a partir de la lista de
 *      secciones que la IA decide incluir (decisión: la IA SOLO elige qué
 *      secciones aparecen; los textos salen siempre del default).
 *   5. El render "markdown-lite → HTML seguro" para dompdf.
 *
 * ── Formato markdown-lite (uniforme para las 11 secciones) ──
 *   - Cada línea (\n) = una viñeta <li>. Líneas consecutivas → un <ul>.
 *   - Línea que empieza con "> " = párrafo sin viñeta (<p>), para intros y notas.
 *   - `**texto**` → <strong>texto</strong> (única marca de formato; SIN color).
 *   - String vacío / solo espacios → la fila NO se renderiza (fila oculta).
 *   - El contenido se escapa con e() ANTES de aplicar el markdown-lite: no se
 *     permite HTML libre del usuario.
 */
class QuotationCondiciones
{
    /**
     * Las 11 secciones, en el ORDEN del PDF de la plantilla.
     *
     * - key        : clave lógica (la que se guarda en `proposal.condiciones`)
     * - label      : rótulo de la fila izquierda en el PDF
     * - config_key : clave en `quotation_config` de donde sale el texto default
     * - default    : true = se incluye por defecto cuando la IA no decide o falla
     *
     * La "Validez de la oferta" reusa `cotizacion_validez_default` (no tiene una
     * clave propia `condiciones_default_*`), por decisión del negocio.
     */
    const SECTIONS = [
        ['key' => 'costo_servicio',             'label' => 'Costo del Servicio',                       'config_key' => 'condiciones_default_costo_servicio',             'default' => true],
        ['key' => 'forma_pago',                 'label' => 'Forma de Pago',                            'config_key' => 'condiciones_default_forma_pago',                 'default' => true],
        ['key' => 'tiempo_ejecucion',           'label' => 'Tiempo de ejecución',                      'config_key' => 'condiciones_default_tiempo_ejecucion',           'default' => true],
        ['key' => 'consideraciones_produccion', 'label' => 'Consideraciones de producción',            'config_key' => 'condiciones_default_consideraciones_produccion', 'default' => true],
        ['key' => 'moneda_texto',               'label' => 'Moneda',                                   'config_key' => 'condiciones_default_moneda_texto',               'default' => true],
        ['key' => 'validez_oferta',             'label' => 'Validez de la oferta',                     'config_key' => 'cotizacion_validez_default',                     'default' => true],
        ['key' => 'consideraciones_entrega',    'label' => 'Consideraciones para Entrega de Producto', 'config_key' => 'condiciones_default_consideraciones_entrega',    'default' => true],
        ['key' => 'terminos_instalacion',       'label' => 'Términos para servicio instalación',       'config_key' => 'condiciones_default_terminos_instalacion',       'default' => false],
        ['key' => 'garantia_servicio',          'label' => 'Garantía del Servicio',                    'config_key' => 'condiciones_default_garantia_servicio',          'default' => true],
        ['key' => 'servicio_complementario',    'label' => 'Servicio complementario',                  'config_key' => 'condiciones_default_servicio_complementario',    'default' => true],
        ['key' => 'especificacion_upcycling',   'label' => 'Especificación del servicio UPCYCLING',    'config_key' => 'condiciones_default_especificacion_upcycling',   'default' => false],
    ];

    /**
     * Claves lógicas de las 11 secciones, en orden.
     *
     * @return string[]
     */
    public static function keys()
    {
        return array_column(self::SECTIONS, 'key');
    }

    /**
     * Mapa clave → rótulo.
     *
     * @return array
     */
    public static function labels()
    {
        return array_column(self::SECTIONS, 'label', 'key');
    }

    /**
     * Mapa clave → clave de config del default.
     *
     * @return array
     */
    public static function configKeys()
    {
        return array_column(self::SECTIONS, 'config_key', 'key');
    }

    /**
     * Claves incluidas por defecto (cuando la IA no decide o falla).
     *
     * @return string[]
     */
    public static function defaultIncluidas()
    {
        $out = [];
        foreach (self::SECTIONS as $sec) {
            if (!empty($sec['default'])) {
                $out[] = $sec['key'];
            }
        }
        return $out;
    }

    /**
     * Textos por defecto de fábrica (transcritos de `PLATILLA_MAESTRA`), en
     * formato markdown-lite. Es el fallback runtime y la fuente que la migración
     * siembra en `quotation_config`.
     *
     * Nota: se transcriben tal cual la plantilla del cliente (incluye sus propias
     * grafías y erratas — "envió", "courrier", "SCRT"); el resaltado de color de
     * la plantilla se descarta y solo se conserva la negrita (`**`).
     *
     * @return array  clave lógica → string markdown-lite
     */
    public static function factoryDefaults()
    {
        return [
            'costo_servicio' => <<<'TXT'
> Expuesto en cuadro superior.
Precios incluyen aplicación logo, armado de muestra virtual o montaje referencial para su evaluación, gastos de envío a UN SOLO PUNTO DE ENTREGA mas no incluye IGV.
**No incluye servicio de diseño. (Costo adicional)**
TXT
            ,
            'forma_pago' => <<<'TXT'
**50 % adelanto y 50% cancelación contra entrega.**
**Crédito a X días (*), previa Orden Compra o Servicio y Línea crédito aprobado.**
**Con tarjeta crédito o débito nacional** (Visa, MasterCard, otros). Tener en consideración que se tiene **un 3.99% +IGV de comisión del monto total (monto referencial).**
> **No incluye costos por cargo financiero** por Transferencias bancarias por Depósitos (0.05%), por cuentas de provincia y /o internacionales.
> **(*) De no cumplirse tiempo del crédito acordado, Cliente estará afecto a cargos** administrativos y/o financieros **como penalidades por NO PAGO** (montos y % a definirse acorde tiempo pasé).
TXT
            ,
            'tiempo_ejecucion' => <<<'TXT'
Expuesto en cuadro superior. Para iniciar producción, se debe contar:
Orden de Compra y/o Servicio debe ser facilitado por el Cliente.
**Se inicia proyecto desde el abono verificado del 50% de adelanto en nuestra cuenta.**
El Arte logo o diseño debe ser facilitado por el Cliente (en Corel, Ilustrador) y validado por COMPIPRO este en curvas o vectores.
Muestra virtual y/o física aprobado por Cliente.
Tener definido el lugar de entrega (en caso existan **cambio de dirección** se debe notificar con **1 día de anticipación** para su reprogramación, caso contrario **se evaluaría disponibilidad y costo adicional).**
TXT
            ,
            'consideraciones_produccion' => <<<'TXT'
COMPIPRO recomienda solicitar una muestra física o prueba de color del producto para evitar cualquier inconveniente en la producción y posterior entrega del producto, ya que el producto en físico se puede verificar tamaño, calidad material, etc.
**La realización de esta muestra tiene un tiempo adicional a la producción y posible costo adicional (en caso no se detalle incluya en cuadro cotización).** Si el **cliente decide omitir** esta recomendación, **deberá enviar un correo formal confirmando su decisión de proceder con la producción sin la elaboración de la muestra física (luego no se podrá exigir especificaciones en el producto, porque en desarrollo de la muestra física es la etapa para revisar y aprobar detalles requieran).**
Una vez que se haya proporcionado la **orden de compra y el 50% de adelanto, no se podrá cancelar el pedido.** Si el cliente solicita detener el proceso, COMPIPRO, según su política, **no realizará ningún reembolso económico.** De no haber iniciado producción, es posible canjear por otro proyecto de similar valor durante el presente mes de aprobado proyecto.
TXT
            ,
            'moneda_texto' => <<<'TXT'
Todos los precios son expresados en Soles.
Si se requieren precios en Dólares, deberá solicitarse con antelación, **considerando que el tipo de cambio es volátil y puede variar.**
TXT
            ,
            'validez_oferta' => <<<'TXT'
> **5 a 7 días útiles**, después de ese tiempo transcurrido estará sujeto a variación de precio de los productos, por ello se recomienda poder solicitar una cotización actualizada. Asimismo, tener en cuenta que algunos productos están sujetos a stock.
TXT
            ,
            'consideraciones_entrega' => <<<'TXT'
Gastos de envió incluido para Compras mayores a S/. 550.
**Montos menores a S/. 550** nuevos soles, hay un incremento **adicional** por costo de movilidad **desde S/.30 (sujeto a variación por volumen).**
El incremento del costo de envió fuera de Lima Metropolitana (*) y/o provincias será asumida por el Cliente y se trabajará mediante una courrier o empresa de Transporte de su elección.
COMPIPRO tiene la responsabilidad de **contabilizar los productos** en el momento de la **entrega junto con el cliente**, con ello el cliente pueda firmar la guía de remisión dando conformidad del servicio. Si el **cliente solicita omitir este proceso, deberá firmar y registrar dicha solicitud** en la guía. **Cualquier reclamo posterior por cantidades observadas, el cliente asume la responsabilidad.**
> **(*) Distritos no considerados** para envío dentro de Lima Metropolitana: Ancón, Callao, Chaclacayo, Cieneguilla, Lurigancho, Lurín, Pachacamac, Pta. Hermosa, Pta. Negra, Pte. Piedra, Pucusana, Sta. Rosa, San Bartolo, Sta. María, Ventanilla, S.J de Miraflores, V.M. de Triunfo y Villa el salvador.
TXT
            ,
            'terminos_instalacion' => <<<'TXT'
Cliente debe indicar con anticipación todos los requisitos necesarios para el ingreso del personal al lugar de trabajo (oficina propia / edificio corporativo /almacén) donde se realizará instalación (permisos ingreso, implementos de seguridad, si requiere SCRT, etc.). De no tenerse definido máx. 1 día antes, no se podrá ejecutar el día acordado. En caso haber cambios de lugar/ fecha y no se informe máx. 1 día antes a fecha programada, tendrá un costo adicional por la reprogramación.
**Respetar N° de visitas indicadas para instalación,** según son señaladas en cotización más **servicio estándar es de 1 día en 1 sólo lugar** (No se considera Sábados, Domingos ni feriados). De requerirse visitas adicionales no contemplados en propuesta, es posible previa programación, más tendrá un costo adicional.
**Para el caso de fabricación e instalación, se requerirá realizar una visita de reconocimiento** del espacio o superficie a trabajar (ideal antes de iniciar fabricación, más aún si se ha cotizado solo en base fotos). **En caso condiciones sean diferentes a lo cotizado** y se requiera alguna modificación posterior, **se cobrará un monto adicional** a confirmarse.
TXT
            ,
            'garantia_servicio' => <<<'TXT'
Se cuenta con tiempo de garantía de **3 a 5 días útiles** después de la recepción de los productos, para que se revise y reporte si hay observaciones.
TXT
            ,
            'servicio_complementario' => <<<'TXT'
> Se cuenta con **Seguro Complementario de Trabajo de Riesgo (SCTR).**
TXT
            ,
            'especificacion_upcycling' => <<<'TXT'
El Cliente deberá tener definido la fecha entrega para el recojo de los materiales textiles (uniformes, etc) y banners facilitará para este proyecto, para poder recoger y dar inicio del servicio.
**En caso requerir con urgencia o en menor tiempo** entrega es manejable (siempre que nuestra producción en el momento nos permita).
**Todos nuestros productos están elaborados con materiales reciclados, garantizando calidad y un impacto ambiental positivo.**
COMPIPRO se compromete a facilitar documentación importante le permita tangibilidad el impacto positivo que se realiza con la ejecución de este servicio:
**Reporte con medición de HUELLA DE CARBONO.**
Adicional es posible emitir una medición de residuos e indicadores de sostenibilidad (*).
> (*) Considerar que documento con indicadores de sostenibilidad cuenta con un tiempo y costo adicional.
TXT
            ,
        ];
    }

    /**
     * Cargar los textos por defecto de las 11 secciones desde `quotation_config`,
     * con fallback a los textos de fábrica si una clave falta o está vacía.
     *
     * @return array  clave lógica → string markdown-lite
     */
    public static function loadDefaults()
    {
        $factory = self::factoryDefaults();

        try {
            $rows = DB::connection('tenant')
                ->table('quotation_config')
                ->whereIn('config_key', array_values(self::configKeys()))
                ->pluck('config_value', 'config_key');
        } catch (\Exception $e) {
            $rows = collect();
        }

        $out = [];
        foreach (self::SECTIONS as $sec) {
            $key = $sec['key'];
            $val = $rows->has($sec['config_key']) ? $rows->get($sec['config_key']) : null;

            if ($val === null || trim((string) $val) === '') {
                // Config ausente/vacía → texto de fábrica.
                $out[$key] = isset($factory[$key]) ? $factory[$key] : '';
                continue;
            }

            // Retro-compat: `consideraciones_entrega` podía guardarse como JSON
            // array (config viejo, antes de markdown-lite). Convertir a líneas.
            if ($key === 'consideraciones_entrega') {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    $val = implode("\n", $decoded);
                }
            }

            $out[$key] = (string) $val;
        }

        return $out;
    }

    /**
     * Ensamblar el mapa de condiciones de una proposal a partir de la lista de
     * secciones que la IA decidió incluir.
     *
     * Para cada una de las 11 claves: si está incluida → su texto default; si no
     * → '' (la fila queda oculta en el PDF). Siempre devuelve las 11 claves.
     *
     * @param  array  $incluidas  claves lógicas a incluir
     * @return array  clave lógica → string
     */
    public static function assemble(array $incluidas)
    {
        $defaults = self::loadDefaults();
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = in_array($key, $incluidas, true)
                ? (isset($defaults[$key]) ? $defaults[$key] : '')
                : '';
        }
        return $out;
    }

    /**
     * Renderizar un valor de condición (markdown-lite) a HTML seguro para dompdf.
     *
     * Acepta también un array (retro-compat con el JSON viejo de
     * `consideraciones_entrega`), que se renderiza como viñetas.
     *
     * @param  mixed  $value
     * @return string  HTML ('' si el valor está vacío → fila oculta)
     */
    public static function render($value)
    {
        // Retro-compat: valor array (JSON viejo) → viñetas.
        if (is_array($value)) {
            $items = array_filter(array_map('trim', $value), function ($s) {
                return $s !== '';
            });
            if (empty($items)) {
                return '';
            }
            $lis = '';
            foreach ($items as $it) {
                $lis .= '<li>' . self::inlineBold(e($it)) . '</li>';
            }
            return '<ul>' . $lis . '</ul>';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        $html = '';
        $bullets = [];

        foreach ($lines as $line) {
            $raw = trim($line);
            if ($raw === '') {
                continue; // línea en blanco = separador, se ignora
            }

            // Detectar párrafo ("> ") sobre el texto CRUDO (antes de escapar).
            if (substr($raw, 0, 2) === '> ' || $raw === '>') {
                // Cerrar la lista de viñetas acumulada antes del párrafo.
                if (!empty($bullets)) {
                    $html .= '<ul>' . implode('', $bullets) . '</ul>';
                    $bullets = [];
                }
                $paragraph = ltrim(substr($raw, 1)); // quitar ">" y el espacio
                $html .= '<p>' . self::inlineBold(e($paragraph)) . '</p>';
            } else {
                $bullets[] = '<li>' . self::inlineBold(e($raw)) . '</li>';
            }
        }

        if (!empty($bullets)) {
            $html .= '<ul>' . implode('', $bullets) . '</ul>';
        }

        return $html;
    }

    /**
     * Aplicar `**negrita**` → <strong>. El texto YA viene escapado con e(), así
     * que los marcadores `**` sobreviven al escape y solo envuelven contenido
     * seguro. No-greedy: permite varias negritas por línea.
     *
     * dompdf borra el espacio que toca el borde de un <strong> que se parte en
     * varias líneas, pegando la palabra en negrita con la siguiente
     * ("cargosadministrativos"). Se evita duplicando el espacio a AMBOS lados del
     * borde (dentro y fuera del <strong>): dompdf tendría que colapsar los dos —
     * que viven en runs de texto distintos — para pegarlas, y no lo hace; el par
     * de espacios se colapsa en uno solo visible y el ajuste de línea sigue igual.
     *
     * @param  string  $escaped
     * @return string
     */
    private static function inlineBold($escaped)
    {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $html = str_replace('</strong> ', ' </strong> ', $html);
        $html = str_replace(' <strong>', ' <strong> ', $html);
        return $html;
    }
}

<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;

/**
 * QuotationCalculationEngine — Motor de cálculo del cotizador COM-02
 *
 * Motor puro de cálculo (sin IA). Calcula costos, márgenes, IGV y totales.
 *
 * Secuencia por ítem:
 * 1. Costo base del producto
 * 2. + Costos adicionales (impresión, muestra, empaque, movilidad)
 * 3. × (1 + margen%)
 * 4. = Valor unitario
 * 5. × Cantidad
 * 6. = Valor total (base IGV)
 * 7. + IGV (18%)
 * 8. = Total final del ítem
 */
class QuotationCalculationEngine
{
    /** @var float|null */
    private $igvPercentage = null;

    /** @var float|null */
    private $sctrPercentage = null;

    public function __construct()
    {
        // Lazy: no consultar BD en constructor (falla sin contexto tenant)
    }

    /**
     * Obtener porcentaje de IGV (lazy load).
     *
     * @return float
     */
    private function getIgvPercentage()
    {
        if ($this->igvPercentage === null) {
            $this->igvPercentage = (float) QuotationConfig::getValue('igv_percentage', 18);
        }
        return $this->igvPercentage;
    }

    /**
     * Obtener porcentaje de SCTR (lazy load).
     *
     * @return float
     */
    private function getSctrPercentage()
    {
        if ($this->sctrPercentage === null) {
            $this->sctrPercentage = (float) QuotationConfig::getValue('sctr_porcentaje', 1.5);
        }
        return $this->sctrPercentage;
    }

    /**
     * Resolver el % de SCTR EFECTIVO de una precotización (COM-02):
     *   - el propio de la cotización (`sctr_porcentaje`) si tiene uno;
     *   - el global del config si es NULL (comportamiento histórico).
     *
     * @param  PreQuote|null  $prequote
     * @return float
     */
    private function resolveEffectiveSctr($prequote)
    {
        if ($prequote !== null
            && $prequote->sctr_porcentaje !== null
            && $prequote->sctr_porcentaje !== '') {
            return (float) $prequote->sctr_porcentaje;
        }
        return $this->getSctrPercentage();
    }

    /**
     * Resolver el % de SCTR EFECTIVO de un ÍTEM (COM-02, SCTR unitario) por
     * CASCADA de 3 niveles:
     *   1. el propio del ítem (`sctr_porcentaje`) si tiene uno;
     *   2. el de la cotización (`prequote.sctr_porcentaje`) si el ítem no tiene;
     *   3. el global del config si la cotización tampoco tiene.
     *
     * Un `0` explícito en el ítem aplica 0% (no cae a la herencia).
     *
     * @param  PreQuoteItem  $item
     * @return float
     */
    private function resolveEffectiveSctrForItem(PreQuoteItem $item)
    {
        if ($item->sctr_porcentaje !== null && $item->sctr_porcentaje !== '') {
            return (float) $item->sctr_porcentaje;
        }
        return $this->resolveEffectiveSctr($item->prequote);
    }

    /**
     * Recalcular toda una precotización (todos los ítems + totales).
     *
     * @param  PreQuote  $prequote
     * @return PreQuote
     */
    public function recalculate(PreQuote $prequote)
    {
        $items = $prequote->items;

        $subtotal = 0;
        $totalIgv = 0;
        $total = 0;

        foreach ($items as $item) {
            // El SCTR es por cotización: el ítem ya conoce su prequote (evita el
            // N+1 al resolver el % dentro de recalculateItem).
            $item->setRelation('prequote', $prequote);
            $this->recalculateItem($item);

            $subtotal += (float) $item->base_igv;
            $totalIgv += (float) $item->igv;
            $total    += (float) $item->total_item;
        }

        $prequote->update([
            'subtotal'  => round($subtotal, 2),
            'total_igv' => round($totalIgv, 2),
            'total'     => round($total, 2),
        ]);

        Log::info('QuotationCalculationEngine: precotización recalculada', [
            'prequote_id' => $prequote->id,
            'items_count' => count($items),
            'subtotal'    => $subtotal,
            'total_igv'   => $totalIgv,
            'total'       => $total,
        ]);

        return $prequote->fresh();
    }

    /**
     * Costo base de un ítem.
     *
     * [NUEVO FLUJO 2026-10-21] La fuente de verdad es la tabla
     * `quotation_items_supplier`: el costo del proveedor SELECCIONADO
     * (seleccionado = 1). Si el ítem no tiene comparativa (comisión, servicio
     * sin proveedor o ítem previo al flujo), cae al costo del ítem.
     *
     * @param  PreQuoteItem  $item
     * @return float
     */
    public function resolveCostoBase(PreQuoteItem $item)
    {
        if (strtolower(trim((string) ($item->tipo_linea ?? ''))) === 'comision') {
            return (float) $item->costo_base;
        }

        $selected = $item->itemSuppliers()
            ->where('seleccionado', 1)
            ->orderBy('id')
            ->first();

        // El ganador manda solo cuando ya tiene precio; con 0 se mantiene el costo
        // del ítem (el del catálogo, si la IA encontró el producto).
        if ($selected && (float) $selected->costo_base > 0) {
            return (float) $selected->costo_base;
        }

        return (float) $item->costo_base;
    }

    /**
     * Recalcular un ítem individual.
     *
     * @param  PreQuoteItem  $item
     * @return PreQuoteItem
     */
    public function recalculateItem(PreQuoteItem $item)
    {
        // [NUEVO FLUJO 2026-10-21] El costo base sale del proveedor
        // seleccionado en la comparativa (no de la columna del ítem).
        $costoBaseItem = $this->resolveCostoBase($item);

        // Comisión = costo fijo: el costo base ES el total directo, sin margen,
        // SCTR, IGV ni adicionales. Se persiste así para que tabla, totales de
        // la prequote y PDF cierren con el mismo número.
        if (strtolower(trim((string) ($item->tipo_linea ?? ''))) === 'comision') {
            $base = round($costoBaseItem, 2);
            $item->update([
                'costo_muestra'  => 0,
                'valor_unitario' => $base,
                'valor_total'    => $base,
                'base_igv'       => $base,
                'igv'            => 0,
                'total_item'     => $base,
            ]);
            return $item->fresh();
        }

        // COM09 — costo de muestra DERIVADO: N° de muestras × precio unitario de
        // la muestra. Sigue siendo un costo FIJO del pedido (no se multiplica por
        // la cantidad). Se persiste el valor derivado porque el PDF y los
        // reportes lo siguen usando.
        //
        // Agosto 2026 (punto 4) — el precio unitario ya no es siempre el costo
        // base. Si el ítem tiene `precio_muestra`, manda ese; si es NULL, cae al
        // costo base, que es el comportamiento histórico. Una muestra suelta suele
        // costar más que una unidad de una tirada larga, y antes no había forma de
        // reflejarlo.
        //
        // Se compara contra null y no con empty(): un `precio_muestra` de 0.00
        // significa "las muestras son gratis" y tiene que respetarse, no caer al
        // costo base.
        $precioMuestra = ($item->precio_muestra !== null && $item->precio_muestra !== '')
            ? (float) $item->precio_muestra
            : $costoBaseItem;
        $costoMuestra = (int) $item->numero_muestras * $precioMuestra;

        // COM-02 — SCTR unitario: % efectivo por CASCADA (ítem → cotización →
        // global). Se resuelve aquí porque recalculateItem también se invoca
        // suelto (QuotationProductController) sin pasar por recalculate(PreQuote).
        $sctrPct = $this->resolveEffectiveSctrForItem($item);

        // COM-02 — Costo Total de Impresión: factor manual de 4 términos, tal como
        // el Excel del cliente (AD = Z × AA × AB × AC):
        //   costo_impresion × cantidad_colores × cantidad_lados × cantidad_millar.
        // Por defecto es un costo FIJO del pedido; solo escala por cantidad si la
        // ejecutiva marca "por unidad" (ver más abajo, punto 3 de agosto 2026).
        // colores/lados/millar caen a 1 (neutro) si vienen vacíos o <= 0.
        $millar = (float) $item->cantidad_millar;
        if ($millar <= 0) {
            $millar = 1;
        }
        $costoImpresionTotal = (float) $item->costo_impresion
            * max(1, (int) $item->cantidad_colores)
            * max(1, (int) $item->cantidad_lados)
            * $millar;

        $result = $this->computeItem(
            $costoBaseItem,
            (float) $item->margen_porcentaje,
            max(1, (int) $item->quantity),
            $item->getTotalCostosAdicionales(),
            $costoMuestra,
            (float) $item->costo_movilidad,
            $costoImpresionTotal,                  // impresión ya = costo × colores × lados × millar
            (float) $item->costo_empaque,
            // Agosto 2026 (punto 3) — vuelve a respetarse impresion_por_unidad.
            //
            // Cuando se introdujo "Cantidad por Millar" este parámetro se fijó en
            // false, dando por hecho que el millar reemplazaba al flag. No lo
            // reemplaza: son dos cosas distintas. El millar es un factor del costo
            // de impresión (cuántos millares se imprimen); el flag dice si ese
            // costo se cobra una vez por pedido o por cada unidad.
            //
            // Se comportan igual que su par de empaque, que es con lo que el
            // cliente lo comparó: marcado, el total de impresión —que ya incluye
            // colores, lados y millar— se multiplica por la cantidad del pedido.
            //
            // Sin riesgo para lo ya cotizado: la columna es 0 en todos los ítems
            // existentes, así que ninguna cotización previa cambia de números.
            (bool) $item->impresion_por_unidad,
            (bool) $item->empaque_por_unidad,
            $sctrPct
        );

        $item->update([
            'costo_muestra'  => $costoMuestra,
            'valor_unitario' => $result['valor_unitario'],
            'valor_total'    => $result['valor_total'],
            'base_igv'       => $result['base_igv'],
            'igv'            => $result['igv'],
            'total_item'     => $result['total_item'],
        ]);

        return $item->fresh();
    }

    /**
     * Núcleo de cálculo de un ítem. Cadena validada contra el Excel real de
     * COMPIPRO (ProcesoProyecto_BSF, pestaña Cotizacion1):
     *
     *   T     = costo_base × (1 + markup%)            [solo el producto lleva markup]
     *   TP    = (Q × T) + costos_fijos_pedido         [muestra + movilidad + adicionales]
     *   impr  = impresion_por_unidad ? costo_impresion × Q : costo_impresion
     *   emp   = empaque_por_unidad   ? costo_empaque   × Q : costo_empaque
     *   SCTR  = sctr% × (TP + impr + emp)
     *   Total = TP + impr + emp + SCTR
     *   costo_unit_sin_igv = Total / Q
     *   IGV   = costo_unit_sin_igv × igv%             (por unidad)
     *
     * Los campos guardados conservan su semántica:
     *   valor_unitario = costo unitario SIN IGV que ve el cliente (Total / Q)
     *   valor_total / base_igv = Total del ítem SIN IGV
     *   igv / total_item = IGV y total CON IGV del ítem completo
     *
     * @return array
     */
    public function computeItem(
        $costoBase,
        $markupPorcentaje,
        $quantity,
        $costosAdicionales = 0,
        $costoMuestra = 0,
        $costoMovilidad = 0,
        $costoImpresion = 0,
        $costoEmpaque = 0,
        $impresionPorUnidad = false,
        $empaquePorUnidad = false,
        $sctrPercentage = null
    ) {
        $q = max(1, (int) $quantity);

        // 1. Precio de venta unitario del producto (único componente con markup)
        $T = $costoBase * (1 + ($markupPorcentaje / 100));

        // 2. Total Producto: variable (Q×T) + costos fijos del pedido (sin markup)
        $costosFijosPedido = $costoMuestra + $costoMovilidad + $costosAdicionales;
        $TP = ($q * $T) + $costosFijosPedido;

        // 3. Impresión y empaque: unitarios (×Q) o globales según flag
        $impresionEfectiva = $impresionPorUnidad ? ($costoImpresion * $q) : $costoImpresion;
        $empaqueEfectivo   = $empaquePorUnidad   ? ($costoEmpaque   * $q) : $costoEmpaque;

        // 4. SCTR (overhead) sobre TP + impresión + empaque. El % efectivo puede
        //    venir por parámetro (SCTR editable por cotización, COM-02); si es
        //    null se usa el global del config (comportamiento histórico intacto).
        $sctrPct = ($sctrPercentage !== null) ? (float) $sctrPercentage : $this->getSctrPercentage();
        $sctr = ($sctrPct / 100) * ($TP + $impresionEfectiva + $empaqueEfectivo);

        // 5. Total del pedido SIN IGV
        $totalPedidoSinIgv = $TP + $impresionEfectiva + $empaqueEfectivo + $sctr;

        // 6. Costo unitario sin IGV (lo que ve el cliente) = Total / Q
        //
        //    Agosto 2026 (punto 6) — Redondeo comercial. El orden importa y es el
        //    que pidió el cliente: primero el margen produce el precio y RECIÉN
        //    DESPUÉS se redondea. Al revés, el margen real dejaría de dar el
        //    objetivo, porque el redondeo movería la base sobre la que se calcula.
        //
        //    Recomponer el total desde el unitario redondeado no es opcional: si
        //    se redondeara solo el unitario, "unitario × cantidad" ya no cuadraría
        //    con el total del ítem y la cotización mostraría dos cifras que no
        //    cierran entre sí.
        //
        //    El desvío es de 4 céntimos por unidad como máximo, y siempre hacia
        //    arriba: el redondeo nunca baja un precio.
        $costoUnitSinIgv   = $this->redondeoComercial($totalPedidoSinIgv / $q);
        $totalPedidoSinIgv = $costoUnitSinIgv * $q;

        // 7. IGV (sobre el total sin IGV) + total con IGV
        $igv       = $totalPedidoSinIgv * ($this->getIgvPercentage() / 100);
        $totalItem = $totalPedidoSinIgv + $igv;

        return [
            'valor_unitario' => round($costoUnitSinIgv, 2),    // costo unitario sin IGV
            'valor_total'    => round($totalPedidoSinIgv, 2),  // total del ítem sin IGV
            'base_igv'       => round($totalPedidoSinIgv, 2),
            'igv'            => round($igv, 2),
            'total_item'     => round($totalItem, 2),
            // extras informativos (no se persisten, útiles para preview/debug)
            'precio_producto_unitario' => round($T, 2),
            'sctr'                     => round($sctr, 2),
            'ganancia_absoluta'        => round(($q * $T) - ($q * $costoBase), 2),
        ];
    }

    /**
     * Agosto 2026 (punto 6) · Redondeo comercial del precio unitario.
     *
     * El precio unitario termina siempre en 0, 5 o 9, y siempre hacia arriba:
     * redondear nunca baja un precio. Sobre la última cifra de céntimos:
     *
     *     1, 2, 3, 4  →  5        6, 7, 8  →  9        0, 5, 9  →  se queda
     *
     * Ejemplos del cliente:
     *     12.54 → 12.55 · 12.56 → 12.59 · 12.61 → 12.65 · 15.44 → 15.45
     *
     * El `round(..., 6)` antes del `ceil` protege del error de coma flotante:
     * `8.20 * 100` da 820.0000000000001 y un `ceil` directo lo llevaría a 821,
     * convirtiendo un 8.20 que ya cumple la regla en 8.25.
     *
     * Espejo exacto de `redondeoComercial` en
     * modules/Comercial/Resources/assets/js/lib/costeoReal.js. Si se cambia una,
     * hay que cambiar la otra o la pantalla mostraría un precio distinto del que
     * termina guardado.
     *
     * @param  float $valor
     * @return float
     */
    public function redondeoComercial($valor)
    {
        $n = (float) $valor;

        // Un ítem todavía sin costo vale 0, no 0.05: no se redondea hacia arriba.
        if ($n <= 0) {
            return 0.0;
        }

        $centimos = (int) ceil(round($n * 100, 6));
        $d = $centimos % 10;

        if ($d === 0 || $d === 5 || $d === 9) {
            return $centimos / 100;
        }

        $centimos += ($d < 5) ? (5 - $d) : (9 - $d);

        return $centimos / 100;
    }

    /**
     * Calcular los valores de un ítem sin guardar (preview).
     *
     * @param  float  $costoBase
     * @param  float  $costosAdicionales
     * @param  float  $margenPorcentaje
     * @param  int    $quantity
     * @return array
     */
    public function calculatePreview($costoBase, $costosAdicionales, $margenPorcentaje, $quantity)
    {
        // Delegar al núcleo nuevo. Los componentes de costo extra (muestra,
        // movilidad, impresión, empaque) van en 0 — el preview simple solo
        // considera costo_base + costos_adicionales como antes.
        return $this->computeItem(
            (float) $costoBase,
            (float) $margenPorcentaje,
            max(1, (int) $quantity),
            (float) $costosAdicionales
        );
    }
}

/**
 * COM-02 · Costo Real / Margen Real de una pre-cotización.
 * ---------------------------------------------------------------------------
 * Reproduce EXACTAMENTE la cadena de `costeoBreakdown` de
 * `view/requerimientos/TabPreCotizacion.vue` (donde la ejecutiva ve el Margen
 * Real en vivo). Se factoriza acá para que el campo "Margen (%)" del Proyecto
 * (view/proyectos/form.vue) muestre el MISMO número que la pestaña
 * Pre-Cotización, sin reescribir la fórmula con el riesgo de que se desincronice.
 *
 * ⚠️ Si cambia la fórmula del costeo en TabPreCotizacion.vue, actualizar acá
 *    (es la fuente que consume el Proyecto). Idealmente, a futuro, que
 *    TabPreCotizacion.vue también importe de este helper.
 */

/**
 * Agosto 2026 (punto 6) · Redondeo comercial del precio unitario.
 * ---------------------------------------------------------------------------
 * El precio unitario termina siempre en 0, 5 o 9, y siempre hacia arriba: nunca
 * se baja un precio por redondear. Sobre la última cifra de céntimos:
 *
 *     1, 2, 3, 4  →  5          6, 7, 8  →  9          0, 5, 9  →  se queda
 *
 * Ejemplos del cliente: 12.54 → 12.55 · 12.56 → 12.59 · 12.61 → 12.65 · 15.44 → 15.45
 *
 * El `toFixed(6)` antes del `ceil` no es cosmético: en coma flotante
 * `8.20 * 100` da 820.0000000000001, y un `ceil` directo lo subiría a 821, o sea
 * convertiría un 8.20 —que ya cumple la regla— en 8.25. Recortando el ruido a 6
 * decimales el valor vuelve a ser 820 y se queda quieto.
 *
 * Espejo exacto de `QuotationCalculationEngine::redondeoComercial` (PHP). Si se
 * cambia una, hay que cambiar la otra o la pantalla mostraría un precio distinto
 * del que guarda el backend.
 *
 * @param  {number|string} valor
 * @return {number}
 */
export function redondeoComercial(valor) {
  var n = parseFloat(valor)
  // Un ítem todavía sin costo vale 0, no 0.05: no se redondea hacia arriba.
  if (isNaN(n) || n <= 0) return 0

  var centimos = Math.ceil(Number((n * 100).toFixed(6)))
  var d = centimos % 10
  if (d === 0 || d === 5 || d === 9) return centimos / 100

  centimos += (d < 5) ? (5 - d) : (9 - d)
  return centimos / 100
}

/**
 * % de SCTR que aplica a la cotización completa.
 * Espejo de `parseNumericFields` (TabPreCotizacion.vue): el % propio de la
 * pre-cotización; si viene vacío, el default del config; si tampoco, 1.5%.
 *
 * @param {Object} prequote  objeto crudo de la pre-cotización (detailResponse.data.prequote)
 * @return {number}
 */
export function sctrCotizacionDe(prequote) {
  if (!prequote) return 1.5
  var propio = prequote.sctr_porcentaje
  if (propio !== null && propio !== undefined && propio !== '') {
    var n = parseFloat(propio)
    if (!isNaN(n)) return n
  }
  var def = parseFloat(prequote.sctr_config_default)
  return isNaN(def) ? 1.5 : def
}

/**
 * % de SCTR efectivo de un ítem: su override si lo tiene; si no, el de la
 * cotización. Espejo de `sctrEfectivo` (TabPreCotizacion.vue).
 */
function sctrEfectivoItem(item, sctrCotizacion) {
  var raw = item ? item.sctr_porcentaje : null
  if (raw === null || raw === undefined || (typeof raw === 'string' && raw.trim() === '')) {
    return sctrCotizacion
  }
  var n = parseFloat(raw)
  return isNaN(n) ? sctrCotizacion : n
}

/**
 * Total sin IGV (cadena de venta) y Costo Real Total de un ítem.
 * Copia fiel de `costeoBreakdown` (TabPreCotizacion.vue): el producto entra con
 * margen para la venta y a costo base para el costo real; impresión y empaque
 * EFECTIVOS (impresión × colores × lados × millar con clamp 0→1; empaque × cant.
 * cuando es "por unidad").
 */
function totalesItem(item, sctrCotizacion) {
  var q = parseInt(item.quantity) || 1
  if (q < 1) q = 1
  var costoBase = parseFloat(item.costo_base) || 0
  var markup    = parseFloat(item.margen_porcentaje) || 0
  // Agosto 2026 (punto 4): el precio de la muestra puede diferir del costo base.
  // Se compara contra null/'' y no con `||`: un precio de 0 significa "muestras
  // gratis" y debe respetarse. Espejo de precioMuestraEfectivo() en
  // TabPreCotizacion.vue y de recalculateItem() en el motor de Marketing.
  var precioMuestra = costoBase
  var pmRaw = item.precio_muestra
  if (pmRaw !== null && pmRaw !== undefined && pmRaw !== '') {
    var pmNum = parseFloat(pmRaw)
    if (!isNaN(pmNum)) precioMuestra = pmNum
  }
  var muestra   = (parseInt(item.numero_muestras) || 0) * precioMuestra
  var movilidad = parseFloat(item.costo_movilidad) || 0
  var impresion = parseFloat(item.costo_impresion) || 0
  var empaque   = parseFloat(item.costo_empaque) || 0
  var colores = parseInt(item.cantidad_colores);   if (isNaN(colores) || colores <= 0) colores = 1
  var lados   = parseInt(item.cantidad_lados);     if (isNaN(lados)   || lados   <= 0) lados   = 1
  var millar  = parseFloat(item.cantidad_millar);  if (isNaN(millar)  || millar  <= 0) millar  = 1

  var T  = costoBase * (1 + markup / 100)
  var TP = (q * T) + muestra + movilidad
  // Agosto 2026 (punto 3): igual que el empaque, la impresión puede cobrarse
  // una vez por pedido o por cada unidad. El millar ya está incluido arriba;
  // el flag decide si además escala por la cantidad. Debe coincidir con
  // QuotationCalculationEngine::computeItem o la pantalla mostraría un total
  // distinto del que guarda el backend.
  var imprBase = impresion * colores * lados * millar
  var imprEf = item.impresion_por_unidad ? (imprBase * q) : imprBase
  var empEf  = item.empaque_por_unidad ? (empaque * q) : empaque
  var sp = sctrEfectivoItem(item, sctrCotizacion)
  var sctr = (sp / 100) * (TP + imprEf + empEf)
  // Agosto 2026 (punto 6): el unitario se redondea y el total se recompone desde
  // él (total = unitario × cantidad), igual que computeItem en el backend. Si acá
  // no se replicara, el Margen (%) del Proyecto dejaría de coincidir con el de la
  // Pre-Cotización, que es justo lo que este helper existe para evitar.
  var unit = redondeoComercial((TP + imprEf + empEf + sctr) / q)
  var totalSinIgv = unit * q

  var baseCostoReal  = (costoBase * q) + muestra + movilidad + imprEf + empEf
  var costoRealTotal = baseCostoReal + (sp / 100) * baseCostoReal
  return { totalSinIgv: totalSinIgv, costoRealTotal: costoRealTotal }
}

/**
 * Resumen del Margen Real de un conjunto de ítems.
 * Margen Real % = (Σ Utilidad Real / Σ Total sin IGV) × 100, igual que el
 * encabezado de la Pre-Cotización (`margenRealTotalPct`).
 *
 * @return {{ totalSinIgv:number, utilidadReal:number, margenRealPct:number }}
 */
export function margenRealDeItems(items, sctrCotizacion) {
  var sumTotal = 0, sumCosto = 0
  var lista = Array.isArray(items) ? items : []
  for (var i = 0; i < lista.length; i++) {
    var r = totalesItem(lista[i], sctrCotizacion)
    sumTotal += r.totalSinIgv
    sumCosto += r.costoRealTotal
  }
  var utilidad = sumTotal - sumCosto
  return {
    totalSinIgv: sumTotal,
    utilidadReal: utilidad,
    margenRealPct: sumTotal > 0 ? (utilidad / sumTotal) * 100 : 0,
  }
}

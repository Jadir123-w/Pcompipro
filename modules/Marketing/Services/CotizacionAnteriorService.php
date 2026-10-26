<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;

/**
 * Producto ya cotizado en otro requerimiento.
 *
 * Al crear un ítem de pre-cotización se busca el mismo producto (nombre con 90% o más
 * de similitud) en pre-cotizaciones emitidas, aprobadas, validadas o convertidas de otros
 * requerimientos. Si aparece, el ítem nuevo hereda el margen y el proveedor ganador
 * con su costo y tiempo de entrega, y guarda de dónde vino para mostrarlo en pantalla.
 * No usa IA: es una comparación de texto, rápida y siempre con el mismo resultado.
 */
class CotizacionAnteriorService
{
    const SIMILITUD_MINIMA = 0.90;
    const CANDIDATOS_MAXIMOS = 300;

    /** @var ProductMatchingService */
    protected $matcher;

    public function __construct(ProductMatchingService $matcher)
    {
        $this->matcher = $matcher;
    }

    /**
     * Busca el ítem cotizado antes más parecido al nombre dado (el más reciente si empatan).
     *
     * @param  PreQuoteItem  $item  Ítem nuevo (se excluyen su pre-cotización y su requerimiento)
     * @return array|null  ['item' => PreQuoteItem, 'similitud' => float]
     */
    public function buscar(PreQuoteItem $item)
    {
        $nombre = trim((string) $item->product_name);
        if ($nombre === '' || strtolower((string) $item->tipo_linea) === 'comision') {
            return null;
        }

        try {
            $meses = (int) QuotationConfig::getValue('defop_historico_meses_lookback', 12);
        } catch (\Exception $e) {
            $meses = 12;
        }
        $desde = now()->subMonths($meses > 0 ? $meses : 12);
        $requerimientoId = optional($item->prequote)->requerimiento_id;

        $candidatos = PreQuoteItem::query()
            ->where('prequote_id', '!=', $item->prequote_id)
            ->where('tipo_linea', '!=', 'comision')
            ->whereHas('prequote', function ($q) use ($desde, $requerimientoId) {
                $q->where('created_at', '>=', $desde)
                  ->where(function ($e) {
                      $e->whereIn('estado', ['propuesta_emitida', 'validada', 'aprobada'])->orWhereNotNull('quotation_id');
                  });
                if ($requerimientoId) {
                    $q->where(function ($r) use ($requerimientoId) {
                        $r->whereNull('requerimiento_id')->orWhere('requerimiento_id', '!=', $requerimientoId);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(self::CANDIDATOS_MAXIMOS)
            ->get();

        $mejor = null;
        foreach ($candidatos as $candidato) {
            $similitud = $this->matcher->similitudNombres($nombre, $candidato->product_name);
            if ($similitud >= self::SIMILITUD_MINIMA && (!$mejor || $similitud > $mejor['similitud'])) {
                $mejor = ['item' => $candidato, 'similitud' => $similitud];
            }
        }

        return $mejor;
    }

    /**
     * Si el producto ya se cotizó, copia al ítem nuevo el margen y el proveedor ganador
     * (con costo y tiempo de entrega). La cantidad siempre es la del ítem nuevo.
     *
     * @param  PreQuoteItem  $item
     * @return bool  true si encontró y aplicó una cotización anterior
     */
    public function aplicar(PreQuoteItem $item)
    {
        try {
            $previo = $this->buscar($item);
            if (!$previo) {
                return false;
            }

            /** @var PreQuoteItem $anterior */
            $anterior = $previo['item'];
            $prequoteAnterior = $anterior->prequote;
            $ganador = $anterior->winningItemSupplier()->first();

            // Ítems del flujo nuevo: el proveedor vive en la comparativa; los anteriores
            // lo guardaban en el propio ítem.
            $supplierId = $ganador ? $ganador->supplier_id : $anterior->supplier_id;
            $costo = $ganador && (float) $ganador->costo_base > 0 ? (float) $ganador->costo_base : (float) $anterior->costo_base;
            // "Por confirmar" era el texto de relleno de la comparativa: no es un tiempo real.
            $tiempo = trim((string) ($ganador ? $ganador->tiempo_estimado_dias : ''));
            if ($tiempo === '' || strcasecmp($tiempo, 'Por confirmar') === 0) {
                $tiempo = trim((string) $anterior->tiempo_estimado_dias);
            }
            $fecha = optional($prequoteAnterior->created_at)->format('d/m/Y');
            $codigoReq = $prequoteAnterior->requerimiento_id
                ? 'REQ-' . str_pad((string) $prequoteAnterior->requerimiento_id, 4, '0', STR_PAD_LEFT)
                : 'pre-cotización #' . $prequoteAnterior->id;

            $proveedor = $supplierId
                ? \App\Models\Tenant\Person::where('type', 'suppliers')->find($supplierId)
                : null;

            $cambios = [
                'margen_porcentaje'   => (float) $anterior->margen_porcentaje,
                'cotizacion_anterior' => [
                    'prequote_id'      => (int) $prequoteAnterior->id,
                    'requerimiento_id' => $prequoteAnterior->requerimiento_id ? (int) $prequoteAnterior->requerimiento_id : null,
                    'codigo'           => $codigoReq,
                    'item_id'          => (int) $anterior->id,
                    'producto'         => (string) $anterior->product_name,
                    'fecha'            => $fecha,
                    'similitud'        => round($previo['similitud'] * 100),
                ],
            ];
            if ($tiempo !== '') {
                $cambios['tiempo_estimado_dias'] = $tiempo;
            }

            if ($proveedor && $costo > 0) {
                $nombreProveedor = trim((string) ($proveedor->trade_name ?: $proveedor->name));
                $item->itemSuppliers()->create([
                    'supplier_id'          => (int) $proveedor->id,
                    'supplier_name'        => $nombreProveedor !== '' ? $nombreProveedor : 'Proveedor',
                    'origen'               => \Modules\Marketing\Models\QuotationItemSupplier::ORIGEN_MANUAL,
                    'product_reference'    => $item->product_name,
                    'costo_base'           => $costo,
                    'tiempo_estimado_dias' => $tiempo !== '' ? $tiempo : null,
                    'observaciones'        => 'Cotizado antes en ' . $codigoReq . ($fecha ? ' (' . $fecha . ')' : '') . '.',
                    'seleccionado'         => 1,
                    'user_id'              => $prequoteAnterior->user_id,
                ]);
            } elseif ($costo > 0) {
                // Sin un proveedor válido se conserva al menos el costo cotizado.
                $cambios['costo_base'] = $costo;
            }

            $item->update($cambios);

            return true;
        } catch (\Exception $e) {
            // Buscar lo cotizado antes es una ayuda: si falla, el ítem se crea igual.
            Log::warning('CotizacionAnteriorService: no se pudo aplicar la cotización anterior', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}

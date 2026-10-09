<?php

namespace Modules\Report\Helpers;

use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Person;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\Item;
use Carbon\Carbon;
use App\CoreFacturalo\Helpers\Functions\FunctionsHelper;
use Modules\Sale\Models\PendingAccountCommission;

class UserCommissionHelper
{

    public static function getCommission($user, $utilities){

        $type = $user->user_commission->type;
        $amount = $user->user_commission->amount;

        $commission = ($type == 'amount') ? $utilities['total_utility'] * $amount : ($utilities['total_utility'] * ($amount / 100));

        return number_format($commission, 2, ".", "");

    }


    public static function getUtilities($sale_notes, $documents){

        $sale_notes_utility = self::getUtilityRecords($sale_notes);
        $documents_utility = self::getUtilityRecords($documents);

        return [
            'sale_notes_utility' => number_format($sale_notes_utility, 2, ".", ""),
            'documents_utility' => number_format($documents_utility, 2, ".", ""),
            'total_utility' => number_format($documents_utility + $sale_notes_utility, 2, ".", ""),
        ];

    }


    public static function getUtilityRecords($records){

        return $records->sum(function($record){

            return $record->items->sum(function($item) use($record){

                $total_item_purchase = self::getPurchaseUnitPrice($item) * $item->quantity;
                $total_item_sale = self::calculateTotalCurrencyType($record, $item->total);
                $total_item = $total_item_sale - $total_item_purchase;
                
                return ($record->document_type_id === '07') ? $total_item * -1 : $total_item;
    
            });
             
        });

    }

    
    public static function getPurchaseUnitPrice($record){

        $purchase_unit_price = 0;

        if($record->item->unit_type_id != 'ZZ'){

            if($record->relation_item->purchase_unit_price > 0){

                $purchase_unit_price = $record->relation_item->purchase_unit_price;

            }else{

                $purchase_item = PurchaseItem::select('unit_price')->where('item_id', $record->item_id)->latest('id')->first();
                $purchase_unit_price = ($purchase_item) ? $purchase_item->unit_price : $record->unit_price;

            }

        }

        return $purchase_unit_price;
    }
    
    public static function calculateTotalCurrencyType($record, $total)
    {
        return ($record->currency_type_id === 'USD') ? $total * $record->exchange_rate_sale : $total;
    }

        
    /**
     * 
     * Obtener totales para reporte de comisiones
     * Usado en:
     * Modules\Report\Http\Resources\ReportCommissionCollection
     * Formato excel y pdf (blade) de reportes comisiones
     *
     * @param $user
     * @return array
     */
    public static function getDataForReportCommission($user, $request)
    {

        $requestInner = $request->all();
        $date_start = $requestInner['date_start'];
        $date_end = $requestInner['date_end'];
        $establishment_id = $requestInner['establishment_id'];
        $user_type = $requestInner['user_type'];
        $user_seller_id = $requestInner['user_seller_id'];

        $row_user_id = $user->id;

        FunctionsHelper::setDateInPeriod($requestInner, $date_start, $date_end);

        //si user_seller_id es null, en la consulta se usara el id del usuario de la fila
        $documents = Document::whereFilterCommission($date_start, $date_end, $establishment_id, $user_type, $user_seller_id, $row_user_id)->get();
        $sale_notes = SaleNote::whereFilterCommission($date_start, $date_end, $establishment_id, $user_type, $user_seller_id, $row_user_id)->get();
        
        $sale_notes = $sale_notes->filter(function($sn) {
            return !is_null($sn->document_id) ? false : true;
        });
        
        $total_commision = 0;

        $total_transactions_document = $documents->count();
        $total_transactions_sale_note = $sale_notes->count();

        $acum_sales_document = $documents->sum(function($document){ return $document->getTotalByDocumentType();});
        $acum_sales_sale_note = $sale_notes->sum('total');

        $total_commision_document = self::getTotalCommision($documents);
        $total_commision_sale_note = self::getTotalCommision($sale_notes);

        return [
            'id' => $user->id,
            'user_name' => $user->name,

            'acum_sales' => number_format($acum_sales_document + $acum_sales_sale_note, 2),
            'acum_sales_document' => $acum_sales_document,
            'acum_sales_sale_note' => $acum_sales_sale_note,

            'total_commision' => number_format($total_commision_document + $total_commision_sale_note, 2),
            'total_commision_sale_note' => $total_commision_sale_note,
            'total_commision_document' => $total_commision_document,

            'total_transactions' => $total_transactions_document + $total_transactions_sale_note,
            'total_transactions_document' => $total_transactions_document,
            'total_transactions_sale_note' => $total_transactions_sale_note,
        ];

    }

        
    /**
     * Obtener total de comisiones
     * 
     * Usado para:
     * Documents
     * SaleNotes
     *
     * @param  mixed $records
     * @return float
     */
    public static function getTotalCommision($records){

        return $records->sum(function($record){
            return $record->items->sum(function($item) use($record){

                $total_commision = 0;

                if ($item->relation_item->commission_amount) 
                {
                    if (!$item->relation_item->commission_type || $item->relation_item->commission_type == 'amount') {
                        $total_commision = $item->quantity * $item->relation_item->commission_amount;
                    } else {
                        $total_commision = $item->quantity * $item->unit_price * ($item->relation_item->commission_amount / 100);
                    }
                }

                return ($record->document_type_id === '07') ? $total_commision * -1 : $total_commision;
            });
        });

    }


    /**
     * Devuelve los datos formateados para el reporte de cuentas pendientes por vendedor.
     * Incluye id, nombre, monto cobrado y comisión.
     *
     * @param $user
     * @param $request
     * @param int $days_expired
     * @return array
     */
    public static function getDataForPendingAccountCommissionReport($user, $request, $finances = [])
    {
        $requestInner = $request->all();
        $date_start = $requestInner['date_start'];
        $date_end = $requestInner['date_end'];
        $establishment_id = $requestInner['establishment_id'] ?? null;
        $user_type = $requestInner['user_type'] ?? null;
        $user_seller_id = $requestInner['user_seller_id'] ?? null;
        $estados_validos_comision = ['01', '03', '05', '07'];
        $row_user_id = $user->id;
        $restriction_enabled = $finances->restriction_expired_debt ?? false;
        $days_expired = $finances->max_expired_days ?? 0;

        FunctionsHelper::setDateInPeriod($requestInner, $date_start, $date_end);

        $documents = Document::query()
        ->where('payment_condition_id', '02')
        ->where('state_type_id', '!=', '11')
        ->leftJoin('notes', 'documents.id', '=', 'notes.affected_document_id')
        ->select('documents.*', 'notes.affected_document_id')
        ->whereNull('notes.affected_document_id')
        ->whereBetween('date_of_issue', [$date_start, $date_end]);

        $sale_notes = SaleNote::whereHas('payments', function($q) use ($date_start, $date_end) {
            $q->whereBetween('date_of_payment', [$date_start, $date_end]);
        })
        ->where('payment_condition_id', '02')
        ->where('state_type_id', '!=', '11');

        // Si necesitas filtrar por sucursal, usuario, vendedor, etc., hazlo aquí, pero NO por fechas de emisión/vencimiento:
        if ($establishment_id) {
            $documents = $documents->where('establishment_id', $establishment_id);
            $sale_notes = $sale_notes->where('establishment_id', $establishment_id);
        }
        if ($user_seller_id) {
            $documents = $documents->where('seller_id', $user_seller_id);
            $sale_notes = $sale_notes->where('seller_id', $establishment_id);
        }
        $documents = $documents->get();
        $sale_notes = $sale_notes->get();

        $total_dentro_rango = 0;
        $total_commission = 0;
        $total_fuera_rango = 0;

        $pending_commission = PendingAccountCommission::where('seller_id', $user->id)->first();
        $commission_type = $pending_commission->commission_type ?? 'amount';
        $commission_value = $pending_commission->amount ?? 0;
        
        $documents = $documents->concat($sale_notes);
        $pagos_detalles = [];
        // dd($documents);
        foreach ($documents as $document) {
            $date_of_due = optional($document->invoice)->date_of_due;
            if ($document instanceof SaleNote) {
                $date_of_due = $document->date_of_due;
            } 
            $monto_cobrado = 0;
            $comision_sumada = 0;

            $estado_valido = in_array($document->state_type_id, $estados_validos_comision);

            if ($document->seller_id == $user->id && $estado_valido) {
                foreach ($document->payments as $payment) {
                    $comision = 0;
                    $aplica_comision = false;
                    $motivo_comision = '';
                    if ($date_of_due){
                        $fecha_pago = Carbon::parse($payment->date_of_payment);
                        $fecha_venc = Carbon::parse($date_of_due);
                        // Reglas de comisión
                        // $pago_al_contado = $document->payment_condition_id === '01';
                        $antes_del_vencimiento = $fecha_pago->lte($fecha_venc);
                        $dias_despues_vencimiento = $fecha_venc->diffInDays($fecha_pago, false);
                        $dentro_rango_maximo = $restriction_enabled
                            ? ($dias_despues_vencimiento >= 0 && $dias_despues_vencimiento <= $days_expired)
                            : false;

                        $aplica_comision =$antes_del_vencimiento || $dentro_rango_maximo;
                        $motivo_comision = $aplica_comision
                        ? ($antes_del_vencimiento ? 'antes_de_vencimiento' : 'dentro_de_plazo_post_vencimiento')
                        : 'no_aplica';

                        if (!$aplica_comision) {
                            $monto_cobrado += $payment->payment;
                            $total_fuera_rango  += $monto_cobrado;
                        }
                    } else {
                        $aplica_comision = true;
                        $motivo_comision = 'sin_fecha_vencimiento';
                    }
                    if ($aplica_comision) {
                        $monto_cobrado += $payment->payment;

                        if ($commission_type === 'monto' || $commission_type === 'amount') {
                            $comision = $commission_value;
                        } elseif ($commission_type === 'porcentaje' || $commission_type === 'percent') {
                            $comision = $payment->payment * ($commission_value / 100);
                        }

                        $comision_sumada += $comision;
                        $total_commission += $comision;
                        $total_dentro_rango += $monto_cobrado;
                    }

                    $pagos_detalles[] = [
                        'fecha_pago'      => $fecha_pago->toDateString(),
                        'fecha_venc'      => $fecha_venc->toDateString(),
                        'pago'            => $payment->payment,
                        'comision'        => $comision,
                        'tipo_comision'   => $commission_type,
                        'document' => $document->document_type_id.' - '.$document->series.'-'.$document->number,
                        'condicion_pago'  => $document->payment_condition_id,
                        'estado_doc'      => $document->state_type_id,
                        'estado_valido'   => $estado_valido,
                        'aplica_comision' => $aplica_comision,
                        'motivo_comision' => $motivo_comision,
                    ];
                }

            }

        }

        if (!$restriction_enabled) {
            return [
                'id' => $user->id,
                'user_name' => $user->name,
                'total_within_range' => number_format($total_dentro_rango, 2, ".", ""),
                'commission' => number_format(0, 2, ".", ""),
            ];
        }

        return [
            'id' => $user->id,
            'user_name' => $user->name,
            'total_within_range' => number_format($total_dentro_rango, 2, ".", ""),
            'total_completed' => number_format($total_dentro_rango + $total_fuera_rango, 2, ".", ""),
            'commission' => number_format($total_commission, 2, ".", ""),
            'total_out_of_range' => number_format($total_fuera_rango, 2, ".", "")
        ];
    }
}

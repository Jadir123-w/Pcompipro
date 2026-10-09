<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega Yape a las formas de pago.
 *
 * Va en `payment_method_types`, que es la tabla INTERNA del ERP (Efectivo,
 * Transferencia, Tarjeta, etc.) y es la que usa Caja Chica.
 *
 * ⚠️ NO se toca `cat_payment_method_types`: esa es el catálogo oficial de SUNAT
 * (001 Depósito en cuenta, 003 Transferencia de fondos, …) que interviene en la
 * facturación electrónica. Agregar un medio de pago inexistente en ese catálogo
 * podría hacer que SUNAT rechace comprobantes.
 *
 * Se usa el id '11' por ser el primero libre (la tabla llega hasta '10').
 * Idempotente: no inserta si ya existe un Yape.
 */
class AddYapeToPaymentMethodTypes extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('payment_method_types')) {
            return;
        }

        $yaExiste = DB::connection('tenant')
            ->table('payment_method_types')
            ->where('id', '11')
            ->orWhere('description', 'Yape')
            ->exists();

        if ($yaExiste) {
            return;
        }

        DB::connection('tenant')->table('payment_method_types')->insert(array(
            'id'          => '11',
            'description' => 'Yape',
            'has_card'    => 0,
            'charge'      => null,
            'number_days' => null,
            'is_credit'   => 0,
            // Yape es pago inmediato, igual que el efectivo: no genera crédito.
            'is_cash'     => 1,
        ));
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('payment_method_types')) {
            return;
        }

        // Solo se borra si ningún movimiento lo dejó referenciado.
        $enUso = Schema::connection('tenant')->hasTable('small_cash_transactions')
            && DB::connection('tenant')
                ->table('small_cash_transactions')
                ->where('payment_method_type_id', '11')
                ->exists();

        if (!$enUso) {
            DB::connection('tenant')
                ->table('payment_method_types')
                ->where('id', '11')
                ->where('description', 'Yape')
                ->delete();
        }
    }
}

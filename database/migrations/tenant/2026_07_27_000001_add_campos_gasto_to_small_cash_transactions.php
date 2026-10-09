<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos nuevos en los movimientos de Caja Chica.
 *
 * El cliente necesita registrar, además de lo que ya se guarda:
 *   - ruta         : recorrido del gasto (ej. "Oficina - Jesús María"). Texto libre.
 *   - responsable  : persona que EJECUTÓ el gasto. Es distinto del usuario del
 *                    sistema (que es quien registra el movimiento). Texto libre.
 *   - proyecto     : proyecto al que se imputa el gasto. Admite un proyecto real
 *                    del sistema, un texto libre (ej. "Feria Compipro") o vacío.
 *   - supplier_id  : enlace opcional al proveedor del maestro de personas. El
 *                    nombre se sigue guardando en supplier_customer, de modo que
 *                    los movimientos ya registrados se siguen viendo igual.
 *
 * Todos son opcionales: el pedido pide expresamente no bloquear la operación
 * diaria cuando no hay catálogo previo (movilidad, taxis, ferias, etc.).
 *
 * Se deja proyecto como texto (sin llave foránea) siguiendo la indicación del
 * documento del cliente: "Mantener como string Responsable, Referencias de
 * proyecto interno y Rutas para evitar bloqueos por falta de catálogo".
 *
 * Idempotente: solo agrega las columnas que falten.
 */
class AddCamposGastoToSmallCashTransactions extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('small_cash_transactions')) {
            return;
        }

        Schema::connection('tenant')->table('small_cash_transactions', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('small_cash_transactions', 'ruta')) {
                $table->string('ruta', 191)->nullable()->after('category');
            }
            if (!Schema::connection('tenant')->hasColumn('small_cash_transactions', 'proyecto')) {
                $table->string('proyecto', 191)->nullable()->after('ruta');
            }
            if (!Schema::connection('tenant')->hasColumn('small_cash_transactions', 'responsable')) {
                $table->string('responsable', 191)->nullable()->after('proyecto');
            }
            if (!Schema::connection('tenant')->hasColumn('small_cash_transactions', 'supplier_id')) {
                $table->unsignedInteger('supplier_id')->nullable()->after('supplier_customer');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('small_cash_transactions')) {
            return;
        }

        Schema::connection('tenant')->table('small_cash_transactions', function (Blueprint $table) {
            foreach (array('ruta', 'proyecto', 'responsable', 'supplier_id') as $columna) {
                if (Schema::connection('tenant')->hasColumn('small_cash_transactions', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuotationPrequoteHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_prequote_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('prequote_id')
                ->comment('FK a quotation_prequotes');

            $table->integer('version')
                ->comment('Versión de la precotización en el momento del cambio');

            $table->string('action', 100)
                ->comment('Acción: created, item_edited, item_added, item_removed, status_changed, recalculated, approved, converted');

            $table->text('changes')->nullable()
                ->comment('JSON: detalle de los cambios realizados');

            $table->unsignedInteger('user_id')->nullable()
                ->comment('FK al usuario que realizó el cambio');

            $table->timestamp('created_at')->useCurrent();

            // ── Índices ──
            $table->index('prequote_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_prequote_history');
    }
}

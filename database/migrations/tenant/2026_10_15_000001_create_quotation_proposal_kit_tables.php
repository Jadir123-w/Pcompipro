<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kits de propuesta: agrupan ítems de la pre-cotización en un paquete comercial con
 * precio consolidado (quotation_proposal_kits + quotation_proposal_kit_items).
 *
 * Idempotente: en algunas empresas las tablas ya se crearon con una versión anterior
 * de esta misma migración o con el script manual.
 */
class CreateQuotationProposalKitTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('quotation_proposal_kits')) {
            Schema::create('quotation_proposal_kits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('proposal_id')
                    ->comment('FK a quotation_proposals (borrador 1:1 del prequote)');
                $table->string('nombre', 191);
                $table->text('descripcion')->nullable();
                $table->string('presentacion', 191)->nullable()
                    ->comment('Empaque / presentación del kit');
                $table->mediumText('imagen')->nullable()
                    ->comment('Path relativo de la foto propia del kit');
                $table->integer('cantidad')->default(1)
                    ->comment('Cantidad del kit (= la de todos sus componentes)');
                $table->decimal('precio_kit', 12, 2)->default(0)
                    ->comment('Snapshot del consolidado al guardar');
                $table->timestamps();

                $table->foreign('proposal_id', 'fk_pkit_proposal')
                    ->references('id')->on('quotation_proposals')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('quotation_proposal_kit_items')) {
            Schema::create('quotation_proposal_kit_items', function (Blueprint $table) {
                $table->unsignedBigInteger('kit_id');
                $table->unsignedBigInteger('prequote_item_id');
                $table->decimal('cantidad', 12, 2)->default(1)
                    ->comment('Siempre igual a la cantidad del kit y del ítem');
                $table->timestamps();

                $table->primary(['kit_id', 'prequote_item_id']);
                $table->foreign('kit_id', 'fk_pkititems_kit')
                    ->references('id')->on('quotation_proposal_kits')
                    ->onDelete('cascade');
                $table->foreign('prequote_item_id', 'fk_pkititems_item')
                    ->references('id')->on('quotation_prequote_items')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('quotation_proposal_kit_items');
        Schema::dropIfExists('quotation_proposal_kits');
    }
}

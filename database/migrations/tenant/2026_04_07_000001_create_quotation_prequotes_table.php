<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuotationPrequotesTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_prequotes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Origen ──
            $table->unsignedInteger('requerimiento_id')->nullable()
                ->comment('FK al requerimiento del módulo Comercial (nullable para entrada manual)');

            $table->unsignedInteger('empresa_id')->nullable()
                ->comment('FK a la empresa del cliente');

            $table->unsignedInteger('contacto_id')->nullable()
                ->comment('FK al contacto del cliente');

            // ── Versionado ──
            $table->integer('version')->default(1)
                ->comment('Número de versión de la precotización');

            // ── Estado ──
            $table->string('estado', 50)->default('borrador')
                ->comment('borrador, en_revision, ajustada, validada, propuesta_emitida, aprobada, rechazada, convertida_en_proyecto');

            // ── Contenido ──
            $table->string('titulo', 255)->nullable()
                ->comment('Título de la precotización');

            $table->text('descripcion_original')->nullable()
                ->comment('Texto original del requerimiento tal como llegó');

            $table->text('texto_interpretado')->nullable()
                ->comment('JSON: interpretación estructurada del LLM');

            // ── Usuarios ──
            $table->unsignedInteger('user_id')->nullable()
                ->comment('FK al usuario que creó la precotización');

            $table->unsignedInteger('reviewer_id')->nullable()
                ->comment('FK al usuario que revisó/validó');

            // ── Totales ──
            $table->decimal('subtotal', 12, 2)->default(0)
                ->comment('Suma de totales de ítems sin IGV');

            $table->decimal('total_igv', 12, 2)->default(0)
                ->comment('Total IGV (18%)');

            $table->decimal('total', 12, 2)->default(0)
                ->comment('Total final con IGV');

            // ── Metadata ──
            $table->text('observaciones')->nullable();

            $table->string('ai_provider', 30)->nullable()
                ->comment('Proveedor de IA usado: claude, openai');

            $table->string('ai_model', 60)->nullable()
                ->comment('Modelo de IA usado');

            // ── FK a Quotation formal (cuando se convierte) ──
            $table->unsignedBigInteger('quotation_id')->nullable()
                ->comment('FK a quotations si ya se convirtió a cotización formal');

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('requerimiento_id');
            $table->index('empresa_id');
            $table->index('estado');
            $table->index('user_id');
            $table->index('quotation_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_prequotes');
    }
}

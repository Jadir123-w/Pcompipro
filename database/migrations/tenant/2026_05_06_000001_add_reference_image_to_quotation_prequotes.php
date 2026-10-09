<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * COM-02 S2 — Procesamiento de imágenes (sec 22 doc funcional).
 *
 * Agrega `reference_image_path` a `quotation_prequotes` para que el cliente
 * pueda adjuntar UNA imagen general del requerimiento (antes de splitearse
 * en items). El AIEngine la pasa al LLM multimodal junto con el texto del
 * requerimiento, mejorando la inferencia de:
 *
 *   - Material y medidas aproximadas
 *   - Técnica de impresión visible
 *   - Variantes específicas (cuello redondo vs camisa, eco vs estándar)
 *
 * La columna `product_reference_image` por ítem ya existe — esta migración
 * solo agrega la versión a nivel prequote.
 *
 * Migración aditiva e idempotente.
 */
class AddReferenceImageToQuotationPrequotes extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequotes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequotes', 'reference_image_path')) {
                $table->string('reference_image_path', 500)->nullable()
                    ->after('texto_interpretado')
                    ->comment('Path relativo al storage local de la imagen general del requerimiento. La consume el AIEngine multimodal.');
            }

            if (!Schema::connection('tenant')->hasColumn('quotation_prequotes', 'reference_image_mime')) {
                $table->string('reference_image_mime', 100)->nullable()
                    ->after('reference_image_path')
                    ->comment('MIME real detectado al subir (image/jpeg, image/png, image/webp). Validado server-side.');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequotes', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_prequotes', 'reference_image_mime')) {
                $table->dropColumn('reference_image_mime');
            }
            if (Schema::connection('tenant')->hasColumn('quotation_prequotes', 'reference_image_path')) {
                $table->dropColumn('reference_image_path');
            }
        });
    }
}

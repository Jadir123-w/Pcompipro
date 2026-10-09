<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla marketing_chatbot_faqs — Preguntas frecuentes editables del chatbot MKT-01.
 *
 * Patrón idéntico a chatbot_catalogs: CRUD desde el panel de administración
 * del ERP (tab "FAQs" en Settings del Chatbot). Las FAQs activas se inyectan
 * en el system prompt vía placeholder {{FAQS}}.
 *
 * NO confundir con las claves `faq_*` de marketing_chatbot_config: aquellas
 * eran un stub key/value descartado a favor de este CRUD dedicado.
 *
 * Columnas:
 *   - question:    pregunta canónica del cliente (texto corto)
 *   - answer:      respuesta canónica que debe dar el bot (puede tener saltos de línea)
 *   - keywords:    palabras clave separadas por coma para que el LLM la encuentre
 *                  ("tiempo, demora, plazo, entrega" → matchea muchas variantes)
 *   - category:    agrupador opcional ("Tiempos", "Pagos", "Personalización", "Envíos"…)
 *                  para que la UI las ordene/filtre
 *   - active:      toggle para silenciar una FAQ sin borrarla
 *   - sort_order:  orden de presentación al LLM (las primeras pesan más)
 */
class CreateMarketingChatbotFaqsTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('marketing_chatbot_faqs')) {
            return;
        }

        Schema::connection('tenant')->create('marketing_chatbot_faqs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('question', 500)
                ->comment('Pregunta canónica (ej: "¿Cuánto demora la producción?")');

            $table->text('answer')
                ->comment('Respuesta canónica que debe dar el bot. Admite saltos de línea.');

            $table->string('keywords', 500)->nullable()
                ->comment('Palabras clave coma-separadas para matching: "tiempo, demora, plazo, entrega"');

            $table->string('category', 80)->nullable()
                ->comment('Agrupador opcional: Tiempos, Pagos, Personalización, Envíos, Mínimos, Muestras, etc.');

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0)
                ->comment('Orden de presentación al LLM. Menor = más prioritario.');

            $table->timestamps();

            $table->index('active');
            $table->index('category');
            $table->index(['active', 'sort_order'], 'idx_active_sort');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_chatbot_faqs');
    }
}

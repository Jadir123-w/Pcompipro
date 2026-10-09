<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla marketing_brand_assets — Brand Asset Library (BAL) compartida.
 *
 * Repositorio centralizado de identidad visual y de marca usado por los 3
 * agentes del módulo Marketing:
 *
 *   - MKT-01 (Chatbot): inyecta brand_guideline en system prompt; usa logo en widget
 *   - COM-02 (Cotizador): aplica logo+colores al PDF de la cotización
 *   - MKT-03 (Social Media): pasa product_photos como referencias a gpt-image-2
 *
 * Tipos soportados:
 *   - logo            → archivo de imagen (storage_path)
 *   - color           → hex_color
 *   - product_photo   → archivo de imagen (storage_path)
 *   - brand_guideline → texto largo (text_value)
 *   - icon            → archivo de imagen (storage_path)
 *   - font            → texto (nombre google font) o archivo .ttf/.woff2
 *
 * Filtrado por agente vía flags use_in_mkt01/com02/mkt03.
 * Filtrado fino vía tags JSON (ej: ["primary","header","mkt03-hero"]).
 */
class CreateMarketingBrandAssetsTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('marketing_brand_assets', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Categorización ──
            $table->enum('type', [
                'logo',
                'color',
                'product_photo',
                'brand_guideline',
                'icon',
                'font',
            ])->comment('Tipo de asset');

            $table->string('name', 150)->comment('Nombre humano del asset');
            $table->text('description')->nullable()->comment('Contexto adicional para el equipo');

            // ── Asset file (logo, product_photo, icon, font binario) ──
            $table->string('storage_disk', 20)->nullable()
                ->comment('Disk de Storage (default: public)');
            $table->string('storage_path', 500)->nullable()
                ->comment('Ruta relativa al disk');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable()
                ->comment('Bytes');
            $table->unsignedInteger('width')->nullable()
                ->comment('Ancho en px (solo imágenes)');
            $table->unsignedInteger('height')->nullable()
                ->comment('Alto en px (solo imágenes)');

            // ── Asset color ──
            $table->string('hex_color', 9)->nullable()
                ->comment('Formato #RRGGBB o #RRGGBBAA');

            // ── Asset texto (brand_guideline, font name) ──
            $table->text('text_value')->nullable();

            // ── Metadata para los agentes ──
            $table->json('tags')->nullable()
                ->comment('Tags para filtrado fino, ej: ["primary","mkt03-hero"]');
            $table->text('usage_context')->nullable()
                ->comment('Hint para la IA: cuándo y cómo usar este asset');

            // ── Flags por agente (filtrado rápido sin tocar tags) ──
            $table->boolean('use_in_mkt01')->default(false);
            $table->boolean('use_in_com02')->default(false);
            $table->boolean('use_in_mkt03')->default(false);

            // ── Estado ──
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);

            // ── Tracking ──
            $table->unsignedBigInteger('created_by')->nullable()
                ->comment('FK a users.id (puede ser null si seed)');

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index(['type', 'active'], 'idx_type_active');
            $table->index(['active', 'sort_order'], 'idx_active_sort');
            $table->index('use_in_mkt01');
            $table->index('use_in_com02');
            $table->index('use_in_mkt03');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_brand_assets');
    }
}

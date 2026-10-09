<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAiFieldsToMarketingChatbotLeadsTable extends Migration
{
    public function up()
    {
        Schema::table('marketing_chatbot_leads', function (Blueprint $table) {

            // ── Inteligencia conversacional ──
            $table->string('intent_detected', 60)->nullable()->after('meta')
                ->comment('Intención final: saludo, consulta_general, solicitar_catalogo, registrar_para_cotizacion, etc.');

            $table->text('conversation_summary')->nullable()->after('intent_detected')
                ->comment('Resumen de 3-6 líneas generado por el LLM al cierre');

            $table->json('structured_data')->nullable()->after('conversation_summary')
                ->comment('Datos extraídos: items_solicitados[], fecha, ciudad, rubro, personalización, etc.');

            $table->json('tags')->nullable()->after('structured_data')
                ->comment('Etiquetas/categorías detectadas: ["Textil","Impresos"]');

            // ── Canal y asignación ──
            $table->string('channel', 20)->default('web')->after('tags')
                ->comment('Canal de origen: web, whatsapp, correo');

            $table->unsignedBigInteger('assigned_to')->nullable()->after('channel')
                ->comment('ID de la ejecutiva comercial asignada');

            // ── Estado de madurez ──
            $table->boolean('ready_to_convert')->default(false)->after('assigned_to')
                ->comment('listo_para_convertir: true si tiene datos mínimos completos');

            $table->enum('urgency_level', ['low', 'medium', 'high'])->default('low')->after('ready_to_convert')
                ->comment('Nivel de urgencia detectado');

            // ── Referencia a sesión ──
            $table->string('session_id', 64)->nullable()->after('urgency_level')
                ->comment('UUID de la sesión de chat que originó este lead');

            // ── Índices ──
            $table->index('intent_detected');
            $table->index('channel');
            $table->index('ready_to_convert');
            $table->index('urgency_level');
            $table->index('session_id');
            $table->index('assigned_to');
        });
    }

    public function down()
    {
        Schema::table('marketing_chatbot_leads', function (Blueprint $table) {
            $table->dropIndex(['intent_detected']);
            $table->dropIndex(['channel']);
            $table->dropIndex(['ready_to_convert']);
            $table->dropIndex(['urgency_level']);
            $table->dropIndex(['session_id']);
            $table->dropIndex(['assigned_to']);

            $table->dropColumn([
                'intent_detected',
                'conversation_summary',
                'structured_data',
                'tags',
                'channel',
                'assigned_to',
                'ready_to_convert',
                'urgency_level',
                'session_id',
            ]);
        });
    }
}

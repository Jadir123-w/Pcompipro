<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-01 Email Triage — Detección automática de urgencia (ET2 mejorado).
 *
 * Agrega la columna `urgency_level` a `marketing_emails_received` para que el
 * clasificador pueda marcar correos con urgencia alta automáticamente. Esto
 * permite que la UI de pendientes destaque los correos que mencionan
 * "urgente", "lo necesito ya", "asap" etc., para priorizar su atención.
 *
 * Niveles:
 *   - low      : explícito que NO es urgente ("cuando puedan", "sin apuro").
 *   - normal   : default. Sin señales de urgencia ni anti-urgencia.
 *   - high     : palabras como "urgente", "asap", "lo antes posible",
 *                "necesito hoy", o fecha de entrega < 5 días.
 *   - critical : algo MUY grave o tiempo crítico (poco común; <24h).
 *
 * Idempotente: chequea existencia antes de crear la columna.
 */
class AddUrgencyLevelToMarketingEmailsReceived extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_emails_received')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('marketing_emails_received', 'urgency_level')) {
            Schema::connection('tenant')->table('marketing_emails_received', function (Blueprint $table) {
                $table->string('urgency_level', 20)->default('normal')->after('classification_reason')
                    ->comment('low | normal | high | critical — detectado por el LLM en el clasificador (ET2)');

                $table->index('urgency_level', 'idx_urgency_level');
            });
        }
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_emails_received')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('marketing_emails_received', 'urgency_level')) {
            Schema::connection('tenant')->table('marketing_emails_received', function (Blueprint $table) {
                $table->dropIndex('idx_urgency_level');
                $table->dropColumn('urgency_level');
            });
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProviderMaxTokensLimit extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'max_tokens_limit')) {
            Schema::connection('tenant')->table('marketing_ai_providers', function (Blueprint $table) {
                $table->unsignedInteger('max_tokens_limit')->nullable()
                    ->comment('Límite máximo de tokens de salida para el adaptador compatible');
            });
        }

        DB::connection('tenant')->table('marketing_ai_providers')
            ->where('provider_key', 'groq')
            ->whereNull('max_tokens_limit')
            ->update(['max_tokens_limit' => 4096]);
    }

    public function down()
    {
        if (
            Schema::connection('tenant')->hasTable('marketing_ai_providers')
            && Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'max_tokens_limit')
        ) {
            Schema::connection('tenant')->table('marketing_ai_providers', function (Blueprint $table) {
                $table->dropColumn('max_tokens_limit');
            });
        }
    }
}

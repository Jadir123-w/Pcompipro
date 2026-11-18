<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDynamicProviderSupport extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        Schema::connection('tenant')->table('marketing_ai_providers', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'adapter_type')) {
                $table->string('adapter_type', 40)->nullable()
                    ->comment('Conector runtime; null conserva el proveedor legacy');
            }
            if (!Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'api_key_encrypted')) {
                $table->text('api_key_encrypted')->nullable()
                    ->comment('Credencial cifrada con Laravel Crypt para proveedores registrados desde UI');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        Schema::connection('tenant')->table('marketing_ai_providers', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'api_key_encrypted')) {
                $table->dropColumn('api_key_encrypted');
            }
            if (Schema::connection('tenant')->hasColumn('marketing_ai_providers', 'adapter_type')) {
                $table->dropColumn('adapter_type');
            }
        });
    }
}

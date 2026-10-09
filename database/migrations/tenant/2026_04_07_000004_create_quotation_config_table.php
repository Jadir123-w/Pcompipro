<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuotationConfigTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_config', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('config_key', 100)->unique()
                ->comment('Clave de configuración');

            $table->text('config_value')->nullable()
                ->comment('Valor de la configuración');

            $table->string('config_type', 20)->default('string')
                ->comment('Tipo: string, integer, decimal, boolean, json');

            $table->text('description')->nullable()
                ->comment('Descripción legible de la configuración');

            $table->timestamps();
        });

        // ── Insertar configuración por defecto ──
        $defaults = [
            ['config_key' => 'default_margin_percentage', 'config_value' => '30', 'config_type' => 'decimal', 'description' => 'Margen por defecto cuando no hay regla específica (%)'],
            ['config_key' => 'igv_percentage', 'config_value' => '18', 'config_type' => 'decimal', 'description' => 'Porcentaje de IGV aplicable'],
            ['config_key' => 'default_colores', 'config_value' => '1', 'config_type' => 'integer', 'description' => 'Cantidad de colores por defecto si no se indica'],
            ['config_key' => 'default_lados', 'config_value' => '1', 'config_type' => 'integer', 'description' => 'Cantidad de lados por defecto si no se indica'],
            ['config_key' => 'default_tecnica_impresion', 'config_value' => 'serigrafía', 'config_type' => 'string', 'description' => 'Técnica de impresión por defecto'],
            ['config_key' => 'default_empaque', 'config_value' => 'bolsa individual', 'config_type' => 'string', 'description' => 'Empaque por defecto'],
            ['config_key' => 'default_tiempo_dias', 'config_value' => '15', 'config_type' => 'integer', 'description' => 'Tiempo estimado de producción por defecto (días)'],
            ['config_key' => 'currency_type_id', 'config_value' => 'PEN', 'config_type' => 'string', 'description' => 'Moneda por defecto para cotizaciones'],
            ['config_key' => 'auto_prequote_enabled', 'config_value' => '0', 'config_type' => 'boolean', 'description' => 'Habilitar generación autónoma de precotizaciones'],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $d) {
            $d['created_at'] = $now;
            $d['updated_at'] = $now;
            \Illuminate\Support\Facades\DB::table('quotation_config')->insert($d);
        }
    }

    public function down()
    {
        Schema::dropIfExists('quotation_config');
    }
}

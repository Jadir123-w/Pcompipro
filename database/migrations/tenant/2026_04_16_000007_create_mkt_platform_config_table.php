<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktPlatformConfigTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_platform_config', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('red_social', 30)->unique()
                ->comment('facebook, instagram, linkedin, tiktok');

            $table->string('nombre_publico', 60)
                ->comment('Nombre visible: Facebook, Instagram, LinkedIn, TikTok');

            // ── Configuración editorial ──
            $table->text('funcion_editorial_principal')->nullable()
                ->comment('Función principal de la red (cercanía, visibilidad, reputación, etc.)');

            $table->text('que_priorizar')->nullable()
                ->comment('Qué debe priorizar el agente en esta red');

            $table->text('que_evitar')->nullable()
                ->comment('Qué debe evitar en esta red');

            $table->text('tono_recomendado')->nullable();

            // ── Límites técnicos ──
            $table->integer('max_copy_length')->nullable()
                ->comment('Longitud máxima del copy');

            $table->integer('max_hashtags')->nullable()
                ->comment('Cantidad máxima recomendada de hashtags');

            $table->json('formatos_soportados')->nullable()
                ->comment('Formatos: [post, reel, story, video, carousel]');

            // ── Estilo visual ──
            $table->string('aspect_ratio_default', 20)->nullable()
                ->comment('1:1, 4:5, 9:16, 16:9');

            $table->json('aspect_ratios_soportados')->nullable();

            // ── Relación con otras redes ──
            $table->boolean('comparte_grilla_base')->default(false)
                ->comment('Si comparte grilla base con otra red (ej: FB+IG)');

            $table->string('red_pareja', 30)->nullable()
                ->comment('Red con la que comparte grilla base');

            // ── Estado ──
            $table->boolean('active')->default(true);

            $table->timestamps();
        });

        // Insertar configuración por defecto para las 4 redes
        $now = date('Y-m-d H:i:s');
        $defaults = [
            [
                'red_social' => 'facebook',
                'nombre_publico' => 'Facebook',
                'funcion_editorial_principal' => 'Cercanía, visibilidad, valor comercial, difusión, recordación de marca',
                'que_priorizar' => 'Claridad, piezas visuales entendibles, copy accesible, tono útil y cercano',
                'que_evitar' => 'Exceso de tecnicismo, tono demasiado institucional, copy muy frío o muy extenso',
                'tono_recomendado' => 'humano, cercano, útil',
                'max_copy_length' => 2200,
                'max_hashtags' => 5,
                'formatos_soportados' => '["post","video","carousel","story","reel"]',
                'aspect_ratio_default' => '1:1',
                'aspect_ratios_soportados' => '["1:1","4:5","16:9","9:16"]',
                'comparte_grilla_base' => 1,
                'red_pareja' => 'instagram',
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'red_social' => 'instagram',
                'nombre_publico' => 'Instagram',
                'funcion_editorial_principal' => 'Impacto visual, recordación, atractivo estético, posicionamiento visual de marca',
                'que_priorizar' => 'Buena presencia visual, copy más corto, gancho visual, valor estético de la pieza',
                'que_evitar' => 'Texto muy pesado, pieza muy cargada, mensaje más propio de LinkedIn',
                'tono_recomendado' => 'visual, estético, breve',
                'max_copy_length' => 2200,
                'max_hashtags' => 15,
                'formatos_soportados' => '["post","reel","story","carousel"]',
                'aspect_ratio_default' => '4:5',
                'aspect_ratios_soportados' => '["1:1","4:5","9:16"]',
                'comparte_grilla_base' => 1,
                'red_pareja' => 'facebook',
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'red_social' => 'linkedin',
                'nombre_publico' => 'LinkedIn',
                'funcion_editorial_principal' => 'Reputación, posicionamiento institucional, valor empresarial, credibilidad B2B',
                'que_priorizar' => 'Enfoque profesional, claridad estratégica, valor corporativo, lenguaje ejecutivo',
                'que_evitar' => 'Tono demasiado informal, contenido promocional, visuales tipo TikTok',
                'tono_recomendado' => 'profesional, ejecutivo, estratégico',
                'max_copy_length' => 3000,
                'max_hashtags' => 5,
                'formatos_soportados' => '["post","articulo","video","carousel"]',
                'aspect_ratio_default' => '1:1',
                'aspect_ratios_soportados' => '["1:1","1.91:1","9:16"]',
                'comparte_grilla_base' => 0,
                'red_pareja' => null,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'red_social' => 'tiktok',
                'nombre_publico' => 'TikTok',
                'funcion_editorial_principal' => 'Atención, dinamismo, creatividad, formato corto, impacto inicial',
                'que_priorizar' => 'Gancho, ritmo, idea de video, lógica audiovisual, sonido o música, formato ágil',
                'que_evitar' => 'Contenido estático disfrazado, copy pesado, tono corporativo rígido',
                'tono_recomendado' => 'dinámico, creativo, ágil',
                'max_copy_length' => 2200,
                'max_hashtags' => 10,
                'formatos_soportados' => '["video_corto","video"]',
                'aspect_ratio_default' => '9:16',
                'aspect_ratios_soportados' => '["9:16"]',
                'comparte_grilla_base' => 0,
                'red_pareja' => null,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($defaults as $row) {
            \Illuminate\Support\Facades\DB::table('mkt_platform_config')->insert($row);
        }
    }

    public function down()
    {
        Schema::dropIfExists('mkt_platform_config');
    }
}

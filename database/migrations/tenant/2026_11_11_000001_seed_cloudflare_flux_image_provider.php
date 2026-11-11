<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedCloudflareFluxImageProvider extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        DB::connection('tenant')->table('marketing_ai_providers')->updateOrInsert(
            ['provider_key' => 'cloudflare'],
            [
                'capability'              => 'image',
                'display_name'            => 'Cloudflare Flux 1 Schnell',
                'description'             => 'Generación de imágenes con Flux 1 Schnell en Cloudflare Workers AI.',
                'default_model'           => '@cf/black-forest-labs/flux-1-schnell',
                'supported_models'        => json_encode(['@cf/black-forest-labs/flux-1-schnell']),
                'supports_image_edit'     => 0,
                'supported_qualities'     => null,
                'supported_aspect_ratios' => json_encode(['1:1', '4:5', '16:9', '9:16', '2:3', '3:2']),
                'base_url'                => 'https://api.cloudflare.com/client/v4/accounts',
                'api_key_env_var'         => 'CLOUDFLARE_API_TOKEN',
                'max_tokens_default'      => null,
                'enabled'                 => 1,
                'sort_order'              => 26,
                'pricing_notes'           => 'Requiere CLOUDFLARE_API_TOKEN y CLOUDFLARE_ACCOUNT_ID.',
                'updated_at'              => $now,
                'created_at'              => $now,
            ]
        );
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        DB::connection('tenant')->table('marketing_ai_providers')
            ->where('provider_key', 'cloudflare')
            ->delete();
    }
}

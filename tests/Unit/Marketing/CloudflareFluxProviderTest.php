<?php

namespace Tests\Unit\Marketing;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Services\Image\CloudflareFluxProvider;
use Tests\TestCase;

class CloudflareFluxProviderTest extends TestCase
{
    public function test_it_generates_and_stores_a_flux_image()
    {
        $disk = 'cloudflare_flux_provider_test';
        Storage::fake($disk);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'result' => ['image' => base64_encode('fake-png-content')],
            ])),
        ]));
        $stack->push(Middleware::history($history));

        $provider = new CloudflareFluxProvider([
            'api_key'     => 'test-token',
            'account_id'  => 'test-account',
            'model'       => '@cf/black-forest-labs/flux-1-schnell',
            'base_url'    => 'https://api.cloudflare.com/client/v4/accounts',
            'storage_disk' => $disk,
            'storage_path' => 'cloudflare-test',
        ]);
        $clientProperty = new \ReflectionProperty($provider, 'client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($provider, new Client(['handler' => $stack]));

        $result = $provider->generateImage('A studio product photo', ['aspect_ratio' => '16:9']);

        $this->assertTrue($result['success']);
        $this->assertSame('@cf/black-forest-labs/flux-1-schnell', $result['model']);
        $this->assertSame('cloudflare', $result['provider']);
        $this->assertTrue(Storage::disk($disk)->exists($result['storage_path']));
        $this->assertSame(
            'https://api.cloudflare.com/client/v4/accounts/test-account/ai/run/@cf/black-forest-labs/flux-1-schnell',
            (string) $history[0]['request']->getUri()
        );
        $this->assertSame(
            ['prompt' => 'A studio product photo'],
            json_decode((string) $history[0]['request']->getBody(), true)
        );
    }

    public function test_account_id_is_required_for_cloudflare()
    {
        $provider = new CloudflareFluxProvider(['api_key' => 'test-token']);

        $this->assertFalse($provider->isConfigured());
        $this->assertSame('@cf/black-forest-labs/flux-1-schnell', $provider->getModelName());
        $this->assertSame(
            'Account ID no configurado (CLOUDFLARE_ACCOUNT_ID ausente)',
            $provider->generateImage('A studio product photo')['error']
        );
        $this->assertFalse($provider->supportsImageEdit());
    }
}

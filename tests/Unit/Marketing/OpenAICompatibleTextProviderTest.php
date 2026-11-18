<?php

namespace Tests\Unit\Marketing;

use Modules\Marketing\Services\AI\OpenAICompatibleTextProvider;
use Tests\TestCase;

class OpenAICompatibleTextProviderTest extends TestCase
{
    public function test_it_caps_requested_output_tokens_to_provider_limit()
    {
        $this->assertSame(4096, OpenAICompatibleTextProvider::resolveMaxTokens(16000, 4096));
    }

    public function test_it_preserves_requested_output_tokens_when_under_provider_limit()
    {
        $this->assertSame(2048, OpenAICompatibleTextProvider::resolveMaxTokens(2048, 4096));
    }

    public function test_it_does_not_cap_tokens_without_configured_provider_limit()
    {
        $this->assertSame(16000, OpenAICompatibleTextProvider::resolveMaxTokens(16000));
    }
}

<?php

namespace Vinkius\ConnectLaravel\Tests\Support;

use PHPUnit\Framework\TestCase;
use Vinkius\ConnectLaravel\Support\Redaction;

final class RedactionTest extends TestCase
{
    public function test_headers_redact_secrets(): void
    {
        $redacted = Redaction::headers([
            'Authorization' => 'Bearer vk_app_sk_secret',
            'X-Vinkius-App-Id' => 'vk_app_public',
            'Idempotency-Key' => 'idemp-123',
            'Accept' => 'application/json',
        ]);

        self::assertSame('[REDACTED]', $redacted['Authorization']);
        self::assertSame('[REDACTED]', $redacted['Idempotency-Key']);
        self::assertSame('vk_app_public', $redacted['X-Vinkius-App-Id']);
        self::assertSame('application/json', $redacted['Accept']);
    }

    public function test_url_masks_vk_live_token_segment(): void
    {
        $url = 'https://runtime.vinkius.com/vk_live_ABC123_xyz/mcp';
        self::assertSame('https://runtime.vinkius.com/[REDACTED]/mcp', Redaction::url($url));
    }

    public function test_body_redacts_secret_keys(): void
    {
        $redacted = Redaction::body([
            'credentials' => ['GITHUB_TOKEN' => 'secret'],
            'api_key' => 'key123',
            'mcp_url' => 'https://x/vk_live_y/mcp',
            'safe' => 'value',
        ]);

        self::assertSame('[REDACTED]', $redacted['credentials']);
        self::assertSame('[REDACTED]', $redacted['api_key']);
        self::assertSame('[REDACTED]', $redacted['mcp_url']);
        self::assertSame('value', $redacted['safe']);
    }
}

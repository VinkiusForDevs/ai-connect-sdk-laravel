<?php

namespace Vinkius\ConnectLaravel\Tests;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;
use Vinkius\ConnectLaravel\Vinkius;

final class VinkiusTest extends TestCase
{
    public function test_rejects_app_key_used_as_app_id(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/vk_app_/');

        new Vinkius('vk_app_sk_abc', 'vk_app_sk_abc', null, new Client());
    }

    public function test_rejects_non_prefixed_app_id(): void
    {
        $this->expectException(ConfigurationException::class);

        new Vinkius('my-app', 'vk_app_sk_abc', null, new Client());
    }

    public function test_rejects_non_prefixed_api_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/vk_app_sk_/');

        new Vinkius('vk_app_abc', 'not-a-key', null, new Client());
    }

    public function test_user_rejects_vinkius_internal_prefix(): void
    {
        $vinkius = new Vinkius('vk_app_abc', 'vk_app_sk_abc', null, new Client());

        $this->expectException(ConfigurationException::class);
        $vinkius->user('vk_app_user_123');
    }

    public function test_user_rejects_invalid_external_id(): void
    {
        $vinkius = new Vinkius('vk_app_abc', 'vk_app_sk_abc', null, new Client());

        $this->expectException(ConfigurationException::class);
        $vinkius->user('bad id');
    }

    public function test_user_handle_is_lazy_and_returns_user_context(): void
    {
        $vinkius = new Vinkius('vk_app_abc', 'vk_app_sk_abc', null, new Client());

        $user = $vinkius->user('alice_123');

        self::assertSame('alice_123', $user->externalId());
        self::assertSame($vinkius, $user->vinkius());
    }

    public function test_capability_namespacing_is_overridable(): void
    {
        $vinkius = new Vinkius(
            'vk_app_abc',
            'vk_app_sk_abc',
            null,
            new Client(),
            ['namespace' => fn (string $c, string $n) => "{$c}_{$n}"],
        );

        self::assertSame('github_create_issue', $vinkius->call('github', 'create_issue'));
    }

    public function test_default_namespacing_uses_double_underscore(): void
    {
        $vinkius = new Vinkius('vk_app_abc', 'vk_app_sk_abc', null, new Client());

        self::assertSame('github__create_issue', $vinkius->call('github', 'create_issue'));
    }

    public function test_invalid_base_url_is_rejected(): void
    {
        $this->expectException(ConfigurationException::class);

        new Vinkius('vk_app_abc', 'vk_app_sk_abc', 'not a url', new Client());
    }
}

<?php

namespace Vinkius\ConnectLaravel\Tests;

use Orchestra\Testbench\TestCase;
use Vinkius\ConnectLaravel\HasVinkius;
use Vinkius\ConnectLaravel\UserContext;
use Vinkius\ConnectLaravel\VinkiusServiceProvider;

final class HasVinkiusTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [VinkiusServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('vinkius.app_id', 'vk_app_test');
        $app['config']->set('vinkius.app_key', 'vk_app_sk_test');
        $app['config']->set('vinkius.external_id', null);
    }

    protected function model(): object
    {
        return new class () {
            use HasVinkius;

            public function getKey(): string
            {
                return 'user-slug';
            }

            public function uuid(): string
            {
                return 'abc-123';
            }

            public function email(): string
            {
                return 'user@example.com';
            }
        };
    }

    public function test_external_id_defaults_to_model_key(): void
    {
        config()->set('vinkius.external_id', null);

        self::assertSame('user-slug', $this->model()->vinkiusExternalId());
    }

    public function test_external_id_from_configured_property(): void
    {
        config()->set('vinkius.external_id', 'uuid');

        self::assertSame('abc-123', $this->model()->vinkiusExternalId());
    }

    public function test_external_id_from_callable_resolver(): void
    {
        config()->set('vinkius.external_id', static fn (object $model): string => strtoupper($model->email()));

        self::assertSame('USER@EXAMPLE.COM', $this->model()->vinkiusExternalId());
    }

    public function test_vinkius_returns_user_context(): void
    {
        $user = $this->model()->vinkius();

        self::assertInstanceOf(UserContext::class, $user);
        self::assertSame('user-slug', $user->externalId());
    }
}

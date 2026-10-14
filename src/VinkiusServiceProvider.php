<?php

namespace Vinkius\ConnectLaravel;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;

class VinkiusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/vinkius.php', 'vinkius');

        $this->app->singleton(Vinkius::class, function () {
            $config = $this->app->make('config')->get('vinkius', []);

            if (empty($config['app_id']) || empty($config['app_key'])) {
                throw ConfigurationException::create(
                    'vinkius.app_id and vinkius.app_key must be set (env VINKIUS_APP_ID / VINKIUS_APP_KEY).'
                );
            }

            $hooks = $this->observabilityHooks($config);

            $guzzle = new GuzzleClient([
                'timeout' => intdiv((int) ($config['timeout'] ?? 30_000), 1000),
                'connect_timeout' => 30,
            ]);

            $namespace = $config['namespace'];

            return new Vinkius(
                appId: (string) $config['app_id'],
                apiKey: (string) $config['app_key'],
                baseUrl: ! empty($config['base_url']) ? (string) $config['base_url'] : null,
                guzzle: $guzzle,
                options: [
                    'userAgent' => (string) ($config['user_agent'] ?? 'vinkius-connect-laravel'),
                    'maxRetries' => (int) ($config['max_retries'] ?? 2),
                    'timeoutMs' => (int) ($config['timeout'] ?? 30_000),
                    'namespace' => is_callable($namespace) ? Closure::fromCallable($namespace) : null,
                    'onRequest' => $hooks['onRequest'],
                    'onResponse' => $hooks['onResponse'],
                ],
            );
        });

        $this->app->alias(Vinkius::class, 'vinkius');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/vinkius.php' => $this->app->configPath('vinkius.php'),
        ], 'vinkius-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_vinkius_state_table.php.stub' => $this->app->databasePath()
                . '/migrations/create_vinkius_state_table.php',
        ], 'vinkius-migrations');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{onRequest: ?Closure, onResponse: ?Closure}
     */
    protected function observabilityHooks(array $config): array
    {
        $log = $this->app->make('log');
        $observability = $config['observability'] ?? [];
        $channel = $observability['log_channel'] ?? null;

        $onRequest = ($observability['log_on_request'] ?? false)
            ? static function (array $info) use ($log, $channel): void {
                ($channel ? $log->channel($channel) : $log)->debug('vinkius.request', $info);
            }
            : null;

        $onResponse = ($observability['log_on_response'] ?? false)
            ? static function (array $info) use ($log, $channel): void {
                ($channel ? $log->channel($channel) : $log)->debug('vinkius.response', $info);
            }
            : null;

        return ['onRequest' => $onRequest, 'onResponse' => $onResponse];
    }
}

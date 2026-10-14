<?php

namespace Vinkius\ConnectLaravel;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;
use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Http\RuntimeClient;
use Vinkius\ConnectLaravel\Resources\AppUsersResource;
use Vinkius\ConnectLaravel\Resources\CatalogResource;
use Vinkius\ConnectLaravel\Support\ResolverCache;
use Vinkius\ConnectLaravel\Support\RetryPolicy;
use Vinkius\ConnectLaravel\Support\Validation;

class Vinkius
{
    protected Client $http;

    protected string $appId;

    protected ResolverCache $cache;

    protected Closure $namespace;

    protected ?Closure $runtimeFactory = null;

    public CatalogResource $catalog;

    public AppUsersResource $users;

    public const DEFAULT_BASE_URL = 'https://api.vinkius.com';

    public function __construct(
        string $appId,
        string $apiKey,
        ?string $baseUrl = null,
        ?GuzzleClient $guzzle = null,
        array $options = [],
    ) {
        $this->validateCredentials($appId, $apiKey);

        $baseUrl = self::normalizeBaseUrl($baseUrl ?? self::DEFAULT_BASE_URL);

        $userAgent = (string) ($options['userAgent'] ?? 'vinkius-connect-laravel');
        $guzzle ??= new GuzzleClient();

        $this->appId = $appId;
        $this->cache = new ResolverCache();
        $this->namespace = $options['namespace'] ?? static fn (string $connector, string $name): string => "{$connector}__{$name}";

        $this->http = new Client(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            appId: $appId,
            userAgent: $userAgent,
            retry: new RetryPolicy(maxRetries: (int) ($options['maxRetries'] ?? 2)),
            guzzle: $guzzle,
            timeoutMs: (int) ($options['timeoutMs'] ?? 30_000),
            onRequest: $options['onRequest'] ?? null,
            onResponse: $options['onResponse'] ?? null,
        );

        $this->catalog = new CatalogResource($this->http);
        $this->users = new AppUsersResource($this->http, $appId);
    }

    public function user(string $externalId): UserContext
    {
        Validation::assertExternalId($externalId);

        if (str_starts_with($externalId, 'vk_app_user_')) {
            throw ConfigurationException::create(
                'externalId must be YOUR user id (e.g. "alice_123"), not a Vinkius internal id. '
                . 'The SDK resolves identifiers automatically from the external_id you provide.'
            );
        }

        return new UserContext($externalId, $this);
    }

    public function runtime(string $mcpUrl): RuntimeClient
    {
        if ($this->runtimeFactory === null) {
            $this->runtimeFactory = function (string $mcpUrl): RuntimeClient {
                return new RuntimeClient(
                    mcpUrl: $mcpUrl,
                    guzzle: $this->http->guzzle(),
                    retry: $this->http->retryPolicy(),
                    userAgent: $this->http->userAgent(),
                    timeoutMs: $this->http->timeoutMs(),
                    onRequest: $this->http->onRequest(),
                    onResponse: $this->http->onResponse(),
                );
            };
        }

        return ($this->runtimeFactory)($mcpUrl);
    }

    public function cache(): ResolverCache
    {
        return $this->cache;
    }

    public function call(string $connector, string $name): string
    {
        return ($this->namespace)($connector, $name);
    }

    public function http(): Client
    {
        return $this->http;
    }

    public function appId(): string
    {
        return $this->appId;
    }

    protected function validateCredentials(string $appId, string $apiKey): void
    {
        if (str_starts_with($appId, 'vk_app_sk_') || ! str_starts_with($appId, 'vk_app_')) {
            throw ConfigurationException::create('`appId` must be a public application id starting with "vk_app_" (not the secret key).');
        }

        if (! str_starts_with($apiKey, 'vk_app_sk_')) {
            throw ConfigurationException::create('`apiKey` must be a secret application key starting with "vk_app_sk_".');
        }
    }

    protected static function normalizeBaseUrl(string $url): string
    {
        $normalized = rtrim($url, '/');

        if (filter_var($normalized, FILTER_VALIDATE_URL) === false) {
            throw ConfigurationException::create("Invalid baseUrl: {$url}");
        }

        return $normalized;
    }
}

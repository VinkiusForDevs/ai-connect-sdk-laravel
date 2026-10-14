<?php

namespace Vinkius\ConnectLaravel\Http;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;
use Vinkius\ConnectLaravel\Exceptions\VinkiusException;
use Vinkius\ConnectLaravel\Support\RetryPolicy;

class Client
{
    protected Transport $transport;

    protected GuzzleClient $guzzle;

    protected RetryPolicy $retry;

    protected ?Closure $onRequest;

    protected ?Closure $onResponse;

    protected int $timeoutMs;

    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $appId,
        protected string $userAgent,
        ?RetryPolicy $retry,
        GuzzleClient $guzzle,
        int $timeoutMs = 30_000,
        ?Closure $onRequest = null,
        ?Closure $onResponse = null,
    ) {
        $this->guzzle = $guzzle;
        $this->retry = $retry ?? RetryPolicy::default();
        $this->timeoutMs = $timeoutMs;
        $this->onRequest = $onRequest;
        $this->onResponse = $onResponse;

        $this->transport = new Transport(
            $guzzle,
            $this->retry,
            $onRequest,
            $onResponse,
            $timeoutMs,
        );
    }

    public function guzzle(): GuzzleClient
    {
        return $this->guzzle;
    }

    public function retryPolicy(): RetryPolicy
    {
        return $this->retry;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function timeoutMs(): int
    {
        return $this->timeoutMs;
    }

    public function onRequest(): ?Closure
    {
        return $this->onRequest;
    }

    public function onResponse(): ?Closure
    {
        return $this->onResponse;
    }

    public function get(string $path, array $query = [], array $options = []): mixed
    {
        return $this->request('GET', $path, $query, null, $options);
    }

    public function post(string $path, mixed $body = null, array $options = []): mixed
    {
        return $this->request('POST', $path, [], $body, $options);
    }

    public function put(string $path, mixed $body = null, array $options = []): mixed
    {
        return $this->request('PUT', $path, [], $body, $options);
    }

    public function patch(string $path, mixed $body = null, array $options = []): mixed
    {
        return $this->request('PATCH', $path, [], $body, $options);
    }

    public function delete(string $path, array $options = []): mixed
    {
        return $this->request('DELETE', $path, [], null, $options);
    }

    protected function request(string $method, string $path, array $query, mixed $body, array $options): mixed
    {
        $this->assertIdempotencyKey($options['idempotencyKey'] ?? null);

        $bodyString = $body === null
            ? null
            : (is_array($body) && $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR));

        $result = $this->transport->send([
            'url' => $this->buildUrl($path, $query),
            'method' => $method,
            'headers' => $this->buildHeaders($options, $body),
            'body' => $bodyString,
            'retryable' => $this->resolveRetryable($method, $options),
            'label' => 'Request',
            'timeoutMs' => $options['timeoutMs'] ?? null,
        ]);

        if ($result['status'] >= 200 && $result['status'] < 300) {
            return $result['body'];
        }

        throw VinkiusException::forStatus(
            $result['status'],
            $result['body'],
            $result['requestId'],
            RetryPolicy::parseRetryAfter($result['retryAfter']),
        );
    }

    protected function resolveRetryable(string $method, array $options): bool
    {
        $idempotent = $options['idempotent'] ?? null;
        if ($idempotent !== null) {
            return (bool) $idempotent;
        }

        if (in_array($method, ['GET', 'PUT', 'DELETE'], true)) {
            return true;
        }

        return ($options['idempotencyKey'] ?? null) !== null;
    }

    private function assertIdempotencyKey(?string $key): void
    {
        if ($key !== null && trim($key) === '') {
            throw ConfigurationException::create('Idempotency-Key must be a non-empty, non-whitespace string.');
        }
    }

    private function buildUrl(string $path, array $query): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $params = array_filter($query, static fn ($value) => $value !== null && $value !== false);
        $params = array_map(static fn ($value) => (string) $value, $params);

        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    private function buildHeaders(array $options, mixed $body): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Vinkius-App-Id' => $this->appId,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        if (! empty($options['idempotencyKey'])) {
            $headers['Idempotency-Key'] = $options['idempotencyKey'];
        }

        return array_merge($headers, $options['headers'] ?? []);
    }
}

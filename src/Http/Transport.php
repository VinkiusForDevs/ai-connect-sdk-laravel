<?php

namespace Vinkius\ConnectLaravel\Http;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Vinkius\ConnectLaravel\Exceptions\ConnectionException;
use Vinkius\ConnectLaravel\Exceptions\VinkiusException;
use Vinkius\ConnectLaravel\Support\Redaction;
use Vinkius\ConnectLaravel\Support\RetryPolicy;

final class Transport
{
    public function __construct(
        protected GuzzleClient $guzzle,
        protected RetryPolicy $retry,
        protected ?Closure $onRequest = null,
        protected ?Closure $onResponse = null,
        protected int $defaultTimeoutMs = 30_000,
    ) {}

    /**
     * @param  array{url: string, method: string, headers?: array<string,string>, body?: string|null, retryable?: bool, label?: string, timeoutMs?: int} $request
     * @return array{status: int, body: mixed, requestId: ?string, retryAfter: ?string}
     */
    public function send(array $request): array
    {
        $method = strtoupper((string) $request['method']);
        $headers = $request['headers'] ?? [];
        $body = $request['body'] ?? null;
        $label = $request['label'] ?? 'Request';
        $retryable = (bool) ($request['retryable'] ?? false);
        $maxAttempts = $retryable ? $this->retry->maxRetries + 1 : 1;

        $attempt = 0;
        while (true) {
            if ($this->onRequest !== null) {
                ($this->onRequest)([
                    'method' => $method,
                    'url' => Redaction::url($request['url']),
                    'headers' => Redaction::headers($headers),
                    'attempt' => $attempt,
                ]);
            }

            try {
                $result = $this->perform($method, $request['url'], $headers, $body, $request['timeoutMs'] ?? null, $label);
            } catch (ConnectionException $e) {
                if ($retryable && $attempt < $maxAttempts - 1) {
                    $this->retry->sleep($this->retry->backoffDelay($attempt));
                    $attempt++;
                    continue;
                }
                throw $e;
            }

            if ($this->onResponse !== null) {
                $responseInfo = [
                    'status' => $result['status'],
                    'url' => Redaction::url($request['url']),
                    'body' => Redaction::body($result['body']),
                    'attempt' => $attempt,
                ];
                if ($result['requestId'] !== null) {
                    $responseInfo['requestId'] = $result['requestId'];
                }
                ($this->onResponse)($responseInfo);
            }

            if ($result['status'] >= 200 && $result['status'] < 300) {
                return $result;
            }

            if ($retryable && $this->retry->isRetryableStatus($result['status']) && $attempt < $maxAttempts - 1) {
                $this->retry->sleep($this->retry->backoffDelay($attempt, RetryPolicy::parseRetryAfter($result['retryAfter'])));
                $attempt++;
                continue;
            }

            return $result;
        }
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: mixed, requestId: ?string, retryAfter: ?string}
     */
    private function perform(string $method, string $url, array $headers, ?string $body, ?int $timeoutMs, string $label): array
    {
        $options = [
            'http_errors' => false,
            'timeout' => max(0.25, ($timeoutMs ?? $this->defaultTimeoutMs) / 1000),
            'connect_timeout' => 30,
            'headers' => $headers,
        ];
        if ($method !== 'GET' && $body !== null) {
            $options['body'] = $body;
        }

        try {
            $response = $this->guzzle->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new ConnectionException("{$label} failed", 0, null, $e);
        } catch (TransferException $e) {
            if (! $e->hasResponse()) {
                $message = $e->getMessage();
                $isTimeout = str_contains($message, 'timed out') || str_contains($message, 'Timeout');
                throw new ConnectionException($isTimeout ? "{$label} timed out" : $message, 0, null, $e);
            }
            $response = $e->getResponse();
        }

        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);

        $requestId = null;
        foreach (['x-request-id', 'x-vinkius-request-id'] as $name) {
            if ($response->hasHeader($name)) {
                $requestId = $response->getHeaderLine($name);
                break;
            }
        }

        return [
            'status' => $response->getStatusCode(),
            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : ($raw === '' ? null : $raw),
            'requestId' => $requestId,
            'retryAfter' => $response->hasHeader('retry-after') ? $response->getHeaderLine('retry-after') : null,
        ];
    }

    public static function mapHttpError(int $status, mixed $body, ?string $requestId = null, ?int $retryAfterMs = null): VinkiusException
    {
        return VinkiusException::forStatus($status, $body, $requestId, $retryAfterMs);
    }
}

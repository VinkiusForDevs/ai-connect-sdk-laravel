<?php

namespace Vinkius\ConnectLaravel\Http;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use Vinkius\ConnectLaravel\Exceptions\AuthenticationException;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;
use Vinkius\ConnectLaravel\Exceptions\ConnectionException;
use Vinkius\ConnectLaravel\Exceptions\NotFoundException;
use Vinkius\ConnectLaravel\Exceptions\OverageException;
use Vinkius\ConnectLaravel\Exceptions\ProtocolException;
use Vinkius\ConnectLaravel\Exceptions\QuotaExceededException;
use Vinkius\ConnectLaravel\Exceptions\RateLimitException;
use Vinkius\ConnectLaravel\Exceptions\VinkiusException;
use Vinkius\ConnectLaravel\Support\RetryPolicy;

class RuntimeClient
{
    private int $nextId = 1;

    private string $userAgent;

    protected Transport $transport;

    public function __construct(
        protected string $mcpUrl,
        GuzzleClient $guzzle,
        ?RetryPolicy $retry = null,
        string $userAgent = 'vinkius-connect-laravel',
        int $timeoutMs = 30_000,
        ?Closure $onRequest = null,
        ?Closure $onResponse = null,
    ) {
        $this->transport = new Transport($guzzle, $retry ?? RetryPolicy::default(), $onRequest, $onResponse, $timeoutMs);
        $this->userAgent = $userAgent;
    }

    /**
     * List the tools this connection exposes. Free (un-metered) at the runtime.
     *
     * @return list<array{name: string, title: ?string, description: ?string, input_schema: array<string,mixed>}>
     */
    public function listTools(?int $timeoutMs = null): array
    {
        $result = $this->rpc('tools/list', [], $timeoutMs, true);
        $tools = $result['tools'] ?? null;

        if (! is_array($tools)) {
            throw new ProtocolException('Runtime tools/list returned no tools array.', null, $result);
        }

        return array_map(fn ($tool) => $this->normalizeToolDefinition($tool), $tools);
    }

    /**
     * Execute a tool. Metered against the connection's token.
     *
     * @return array{content: list<array{type:string,text:string}>, isError: bool, structuredContent?: mixed}
     */
    public function callTool(string $name, array $args, ?string $idempotencyKey = null, ?int $timeoutMs = null): array
    {
        if ($idempotencyKey !== null && trim($idempotencyKey) === '') {
            throw ConfigurationException::create('Idempotency-Key must be a non-empty, non-whitespace string.');
        }

        $result = $this->rpc(
            'tools/call',
            ['name' => $name, 'arguments' => $args],
            $timeoutMs,
            $idempotencyKey !== null,
            $idempotencyKey,
        );

        return $this->normalizeCallResult($result);
    }

    private function rpc(string $method, array $params, ?int $timeoutMs, bool $retryable, ?string $idempotencyKey = null): mixed
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'User-Agent' => $this->userAgent,
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $result = $this->transport->send([
            'url' => $this->mcpUrl,
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode([
                'jsonrpc' => '2.0',
                'id' => $this->nextId++,
                'method' => $method,
                'params' => $params,
            ], JSON_THROW_ON_ERROR),
            'retryable' => $retryable,
            'label' => 'Runtime request',
            'timeoutMs' => $timeoutMs,
        ]);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw $this->mapRuntimeHttpError($result['status'], $result['body']);
        }

        return $this->unwrapEnvelope($result['body']);
    }

    private function unwrapEnvelope(mixed $body): mixed
    {
        $envelope = $this->extractEnvelope($body);

        if (! is_array($envelope)) {
            throw new ProtocolException('Runtime returned a non-JSON-RPC response.', null, $body);
        }

        if (isset($envelope['error']) && is_array($envelope['error'])) {
            throw new ProtocolException((string) ($envelope['error']['message'] ?? 'Runtime returned a JSON-RPC error.'), null, $envelope['error']);
        }

        if (! array_key_exists('result', $envelope)) {
            throw new ProtocolException('Runtime JSON-RPC response has no result.', null, $body);
        }

        return $envelope['result'];
    }

    private function extractEnvelope(mixed $body): mixed
    {
        if (! is_string($body)) {
            return $body;
        }

        $last = null;
        foreach (preg_split('/\R/', $body) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'data:')) {
                $decoded = json_decode(trim(substr($trimmed, 5)), true);
                if (is_array($decoded)) {
                    $last = $decoded;
                }
            }
        }

        return $last ?? $body;
    }

    private function mapRuntimeHttpError(int $status, mixed $body): VinkiusException
    {
        $message = $this->pickRuntimeMessage($body, "Runtime request failed with status {$status}");

        return match (true) {
            in_array($status, [401, 403], true) => new AuthenticationException($message, $status, $body),
            $status === 404 => new NotFoundException($message, $status, $body),
            default => new ConnectionException($message, $status, $body),
        };
    }

    private function pickRuntimeMessage(mixed $body, string $fallback): string
    {
        if (is_array($body) || is_object($body)) {
            $b = (array) $body;
            if (isset($b['error']) && is_string($b['error']) && $b['error'] !== '') {
                return $b['error'];
            }
            if (isset($b['error']) && is_array($b['error']) && isset($b['error']['message']) && is_string($b['error']['message'])) {
                return $b['error']['message'];
            }
            if (isset($b['message']) && is_string($b['message']) && $b['message'] !== '') {
                return $b['message'];
            }
        }
        if (is_string($body) && $body !== '') {
            return $body;
        }

        return $fallback;
    }

    private function normalizeToolDefinition(mixed $tool): array
    {
        $t = is_array($tool) ? $tool : (is_object($tool) ? (array) $tool : []);
        $name = $t['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw new ProtocolException('Runtime tool definition is missing a name.', null, $tool);
        }

        $normalized = [
            'name' => $name,
            'title' => isset($t['title']) && is_string($t['title']) ? $t['title'] : null,
            'description' => isset($t['description']) && is_string($t['description']) ? $t['description'] : null,
            'input_schema' => $t['inputSchema'] ?? $t['input_schema'] ?? [],
        ];

        if (isset($t['annotations'])) {
            $normalized['annotations'] = $t['annotations'];
        }

        return $normalized;
    }

    private function normalizeCallResult(mixed $result): array
    {
        $r = is_array($result) ? $result : (is_object($result) ? (array) $result : []);

        $content = [];
        $rawContent = $r['content'] ?? [];
        if (is_array($rawContent)) {
            foreach ($rawContent as $entry) {
                if (! (is_array($entry) || is_object($entry))) {
                    continue;
                }
                $e = (array) $entry;
                $content[] = [
                    'type' => isset($e['type']) && is_string($e['type']) ? $e['type'] : 'text',
                    'text' => isset($e['text']) && is_string($e['text']) ? $e['text'] : json_encode($entry),
                ];
            }
        }

        $normalized = [
            'content' => $content,
            'isError' => ($r['isError'] ?? false) === true,
        ];

        if (array_key_exists('structuredContent', $r)) {
            $normalized['structuredContent'] = $r['structuredContent'];
        }

        $this->throwOnVinkiusBlock($normalized, $content);

        return $normalized;
    }

    private function throwOnVinkiusBlock(array $result, array $content): void
    {
        if (($result['isError'] ?? false) !== true) {
            return;
        }

        $structured = $result['structuredContent'] ?? null;
        if (! is_array($structured)) {
            return;
        }

        $kind = $structured['vinkius_block'] ?? null;
        $upgradeUrl = isset($structured['upgrade_url']) && is_string($structured['upgrade_url']) ? $structured['upgrade_url'] : null;
        $text = $content[0]['text'] ?? 'Request blocked by the Vinkius runtime.';

        match (true) {
            $kind === 'quota' => $this->throwBlock(new QuotaExceededException($text, 429, $structured), $upgradeUrl),
            $kind === 'overage' => $this->throwBlock(new OverageException($text, 402, $structured), $upgradeUrl),
            $kind === 'circuit_breaker' => throw new RateLimitException($text, 429, $structured),
            default => null,
        };
    }

    private function throwBlock(VinkiusException $exception, ?string $upgradeUrl): void
    {
        $exception->upgradeUrl = $upgradeUrl;
        throw $exception;
    }
}

<?php

namespace Vinkius\ConnectLaravel\Fluent;

use Vinkius\ConnectLaravel\Capability;
use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Exceptions\ConnectorNotConnectedException;
use Vinkius\ConnectLaravel\Exceptions\ProtocolException;
use Vinkius\ConnectLaravel\Http\RuntimeClient;
use Vinkius\ConnectLaravel\Resources\ConnectionsResource;
use Vinkius\ConnectLaravel\UserContext;
use Vinkius\ConnectLaravel\Vinkius;

class Connector
{
    protected string $slug;

    protected ?string $connectionId = null;

    protected ?RuntimeClient $runtimeClient = null;

    protected ?string $provisionedFor = null;

    protected ?string $provisionedUrl = null;

    public CredentialsHandle $credentials;

    public function __construct(
        protected UserContext $user,
        string $slug,
        protected Vinkius $client,
    ) {
        $this->slug = $slug;
        $this->credentials = new CredentialsHandle($this);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function user(): UserContext
    {
        return $this->user;
    }

    /**
     * Connect this connector for the user (get-or-create; idempotent), then
     * provision the connection's data-plane token. The returned array carries
     * `runtime_url` — the sole surface for listing/executing this connection's
     * tools, metered and revocable.
     *
     * @return array<string, mixed>
     */
    public function connect(?array $options = null): array
    {
        $options ??= [];
        $connection = $this->connections()->create($this->slug, $options);
        $this->connectionId = $connection['id'];

        $runtimeUrl = $this->provisionRuntime((string) $connection['id'], $options);

        return $connection + ['runtime_url' => $runtimeUrl];
    }

    public function disconnect(?array $options = null): void
    {
        $options ??= [];
        $this->connections()->delete($this->resolveConnectionId($options), $options);

        $this->connectionId = null;
        $this->runtimeClient = null;
        $this->provisionedFor = null;
        $this->provisionedUrl = null;
    }

    public function status(?array $options = null): string
    {
        $connection = $this->findConnection($options ?? []);

        return $connection !== null ? Status::derive($connection) : 'not_connected';
    }

    public function capabilities(?array $options = null): CapabilitySet
    {
        $options ??= [];
        $runtime = $this->resolveRuntime($options);

        return $this->listCapabilities($runtime, (string) $this->connectionId, $options);
    }

    public function capabilitiesForConnection(string $connectionId, ?array $options = null): CapabilitySet
    {
        $this->connectionId = $connectionId;

        return $this->capabilities($options);
    }

    public function runtimeUrl(): ?string
    {
        return $this->provisionedUrl;
    }

    public function resolveConnectionId(?array $options = null): string
    {
        $options ??= [];
        if ($this->connectionId !== null) {
            return $this->connectionId;
        }

        $connection = $this->findConnection($options);
        if ($connection === null) {
            throw new ConnectorNotConnectedException(
                "Connector \"{$this->slug}\" is not connected for this user — call connect() first."
            );
        }

        return (string) $connection['id'];
    }

    protected function connections(): ConnectionsResource
    {
        return $this->client->users->connections($this->user->externalId());
    }

    protected function findConnection(?array $options): ?array
    {
        $connections = $this->connections()->list($options ?? []);

        foreach ($connections as $connection) {
            if (($connection['slug'] ?? null) === $this->slug || ($connection['id'] ?? null) === $this->slug) {
                $this->connectionId = (string) $connection['id'];

                return $connection;
            }
        }

        return null;
    }

    protected function resolveRuntime(array $options): RuntimeClient
    {
        $connectionId = $this->resolveConnectionId($options);

        if ($this->runtimeClient === null) {
            $this->provisionRuntime($connectionId, $options);
        }

        return $this->runtimeClient;
    }

    protected function provisionRuntime(string $connectionId, array $options): string
    {
        if ($this->provisionedFor === $connectionId && $this->provisionedUrl !== null) {
            return $this->provisionedUrl;
        }

        $issued = $this->connections()->tokens($connectionId)->issue(null, $options);
        $mcpUrl = $issued['mcp_url'] ?? null;

        if (! is_string($mcpUrl) || $mcpUrl === '') {
            throw new ProtocolException('Token provisioning did not return a runtime URL.', null, [
                'connectionId' => $connectionId,
            ]);
        }

        $this->runtimeClient = $this->client->runtime($mcpUrl);
        $this->provisionedFor = $connectionId;
        $this->provisionedUrl = $mcpUrl;

        return $mcpUrl;
    }

    protected function listCapabilities(RuntimeClient $runtime, string $connectionId, array $options): CapabilitySet
    {
        $tools = $runtime->listTools($options['timeoutMs'] ?? null);

        $items = [];
        foreach ($tools as $tool) {
            $items[] = Capability::build($tool, [
                'connector' => $this->slug,
                'connectionId' => $connectionId,
                'namespace' => fn (string $connector, string $name) => $this->client->call($connector, $name),
                'executor' => $this->makeExecutor($runtime),
            ]);
        }

        return CapabilitySet::fromCapabilities($items);
    }

    protected function makeExecutor(RuntimeClient $runtime): \Closure
    {
        return static function (string $rawName, ?array $args, ?array $opts) use ($runtime): array {
            return $runtime->callTool(
                $rawName,
                $args ?? [],
                $opts['idempotencyKey'] ?? null,
                $opts['timeoutMs'] ?? null,
            );
        };
    }
}

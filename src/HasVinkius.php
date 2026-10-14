<?php

namespace Vinkius\ConnectLaravel;

use Vinkius\ConnectLaravel\Fluent\Connector;

/**
 * Bridge between a Laravel user and the Vinkius platform. The platform keys
 * every request on (application_id, external_id); external_id is derived from
 * the model and no per-user state is stored locally.
 */
trait HasVinkius
{
    public function vinkiusExternalId(): string
    {
        $resolver = config('vinkius.external_id');

        if (is_string($resolver) && $resolver !== '') {
            $value = $this->resolveIdentifier($resolver);

            return (string) $value;
        }

        if (is_callable($resolver)) {
            return (string) $resolver($this);
        }

        return (string) $this->getKey();
    }

    private function resolveIdentifier(string $name): mixed
    {
        if (property_exists($this, $name)) {
            $value = $this->{$name};
        } elseif (method_exists($this, $name)) {
            $value = $this->{$name}();
        } else {
            $getter = 'get' . ucfirst($name);
            $value = $this->{$getter}();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }

    public function vinkius(): UserContext
    {
        return \Vinkius\ConnectLaravel\Facades\Vinkius::user($this->vinkiusExternalId());
    }

    /**
     * Upsert the user on the Vinkius platform, optionally attaching metadata.
     *
     * @return array<string, mixed>
     */
    public function vinkiusEnsure(array $metadata = []): array
    {
        return $this->vinkius()->ensure($metadata !== [] ? $metadata : null);
    }

    /**
     * @return list<array{slug: string, status: string, connectionId?: string}>
     */
    public function vinkiusConnectors(): array
    {
        return $this->vinkius()->connectors();
    }

    public function vinkiusConnector(string $slug): Connector
    {
        return $this->vinkius()->connector($slug);
    }

    /**
     * Aggregated, executable capabilities across the user's ready connectors.
     *
     * @param  array{include?: list<string>, exclude?: list<string>}  $filters
     */
    public function vinkiusCapabilities(array $filters = [], ?array $options = null, ?callable $onConnectorError = null): CapabilitySet
    {
        return $this->vinkius()->capabilities($filters, $options, $onConnectorError);
    }

    public function vinkiusConnected(string $slug): bool
    {
        return $this->vinkius()->isConnectorReady($slug);
    }

    /**
     * Connect the connector (idempotent) and provision its data-plane token.
     *
     * @return array<string, mixed>
     */
    public function vinkiusConnect(string $slug, array $options = []): array
    {
        return $this->vinkiusConnector($slug)->connect($options);
    }

    public function vinkiusDisconnect(string $slug): void
    {
        $this->vinkiusConnector($slug)->disconnect();
    }

    /**
     * Store credentials for the connection. By default the stored set is fully
     * replaced; pass merge: true to only update the keys sent.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    public function vinkiusSetCredentials(string $slug, array $values, bool $merge = false, array $options = []): array
    {
        $connector = $this->vinkiusConnector($slug);

        return $connector->credentials->set($values, ['merge' => $merge] + $options);
    }

    /**
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function vinkiusOpenAITools(array $filters = []): array
    {
        return \Vinkius\ConnectLaravel\Adapters\OpenAI::tools($this->vinkiusCapabilities($filters)->all());
    }

    /**
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function vinkiusAnthropicTools(array $filters = []): array
    {
        return \Vinkius\ConnectLaravel\Adapters\Anthropic::tools($this->vinkiusCapabilities($filters)->all());
    }

    /**
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function vinkiusGeminiTools(array $filters = []): array
    {
        return \Vinkius\ConnectLaravel\Adapters\Gemini::tools($this->vinkiusCapabilities($filters)->all());
    }

    /**
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function vinkiusJsonSchemaTools(array $filters = []): array
    {
        return \Vinkius\ConnectLaravel\Adapters\JsonSchema::tools($this->vinkiusCapabilities($filters)->all());
    }

    /**
     * Optional local mirror of the last observed connector state. Populated
     * only when `vinkius.state_cache.enabled` is true and the migration was
     * run; the platform itself remains the source of truth.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function vinkiusState()
    {
        return \Vinkius\ConnectLaravel\Models\VinkiusState::query()
            ->where('external_id', $this->vinkiusExternalId());
    }
}

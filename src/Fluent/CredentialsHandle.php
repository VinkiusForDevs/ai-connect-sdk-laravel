<?php

namespace Vinkius\ConnectLaravel\Fluent;

use Vinkius\ConnectLaravel\Resources\CatalogResource;
use Vinkius\ConnectLaravel\Resources\CredentialsResource;

class CredentialsHandle
{
    public function __construct(protected Connector $connector) {}

    /**
     * The credential schema this connector requires (from the catalog, cached).
     *
     * @return array<string, mixed>
     */
    public function schema(?array $options = null): array
    {
        $slug = $this->connector->slug();
        $client = $this->connector->user()->vinkius();
        $opts = $options ?? [];

        return $client->cache()->resolve(
            "credential-schema:{$slug}",
            static function () use ($client, $slug, $opts) {
                $detail = (new CatalogResource($client->http()))->get($slug, $opts);

                return $detail['credential_schema'] ?? [];
            }
        );
    }

    /**
     * Which credential keys are configured (never the values).
     *
     * @return array<string, mixed>
     */
    public function status(?array $options = null): array
    {
        $opts = $options ?? [];
        $connectionId = $this->connector->resolveConnectionId($opts);

        return $this->client($connectionId)->status($opts);
    }

    /**
     * Store credentials, validated server-side. By default the stored set is
     * replaced; pass `merge => true` to update only the keys sent.
     *
     * @param  array<string, string>  $values
     * @param  array{merge?: bool, idempotencyKey?: string, timeoutMs?: int}  $options
     * @return array<string, mixed>
     */
    public function set(array $values, array $options = []): array
    {
        $merge = array_key_exists('merge', $options) ? (bool) $options['merge'] : null;
        unset($options['merge']);

        $connectionId = $this->connector->resolveConnectionId($options);

        $input = ['credentials' => $values];
        if ($merge !== null) {
            $input['merge'] = $merge;
        }

        return $this->client($connectionId)->set($input, $options);
    }

    protected function client(string $connectionId): CredentialsResource
    {
        $user = $this->connector->user();
        $client = $user->vinkius();

        return $client->users->connections($user->externalId())->credentials($connectionId);
    }
}

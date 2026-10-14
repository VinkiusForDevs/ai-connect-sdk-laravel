<?php

namespace Vinkius\ConnectLaravel;

use Vinkius\ConnectLaravel\Fluent\Connector;
use Vinkius\ConnectLaravel\Fluent\Status;
use Vinkius\ConnectLaravel\Resources\AppUsersResource;
use Vinkius\ConnectLaravel\Support\Pools;
use Vinkius\ConnectLaravel\Support\Validation;

class UserContext
{
    /** @var array<string, Connector> */
    protected array $connectorHandles = [];

    protected AppUsersResource $users;

    public function __construct(
        protected string $externalId,
        protected Vinkius $client,
    ) {
        Validation::assertExternalId($externalId);
        $this->users = new AppUsersResource($client->http(), $client->appId());
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function vinkius(): Vinkius
    {
        return $this->client;
    }

    /**
     * Explicitly upsert the user (e.g. to attach metadata up front).
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public function ensure(?array $metadata = null, ?array $options = null): array
    {
        $input = ['external_id' => $this->externalId];
        if ($metadata !== null) {
            $input['metadata'] = $metadata;
        }

        return $this->users->create($input, $options ?? []);
    }

    /**
     * Fetch the underlying user resource.
     *
     * @return array<string, mixed>
     */
    public function get(?array $options = null): array
    {
        return $this->users->get($this->externalId, $options ?? []);
    }

    /**
     * Update the user (metadata / status).
     *
     * @return array<string, mixed>
     */
    public function update(array $patch, ?array $options = null): array
    {
        return $this->users->update($this->externalId, $patch, $options ?? []);
    }

    public function delete(?array $options = null): void
    {
        $this->users->delete($this->externalId, $options ?? []);
    }

    public function connector(string $slug): Connector
    {
        return $this->connectorHandles[$slug] ??= new Connector($this, $slug, $this->client);
    }

    /**
     * Connected connectors for this user, with derived status.
     *
     * @return list<array{slug: string, status: string, connectionId?: string}>
     */
    public function connectors(?array $options = null): array
    {
        $connections = $this->users->connections($this->externalId)->list($options ?? []);

        return array_map(fn ($connection) => Status::summarize($connection), $connections);
    }

    /**
     * Aggregated, executable capabilities across the user's ready connectors.
     *
     * @param  array{include?: list<string>, exclude?: list<string>}  $filters
     * @param  callable(string, \Throwable): void|null  $onConnectorError
     */
    public function capabilities(array $filters = [], ?array $options = null, ?callable $onConnectorError = null): CapabilitySet
    {
        $include = ! empty($filters['include']) ? $filters['include'] : null;
        $exclude = ! empty($filters['exclude']) ? $filters['exclude'] : null;

        $reqOpts = array_intersect_key($options ?? [], array_flip(['timeoutMs', 'signal']));

        $summaries = $this->connectors($reqOpts);

        $targets = array_values(array_filter(
            $summaries,
            static fn (array $summary) => $summary['status'] === 'ready'
                && ($include === null || in_array($summary['slug'], $include, true))
                && ($exclude === null || ! in_array($summary['slug'], $exclude, true))
        ));

        $firstError = null;

        $sets = Pools::map($targets, function (array $summary) use ($onConnectorError, $reqOpts, &$firstError) {
            $slug = $summary['slug'];

            try {
                $connector = $this->connector($slug);

                return array_key_exists('connectionId', $summary)
                    ? $connector->capabilitiesForConnection($summary['connectionId'], $reqOpts)
                    : $connector->capabilities($reqOpts);
            } catch (\Throwable $error) {
                $firstError ??= $error;
                if ($onConnectorError !== null) {
                    $onConnectorError($slug, $error);
                }

                return null;
            }
        });

        $failed = count(array_filter($sets, static fn ($set) => $set === null));

        if ($failed > 0 && $failed === count($targets)) {
            throw $firstError;
        }

        $merged = new CapabilitySet();
        foreach ($sets as $set) {
            if ($set !== null) {
                foreach ($set->all() as $capability) {
                    $merged->push($capability);
                }
            }
        }

        return $merged;
    }

    public function isConnectorReady(string $slug): bool
    {
        foreach ($this->connectors() as $summary) {
            if ($summary['slug'] === $slug) {
                return $summary['status'] === 'ready';
            }
        }

        return false;
    }
}

<?php

namespace Vinkius\ConnectLaravel\Resources;

use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Support\Validation;

class ConnectionsResource
{
    public function __construct(
        protected Client $http,
        protected string $appId,
        protected string $externalId,
    ) {
        Validation::assertExternalId($externalId);
    }

    private function base(): string
    {
        return "/apps/" . rawurlencode($this->appId)
            . "/users/" . rawurlencode($this->externalId)
            . "/mcps";
    }

    /**
     * List the user's connections (not paginated; empty for an unknown user).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $options = []): array
    {
        $body = $this->http->get($this->base(), [], $options);
        $list = $this->listFromBody($body);

        return is_array($list) ? $list : [];
    }

    /**
     * Connect a catalog connector for the user (get-or-create; idempotent).
     *
     * @return array<string, mixed>
     */
    public function create(string $connector, array $options = []): array
    {
        $body = $this->http->post($this->base(), ['catalog_mcp_id' => $connector], ['idempotent' => true] + $options);

        return $this->item($body);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $connectionId, array $options = []): array
    {
        $body = $this->http->get($this->base() . '/' . rawurlencode($connectionId), [], $options);

        return $this->item($body);
    }

    public function delete(string $connectionId, array $options = []): void
    {
        $this->http->delete($this->base() . '/' . rawurlencode($connectionId), $options);
    }

    public function credentials(string $connectionId): CredentialsResource
    {
        return new CredentialsResource($this->http, $this->appId, $this->externalId, $connectionId);
    }

    public function tokens(string $connectionId): TokensResource
    {
        return new TokensResource($this->http, $this->appId, $this->externalId, $connectionId);
    }

    private function item(mixed $body): array
    {
        if (is_array($body) && array_key_exists('data', $body)) {
            return is_array($body['data']) ? $body['data'] : [];
        }

        return is_array($body) ? $body : [];
    }

    private function listFromBody(mixed $body): mixed
    {
        if (is_array($body) && array_key_exists('data', $body)) {
            return $body['data'];
        }

        return $body;
    }
}

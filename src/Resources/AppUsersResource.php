<?php

namespace Vinkius\ConnectLaravel\Resources;

use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Support\Validation;

class AppUsersResource
{
    public function __construct(
        protected Client $http,
        protected string $appId,
    ) {}

    private function base(): string
    {
        return "/apps/" . rawurlencode($this->appId) . "/users";
    }

    private function userPath(string $externalId): string
    {
        Validation::assertExternalId($externalId);

        return $this->base() . '/' . rawurlencode($externalId);
    }

    /**
     * Get-or-create a user by external id (idempotent).
     *
     * @param array{external_id: string, status?: string, metadata?: array<string, mixed>} $input
     * @return array<string, mixed>
     */
    public function create(array $input, array $options = []): array
    {
        Validation::assertExternalId($input['external_id']);
        $body = $this->http->post($this->base(), $input, ['idempotent' => true] + $options);

        return $this->item($body);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $externalId, array $options = []): array
    {
        $body = $this->http->get($this->userPath($externalId), [], $options);

        return $this->item($body);
    }

    /**
     * @param array{status?: string, metadata?: array<string, mixed>} $patch
     * @return array<string, mixed>
     */
    public function update(string $externalId, array $patch, array $options = []): array
    {
        $body = $this->http->patch($this->userPath($externalId), $patch, $options);

        return $this->item($body);
    }

    public function delete(string $externalId, array $options = []): void
    {
        $this->http->delete($this->userPath($externalId), $options);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta?: array, links?: array}
     */
    public function list(array $options = []): array
    {
        $body = $this->http->get($this->base(), [
            'status' => $options['status'] ?? null,
            'page' => $options['page'] ?? null,
        ], $options);

        return $this->paginated($body);
    }

    public function connections(string $externalId): ConnectionsResource
    {
        return new ConnectionsResource($this->http, $this->appId, $externalId);
    }

    private function item(mixed $body): array
    {
        if (is_array($body) && array_key_exists('data', $body) && is_array($body['data'])) {
            return $body['data'];
        }

        return is_array($body) ? $body : [];
    }

    private function paginated(mixed $body): array
    {
        if (!is_array($body)) {
            throw new \Vinkius\ConnectLaravel\Exceptions\ProtocolException('Expected a list response envelope.', 200, $body);
        }
        if (!isset($body['data']) || !is_array($body['data'])) {
            throw new \Vinkius\ConnectLaravel\Exceptions\ProtocolException('Expected a list response envelope.', 200, $body);
        }

        $out = ['data' => $body['data']];
        if (isset($body['meta']) && is_array($body['meta'])) {
            $out['meta'] = $body['meta'];
        }
        if (isset($body['links']) && is_array($body['links'])) {
            $out['links'] = $body['links'];
        }

        return $out;
    }
}

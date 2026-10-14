<?php

namespace Vinkius\ConnectLaravel\Resources;

use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Support\Validation;

class TokensResource
{
    public function __construct(
        protected Client $http,
        protected string $appId,
        protected string $externalId,
        protected string $connectionId,
    ) {
        Validation::assertExternalId($externalId);
    }

    private function base(): string
    {
        return "/apps/" . rawurlencode($this->appId)
            . "/users/" . rawurlencode($this->externalId)
            . "/mcps/" . rawurlencode($this->connectionId)
            . "/tokens";
    }

    /**
     * Mint a data-plane token; the response carries the one-time plaintext and mcp_url.
     *
     * @return array<string, mixed>
     */
    public function issue(?string $name = null, array $options = []): array
    {
        $body = $this->http->post(
            $this->base(),
            $name !== null ? ['name' => $name] : [],
            ['idempotent' => false] + $options,
        );

        if (is_array($body) && array_key_exists('data', $body)) {
            return is_array($body['data']) ? $body['data'] : [];
        }

        return is_array($body) ? $body : [];
    }
}

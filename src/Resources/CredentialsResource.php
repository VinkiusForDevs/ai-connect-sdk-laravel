<?php

namespace Vinkius\ConnectLaravel\Resources;

use Vinkius\ConnectLaravel\Http\Client;
use Vinkius\ConnectLaravel\Support\Validation;

class CredentialsResource
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
            . "/credentials";
    }

    /**
     * Which credential keys are configured (never the values).
     *
     * @return array<string, mixed>
     */
    public function status(array $options = []): array
    {
        $body = $this->http->get($this->base(), [], $options);

        return is_array($body) ? $body : [];
    }

    /**
     * Store credentials, validated server-side against the connector schema.
     *
     * @param  array{credentials: array<string,string>, merge?: bool}  $input
     * @return array<string, mixed>
     */
    public function set(array $input, array $options = []): array
    {
        $body = $this->http->put($this->base(), $input, $options);

        return is_array($body) ? $body : [];
    }
}

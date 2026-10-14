<?php

namespace Vinkius\ConnectLaravel;

use Closure;
use Vinkius\ConnectLaravel\Exceptions\ProtocolException;

class Capability
{
    /** @var array{connector:string, connectionId:string} */
    public readonly array $routing;

    public string $name;

    public string $rawName;

    public ?string $title;

    public string $description;

    /** @var array<string, mixed> */
    public array $inputSchema;

    /** @var Closure(string, array|null, array|null): array */
    private Closure $executor;

    public function __construct(
        string $connector,
        string $connectionId,
        string $name,
        string $rawName,
        ?string $title,
        string $description,
        array $inputSchema,
        Closure $executor,
    ) {
        $this->routing = ['connector' => $connector, 'connectionId' => $connectionId];
        $this->name = $name;
        $this->rawName = $rawName;
        $this->title = $title;
        $this->description = $description;
        $this->inputSchema = $inputSchema;
        $this->executor = $executor;
    }

    /**
     * Execute this capability at the runtime. An upstream tool failure is returned
     * as `isError => true` (never thrown). Vinkius-gate blocks are thrown as typed errors.
     *
     * @param  array<string, mixed>|null $args
     * @param  array{idempotencyKey?: string, timeoutMs?: int}|null $options
     * @return array{content: list<array{type:string,text:string}>, isError: bool, structuredContent?: mixed}
     */
    public function execute(?array $args = null, ?array $options = null): array
    {
        return ($this->executor)(
            $this->rawName,
            $args,
            [
                'idempotencyKey' => $options['idempotencyKey'] ?? null,
                'timeoutMs' => $options['timeoutMs'] ?? null,
            ],
        );
    }

    public static function build(array $data, array $context): self
    {
        $rawName = $data['name'] ?? null;
        if (! is_string($rawName) || trim($rawName) === '') {
            throw new ProtocolException('Capability payload is missing a non-empty name.', null, $data);
        }

        $connector = $context['connector'] ?? null;
        $connectionId = $context['connectionId'] ?? null;
        if (! $connector || ! $connectionId) {
            throw new ProtocolException('Capability payload is missing connector routing metadata.', null, [
                'connector' => $connector,
                'connectionId' => $connectionId,
            ]);
        }

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? '';
        $schema = $data['input_schema'] ?? $data['inputSchema'] ?? [];

        return new self(
            connector: $connector,
            connectionId: $connectionId,
            name: $context['namespace']($connector, $rawName),
            rawName: $rawName,
            title: is_string($title) ? $title : null,
            description: is_string($description) ? $description : '',
            inputSchema: is_array($schema) ? $schema : [],
            executor: $context['executor'],
        );
    }
}

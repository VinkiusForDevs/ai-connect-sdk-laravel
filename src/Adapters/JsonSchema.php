<?php

namespace Vinkius\ConnectLaravel\Adapters;

use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Adapters\Concerns\Shared;

class JsonSchema
{
    use Shared;

    /**
     * Plain { name, description, parameters } definitions — the lowest common
     * denominator for any OpenAI-compatible provider or custom agent loop.
     *
     * @param  list<\Vinkius\ConnectLaravel\Capability>  $capabilities
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public static function tools(array $capabilities): array
    {
        $tools = [];
        foreach ($capabilities as $capability) {
            $tools[] = [
                'name' => $capability->name,
                'description' => $capability->description,
                'parameters' => self::normalizeParams($capability->inputSchema),
            ];
        }

        return $tools;
    }

    /**
     * @return array{content: list<array{type:string,text:string}>, isError: bool}
     */
    public static function execute(CapabilitySet $capabilities, string $name, ?array $args = null, ?array $options = null): array
    {
        $capability = self::findCapability($capabilities, $name);

        return $capability->execute($args ?? [], $options);
    }
}

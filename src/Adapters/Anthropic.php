<?php

namespace Vinkius\ConnectLaravel\Adapters;

use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Adapters\Concerns\Shared;

class Anthropic
{
    use Shared;

    private const NAME_RE = '/^[a-zA-Z0-9_-]+$/';

    private const NAME_MAX = 128;

    /**
     * @param  list<\Vinkius\ConnectLaravel\Capability>  $capabilities
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public static function tools(array $capabilities): array
    {
        $tools = [];
        foreach ($capabilities as $capability) {
            self::validateToolName($capability->name, 'Anthropic', self::NAME_RE, self::NAME_MAX);

            $tools[] = [
                'name' => $capability->name,
                'description' => $capability->description,
                'input_schema' => self::normalizeParams($capability->inputSchema),
            ];
        }

        return $tools;
    }

    /**
     * Execute the Vinkius capability named by an Anthropic tool_use block.
     *
     * @param  array{name: string, input?: array<string, mixed>}  $use
     * @return array{content: list<array{type:string,text:string}>, isError: bool}
     */
    public static function run(CapabilitySet $capabilities, array $use, ?array $options = null): array
    {
        $capability = self::findCapability($capabilities, $use['name']);

        return $capability->execute($use['input'] ?? [], $options);
    }
}

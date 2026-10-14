<?php

namespace Vinkius\ConnectLaravel\Adapters;

use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Adapters\Concerns\Shared;

class Gemini
{
    use Shared;

    private const NAME_RE = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const NAME_MAX = 64;

    /**
     * @param  list<\Vinkius\ConnectLaravel\Capability>  $capabilities
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public static function tools(array $capabilities): array
    {
        $tools = [];
        foreach ($capabilities as $capability) {
            self::validateToolName($capability->name, 'Gemini', self::NAME_RE, self::NAME_MAX);

            $tools[] = [
                'name' => $capability->name,
                'description' => $capability->description,
                'parameters' => self::normalizeParams($capability->inputSchema),
            ];
        }

        return $tools;
    }

    /**
     * Execute the Vinkius capability named by a Gemini function call.
     *
     * @param  array{name: string, args?: array<string, mixed>}  $call
     * @return array{content: list<array{type:string,text:string}>, isError: bool}
     */
    public static function run(CapabilitySet $capabilities, array $call, ?array $options = null): array
    {
        $capability = self::findCapability($capabilities, $call['name']);

        return $capability->execute($call['args'] ?? [], $options);
    }
}

<?php

namespace Vinkius\ConnectLaravel\Adapters;

use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Adapters\Concerns\Shared;

class OpenAI
{
    use Shared;

    private const NAME_RE = '/^[a-zA-Z0-9_-]+$/';

    private const NAME_MAX = 64;

    /**
     * @param  list<\Vinkius\ConnectLaravel\Capability>  $capabilities
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public static function tools(array $capabilities): array
    {
        $tools = [];
        foreach ($capabilities as $capability) {
            self::validateToolName($capability->name, 'OpenAI', self::NAME_RE, self::NAME_MAX);

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $capability->name,
                    'description' => $capability->description,
                    'parameters' => self::normalizeParams($capability->inputSchema),
                ],
            ];
        }

        return $tools;
    }

    /**
     * Execute the Vinkius capability named by an OpenAI tool call.
     *
     * @param  array{function: array{name: string, arguments: string}} $call
     * @return array{content: list<array{type:string,text:string}>, isError: bool}
     */
    public static function run(CapabilitySet $capabilities, array $call, ?array $options = null): array
    {
        $capability = self::findCapability($capabilities, $call['function']['name']);

        return $capability->execute(self::parseArgs($call['function']['arguments']), $options);
    }
}

<?php

namespace Vinkius\ConnectLaravel\Adapters\Concerns;

use Vinkius\ConnectLaravel\Capability;
use Vinkius\ConnectLaravel\CapabilitySet;
use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;
use Vinkius\ConnectLaravel\Exceptions\NotFoundException;

trait Shared
{
    /**
     * Ensure a usable JSON Schema object for function/tool parameters.
     *
     * @return array<string, mixed>
     */
    protected static function normalizeParams(array $schema): array
    {
        if ($schema !== []) {
            return $schema;
        }

        return ['type' => 'object', 'properties' => []];
    }

    /**
     * Parse a JSON arguments string into an object, tolerating malformed input.
     *
     * @return array<string, mixed>
     */
    protected static function parseArgs(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<Capability>|CapabilitySet  $capabilities
     */
    protected static function findCapability(CapabilitySet|array $capabilities, string $name): Capability
    {
        if ($capabilities instanceof CapabilitySet) {
            $found = $capabilities->find($name);
        } else {
            $found = null;
            foreach ($capabilities as $candidate) {
                if ($candidate->name === $name || $candidate->rawName === $name) {
                    $found = $candidate;
                    break;
                }
            }
        }

        if ($found === null) {
            throw new NotFoundException("Unknown capability: {$name}");
        }

        return $found;
    }

    protected static function validateToolName(string $name, string $platform, string $pattern, int $maxLength): void
    {
        if (strlen($name) > $maxLength || preg_match($pattern, $name) !== 1) {
            throw new ConfigurationException(
                "Capability \"{$name}\" cannot be exposed as a {$platform} tool name: it must be "
                . "{$maxLength} char(s) or fewer and match {$pattern} (got " . strlen($name) . " char(s)). "
                . 'Override the namespace option to produce platform-compatible names.'
            );
        }
    }
}

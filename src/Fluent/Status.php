<?php

namespace Vinkius\ConnectLaravel\Fluent;

class Status
{
    /**
     * Map a connection array to its derived connector status.
     *
     * @param  array<string, mixed>  $connection
     */
    public static function derive(array $connection): string
    {
        if (($connection['status'] ?? null) !== 'active') {
            return 'disabled';
        }

        return (($connection['ready'] ?? false) === true) ? 'ready' : 'needs_credentials';
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{slug: string, status: string, connectionId?: string}
     */
    public static function summarize(array $connection): array
    {
        $summary = [
            'slug' => $connection['slug'] ?? $connection['name'],
            'status' => self::derive($connection),
        ];

        if (! empty($connection['id'])) {
            $summary['connectionId'] = (string) $connection['id'];
        }

        return $summary;
    }
}

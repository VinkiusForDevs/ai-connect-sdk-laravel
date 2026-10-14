<?php

namespace Vinkius\ConnectLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class VinkiusState extends Model
{
    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'observed_at' => 'datetime',
    ];

    protected $fillable = [
        'external_id',
        'connector',
        'status',
        'connection_id',
        'payload',
        'observed_at',
    ];

    public function getTable(): string
    {
        return (string) config('vinkius.state_cache.table', 'vinkius_state');
    }

    /**
     * Upsert the observed state for a user + connector.
     */
    public static function record(string $externalId, string $connector, string $status, ?string $connectionId = null, ?array $payload = null): self
    {
        $instance = static::where('external_id', $externalId)
            ->where('connector', $connector)
            ->first();

        $values = [
            'status' => $status,
            'connection_id' => $connectionId,
            'payload' => $payload,
            'observed_at' => now(),
        ];

        if ($instance !== null) {
            $instance->fill($values)->save();

            return $instance;
        }

        return static::create($values + [
            'external_id' => $externalId,
            'connector' => $connector,
        ]);
    }
}

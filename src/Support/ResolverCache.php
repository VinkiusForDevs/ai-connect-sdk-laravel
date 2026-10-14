<?php

namespace Vinkius\ConnectLaravel\Support;

use Closure;

class ResolverCache
{
    /** @var array<string, array{value: mixed, expiresAt: int}> */
    private array $store = [];

    public function __construct(private int $ttlMs = 300_000) {}

    public function get(string $key): mixed
    {
        $entry = $this->store[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['expiresAt'] <= (int) (microtime(true) * 1000)) {
            unset($this->store[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expiresAt' => (int) (microtime(true) * 1000) + $this->ttlMs,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): void
    {
        $this->store = [];
    }

    /**
     * Return the cached value or compute, cache, and return it.
     *
     * @template T
     *
     * @param  Closure():T  $compute
     * @return T
     */
    public function resolve(string $key, Closure $compute): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $compute();
        $this->set($key, $value);

        return $value;
    }
}

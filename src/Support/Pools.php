<?php

namespace Vinkius\ConnectLaravel\Support;

class Pools
{
    /**
     * Map over items preserving order. PHP is sequential; the SDK's async
     * fan-out is surfaced as a plain loop with the same observable semantics
     * (all-failed throws, partial failures tolerated).
     *
     * @template TIn
     * @template TOut
     *
     * @param  list<TIn>  $items
     * @param  callable(TIn, int): TOut  $fn
     * @return list<TOut>
     */
    public static function map(array $items, callable $fn): array
    {
        $results = [];
        foreach ($items as $index => $item) {
            $results[] = $fn($item, $index);
        }

        return $results;
    }
}

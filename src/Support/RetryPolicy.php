<?php

namespace Vinkius\ConnectLaravel\Support;

class RetryPolicy
{
    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly int $baseDelayMs = 250,
        public readonly int $maxDelayMs = 4000,
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function isRetryableStatus(int $status): bool
    {
        return in_array($status, [429, 502, 503, 504], true);
    }

    public function backoffDelay(int $attempt, ?int $retryAfterMs = null): int
    {
        if ($retryAfterMs !== null && $retryAfterMs > 0) {
            return (int) min($retryAfterMs, $this->maxDelayMs);
        }

        $window = (int) min($this->maxDelayMs, $this->baseDelayMs * 2 ** $attempt);

        return (int) round(random_int(0, max(1, $window)));
    }

    public static function parseRetryAfter(?string $headerValue): ?int
    {
        if ($headerValue === null || $headerValue === '') {
            return null;
        }

        if (is_numeric($headerValue)) {
            return (int) max(0, (int) $headerValue * 1000);
        }

        $asDate = strtotime($headerValue);
        if ($asDate !== false) {
            return (int) max(0, ($asDate - time()) * 1000);
        }

        return null;
    }

    public function sleep(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}

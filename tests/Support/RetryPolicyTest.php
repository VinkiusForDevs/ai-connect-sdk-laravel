<?php

namespace Vinkius\ConnectLaravel\Tests\Support;

use PHPUnit\Framework\TestCase;
use Vinkius\ConnectLaravel\Support\RetryPolicy;

final class RetryPolicyTest extends TestCase
{
    public function test_parses_retry_after_seconds(): void
    {
        self::assertSame(3000, RetryPolicy::parseRetryAfter('3'));
    }

    public function test_parses_retry_after_http_date(): void
    {
        $date = gmdate('D, d M Y H:i:s', time() + 10) . ' GMT';
        $parsed = RetryPolicy::parseRetryAfter($date);

        self::assertGreaterThanOrEqual(9_000, $parsed);
        self::assertLessThanOrEqual(11_000, $parsed);
    }

    public function test_parses_retry_after_none(): void
    {
        self::assertNull(RetryPolicy::parseRetryAfter(null));
        self::assertNull(RetryPolicy::parseRetryAfter('not-a-date'));
    }

    public function test_retryable_statuses(): void
    {
        $policy = RetryPolicy::default();

        self::assertTrue($policy->isRetryableStatus(429));
        self::assertTrue($policy->isRetryableStatus(502));
        self::assertTrue($policy->isRetryableStatus(503));
        self::assertTrue($policy->isRetryableStatus(504));
        self::assertFalse($policy->isRetryableStatus(400));
        self::assertFalse($policy->isRetryableStatus(404));
    }

    public function test_retry_after_hint_wins_over_jitter(): void
    {
        $policy = new RetryPolicy(maxRetries: 2, baseDelayMs: 250, maxDelayMs: 4000);

        self::assertSame(2000, $policy->backoffDelay(0, 2000));
        self::assertSame(4000, $policy->backoffDelay(0, 999_999));
    }

    public function test_full_jitter_grows_with_attempt(): void
    {
        $policy = new RetryPolicy(maxRetries: 4, baseDelayMs: 250, maxDelayMs: 4000);

        $window0 = min($policy->maxDelayMs, $policy->baseDelayMs * 2 ** 0);
        $delay0 = $policy->backoffDelay(0);

        self::assertGreaterThanOrEqual(0, $delay0);
        self::assertLessThanOrEqual($window0, $delay0);
    }
}

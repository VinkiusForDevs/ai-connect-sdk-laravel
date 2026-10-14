<?php

namespace Vinkius\ConnectLaravel\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use Vinkius\ConnectLaravel\Exceptions\AuthenticationException;
use Vinkius\ConnectLaravel\Exceptions\OverageException;
use Vinkius\ConnectLaravel\Exceptions\QuotaExceededException;
use Vinkius\ConnectLaravel\Exceptions\RateLimitException;
use Vinkius\ConnectLaravel\Exceptions\NotFoundException;
use Vinkius\ConnectLaravel\Exceptions\ValidationException;
use Vinkius\ConnectLaravel\Exceptions\VinkiusException;

final class VinkiusExceptionTest extends TestCase
{
    public function test_401_maps_to_authentication(): void
    {
        $e = VinkiusException::forStatus(401, ['message' => 'Invalid key'], 'req-1');
        self::assertInstanceOf(AuthenticationException::class, $e);
        self::assertSame('Invalid key', $e->getMessage());
        self::assertSame('req-1', $e->requestId());
        self::assertSame(401, $e->status());
    }

    public function test_404_maps_to_not_found(): void
    {
        $e = VinkiusException::forStatus(404, ['message' => 'No user'], 'req-2');
        self::assertInstanceOf(NotFoundException::class, $e);
    }

    public function test_422_maps_to_validation_with_field_errors(): void
    {
        $body = ['message' => 'Bad input', 'errors' => ['external_id' => ['too long']]];
        $e = VinkiusException::forStatus(422, $body, 'req-3');

        self::assertInstanceOf(ValidationException::class, $e);
        self::assertSame(['external_id' => ['too long']], $e->fieldErrors);
    }

    public function test_429_control_plane_maps_to_rate_limit(): void
    {
        $e = VinkiusException::forStatus(429, ['message' => 'Slow down'], 'req-4', 1500);
        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertSame(1500, $e->retryAfterMs);
    }

    public function test_429_data_plane_mcp_shaped_maps_to_quota(): void
    {
        $body = ['isError' => true, 'vinkius_block' => 'quota', 'upgrade_url' => 'https://x/upgrade'];
        $e = VinkiusException::forStatus(429, $body, 'req-5');

        self::assertInstanceOf(QuotaExceededException::class, $e);
        self::assertSame('https://x/upgrade', $e->upgradeUrl);
    }

    public function test_402_maps_to_overage_with_upgrade_url(): void
    {
        $body = ['message' => 'Payment required', 'upgrade_url' => 'https://x/billing'];
        $e = VinkiusException::forStatus(402, $body, 'req-6');

        self::assertInstanceOf(OverageException::class, $e);
        self::assertSame('https://x/billing', $e->upgradeUrl);
    }

    public function test_pick_message_from_content_array(): void
    {
        $msg = VinkiusException::pickMessage(['content' => [['text' => 'First wall']]], 'fallback');
        self::assertSame('First wall', $msg);
    }

    public function test_pick_message_prefers_top_level_error_string(): void
    {
        $msg = VinkiusException::pickMessage(['error' => 'x', 'content' => [['text' => 'First wall']]], 'fallback');
        self::assertSame('x', $msg);
    }

    public function test_unknown_status_maps_to_base_with_api_error_code(): void
    {
        $e = VinkiusException::forStatus(500, ['message' => 'Server exploded'], 'req-7');
        self::assertInstanceOf(VinkiusException::class, $e);
        self::assertSame('api_error', $e->code());
        self::assertSame(500, $e->status());
    }
}

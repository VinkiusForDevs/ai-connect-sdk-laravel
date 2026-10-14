<?php

namespace Vinkius\ConnectLaravel\Support;

class Redaction
{
    private const REDACTED = '[REDACTED]';

    private const MAX_DEPTH = 6;

    private const SECRET_HEADERS = [
        'authorization',
        'idempotency-key',
        'cookie',
        'set-cookie',
    ];

    private const SECRET_KEYS = [
        'authorization',
        'apikey',
        'api_key',
        'token',
        'access_token',
        'refresh_token',
        'mcp_url',
        'credentials',
        'password',
        'secret',
        'client_secret',
    ];

    public static function headers(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $out[$key] = in_array(strtolower((string) $key), self::SECRET_HEADERS, true)
                ? self::REDACTED
                : $value;
        }

        return $out;
    }

    public static function url(string $url): string
    {
        return preg_replace('/vk_live_[A-Za-z0-9_-]+/', self::REDACTED, $url) ?? $url;
    }

    public static function body(mixed $value): mixed
    {
        return self::cloneValue($value, 0);
    }

    private static function cloneValue(mixed $value, int $depth): mixed
    {
        if ($value === null || ! is_object($value) && ! is_array($value)) {
            return $value;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[TRUNCATED]';
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? strtolower($k) : null;
                $out[$k] = $key !== null && in_array($key, self::SECRET_KEYS, true)
                    ? self::REDACTED
                    : self::cloneValue($v, $depth + 1);
            }

            return $out;
        }

        $record = [];
        foreach ((array) $value as $k => $v) {
            $key = is_string($k) ? strtolower($k) : null;
            $record[$k] = $key !== null && in_array($key, self::SECRET_KEYS, true)
                ? self::REDACTED
                : self::cloneValue($v, $depth + 1);
        }

        return $record;
    }
}

<?php

namespace Vinkius\ConnectLaravel\Support;

use Vinkius\ConnectLaravel\Exceptions\ConfigurationException;

class Validation
{
    private const UNSAFE_EXTERNAL_ID = '/[\s\/\\\\]/';

    public static function assertExternalId(string $value): void
    {
        if ($value === '' || strlen($value) > 255) {
            throw ConfigurationException::create('externalId must be a non-empty string of at most 255 characters.');
        }

        if (preg_match(self::UNSAFE_EXTERNAL_ID, $value) === 1) {
            throw ConfigurationException::create(
                "Invalid externalId " . json_encode($value) . ': it must not contain whitespace, "/" or "\\".'
            );
        }
    }
}

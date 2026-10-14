<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class ConfigurationException extends VinkiusException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, 'config_error', null, $previous);
    }

    public static function create(string $message, ?Throwable $previous = null): self
    {
        return new self($message, $previous);
    }
}

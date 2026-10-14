<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class ValidationException extends VinkiusException
{
    /** @var array<string, string[]> */
    public array $fieldErrors = [];

    public function __construct(string $message = '', ?int $status = null, mixed $details = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, 'validation_error', $details, $previous);
    }
}

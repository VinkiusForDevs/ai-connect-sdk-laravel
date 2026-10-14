<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class ConnectionException extends VinkiusException
{
    public function __construct(string $message = '', ?int $status = null, mixed $details = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, 'connection_error', $details, $previous);
    }
}

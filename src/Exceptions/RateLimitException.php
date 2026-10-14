<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class RateLimitException extends VinkiusException
{
    public ?int $retryAfterMs;

    public function __construct(string $message = '', ?int $status = null, mixed $details = null, ?int $retryAfterMs = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, 'rate_limit', $details, $previous);
        $this->retryAfterMs = $retryAfterMs;
    }
}

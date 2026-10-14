<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class OverageException extends VinkiusException
{
    public ?string $upgradeUrl;

    public function __construct(string $message = '', ?int $status = null, mixed $details = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, 'overage_blocked', $details, $previous);
        $this->upgradeUrl = null;
    }
}

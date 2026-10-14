<?php

namespace Vinkius\ConnectLaravel\Exceptions;

use Throwable;

class ConnectorNotConnectedException extends VinkiusException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, 'connector_not_connected', null, $previous);
    }
}

<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Exceptions;

/**
 * No valid EET response was received (timeout, connection error, unexpected HTTP response).
 *
 * The state of the sale is unknown, so it has to be sent again: see Receipt::forResend().
 */
class TransportException extends ClientException
{
}

<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Exceptions;

use Ondrejnov\EET\Response;

/**
 * The response is not trustworthy: invalid signature, untrusted certificate or a different message UUID.
 *
 * The POK it may contain, available through getResponse(), must not be treated as a confirmation.
 */
class ResponseVerificationException extends ServerException
{
    public function __construct(string $message, ?Response $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $response, $previous);
    }

    public function withResponse(Response $response): self
    {
        return new self($this->getMessage(), $response, $this);
    }
}

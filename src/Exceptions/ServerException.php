<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Exceptions;

use Ondrejnov\EET\Response;

/**
 * The server rejected the message. The exception code is the EET error code.
 */
class ServerException extends \Exception
{
    /** Error codes of temporary failures, the message should be sent again later. */
    private const RETRYABLE_CODES = [-1, 8];

    public function __construct(string $message, int $code, private ?Response $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function isRetryable(): bool
    {
        return in_array($this->getCode(), self::RETRYABLE_CODES, true);
    }
}

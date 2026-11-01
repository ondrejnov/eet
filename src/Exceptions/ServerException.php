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
    const RETRYABLE_CODES = [-1, 8];

    /** @var Response|null */
    private $response;

    /**
     * @param Response|null $response
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $code, $response = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * @return Response|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function isRetryable(): bool
    {
        return in_array($this->getCode(), self::RETRYABLE_CODES, true);
    }
}

<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Http;

use Ondrejnov\EET\Utils\ReadOnlyProperties;

/**
 * @property-read int $status
 * @property-read string $body
 * @property-read string|null $transactionId
 */
final class HttpResponse
{
    use ReadOnlyProperties;

    private $status;

    private $body;

    private $transactionId;

    /**
     * @param string|null $transactionId
     */
    public function __construct(int $status, string $body, $transactionId = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->transactionId = $transactionId;
    }
}

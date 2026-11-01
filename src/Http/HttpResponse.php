<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $transactionId = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Http;

use Ondrejnov\EET\Exceptions\TransportException;

interface Transport
{
    /**
     * Sends a signed SOAP message.
     *
     * @throws TransportException when no HTTP response was received
     */
    public function post(string $url, string $body, string $soapAction): HttpResponse;
}

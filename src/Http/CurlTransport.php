<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Http;

use Ondrejnov\EET\Exceptions\TransportException;

/**
 * HTTPS transport as prescribed by the interface: HTTP/1.1, TLS 1.2 or newer, verified server certificate.
 *
 * Every request uses a new cURL handle, so the DNS name is always resolved again and DNS balancing
 * of the production environment is respected.
 */
final class CurlTransport implements Transport
{
    /**
     * @param int $timeoutMs total limit of one request in milliseconds
     * @param int $connectTimeoutMs limit for establishing the connection in milliseconds
     */
    public function __construct(
        private int $timeoutMs = 2500,
        private int $connectTimeoutMs = 2000,
    ) {
    }

    public function post(string $url, string $body, string $soapAction): HttpResponse
    {
        $transactionId = null;

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_USERAGENT => 'ondrejnov-eet',
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"',
                'Expect:',
            ],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$transactionId): int {
                if (stripos($line, 'X-Global-Transaction-Id:') === 0) {
                    $transactionId = trim(substr($line, strlen('X-Global-Transaction-Id:')));
                }

                return strlen($line);
            },
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            throw new TransportException(curl_error($curl), curl_errno($curl));
        }

        return new HttpResponse((int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) $response, $transactionId);
    }
}

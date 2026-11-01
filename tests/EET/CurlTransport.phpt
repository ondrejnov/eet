<?php

declare(strict_types=1);

use Ondrejnov\EET\Exceptions\TransportException;
use Ondrejnov\EET\Http\CurlTransport;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$transport = new CurlTransport(500, 500);

// HTTPS is mandatory, an unencrypted connection is refused before anything is sent.
Assert::exception(
    static function () use ($transport) { return $transport->post('http://127.0.0.1:1/', '<x/>', 'action'); },
    TransportException::class,
    '~http~i',
    CURLE_UNSUPPORTED_PROTOCOL
);

// A connection error is reported as a transport problem.
Assert::exception(
    static function () use ($transport) { return $transport->post('https://127.0.0.1:1/', '<x/>', 'action'); },
    TransportException::class
);

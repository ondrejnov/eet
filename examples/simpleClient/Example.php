<?php

declare(strict_types=1);

/**
 * Sends a sale to the EET playground, run it after scripts/fetch-playground-certs.sh.
 */

require_once __DIR__ . '/../bootstrap.php';

use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Exceptions\TransportException;
use Ondrejnov\EET\Receipt;

$certificate = Certificate::fromPem(DIR_CERT . '/CZ00000019.key', DIR_CERT . '/CZ00000019.pem');
$dispatcher = new Dispatcher(Dispatcher::PLAYGROUND_URL, $certificate);

$receipt = new Receipt();
$receipt->eic_popl = 'CZ00000019';
$receipt->id_jednotky = 181;
$receipt->id_pokl = 'POKLADNA-1';
$receipt->porad_cis = (string) time();
$receipt->dat_trzby = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
$receipt->celk_trzba = 1000;

echo "--- Verification mode ---\n";
echo 'Message can be processed: ', $dispatcher->check($receipt) ? 'yes' : 'no', "\n";

echo "--- Registration ---\n";
try {
    echo 'POK: ', $dispatcher->send($receipt), "\n";
} catch (TransportException $e) {
    // The state of the sale is unknown: send $receipt->forResend() as soon as possible.
    echo 'No response: ', $e->getMessage(), "\n";
} catch (ServerException $e) {
    echo sprintf("Refused with code %d: %s\n", $e->getCode(), $e->getMessage());
}

foreach ($dispatcher->getWarnings() as $code => $message) {
    echo sprintf("Warning %d: %s\n", $code, $message);
}

echo sprintf(
    "Request: %d B, response: %d B, time: %.0f ms, transaction: %s\n",
    $dispatcher->getLastRequestSize(),
    $dispatcher->getLastResponseSize(),
    $dispatcher->getLastResponseTime() * 1000,
    $dispatcher->getLastResponse() === null ? '-' : $dispatcher->getLastResponse()->transactionId
);

echo "--- Critical error ---\n";
$receipt->eic_popl = 'CZ00000018';
try {
    $dispatcher->send($receipt);
} catch (ServerException $e) {
    echo sprintf("Refused with code %d: %s\n", $e->getCode(), $e->getMessage());
}

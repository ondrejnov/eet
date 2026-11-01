<?php

/**
 * Real communication with the EET playground, run only with the environment variable EET_PLAYGROUND=1
 * after downloading the certificates by scripts/fetch-playground-certs.sh.
 */

declare(strict_types=1);

use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Http\CurlTransport;
use Ondrejnov\EET\Receipt;
use Tester\Assert;
use Tester\Environment;

require __DIR__ . '/../bootstrap.php';

if (getenv('EET_PLAYGROUND') !== '1') {
    Environment::skip('Set EET_PLAYGROUND=1 to run tests against the playground.');
}

if (!is_file(DIR_CERT . '/CZ00000019.key')) {
    Environment::skip('Run scripts/fetch-playground-certs.sh first.');
}

$dispatcherFor = static function (string $eic): Dispatcher { return new Dispatcher(
    Dispatcher::PLAYGROUND_URL,
    Certificate::fromPem(DIR_CERT . '/' . $eic . '.key', DIR_CERT . '/' . $eic . '.pem'),
    null,
    new CurlTransport(15000, 10000)
); };

$receiptFor = static function (string $eic): Receipt {
    $receipt = new Receipt();
    $receipt->eic_popl = $eic;
    $receipt->id_jednotky = 181;
    $receipt->id_pokl = 'test/1';
    $receipt->porad_cis = 'T' . bin2hex(random_bytes(8));
    $receipt->dat_trzby = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $receipt->celk_trzba = 1000;

    return $receipt;
};

// All test identities: legal person, taxpayer's own number, natural person.
foreach (['CZ00000019', 'CZ683555118', 'CZ8551015704'] as $eic) {
    $dispatcher = $dispatcherFor($eic);

    $pok = $dispatcher->send($receiptFor($eic));
    Assert::match('~^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}-ff$~', $pok);

    $response = $dispatcher->getLastResponse();
    Assert::true($response->test);
    Assert::same($dispatcher->getLastUuid(), $response->uuid);
    Assert::same([], $response->warnings);
    Assert::true($response->transactionId !== null);

    Assert::true($dispatcher->check($receiptFor($eic)));
    Assert::same(0, $dispatcher->getLastResponse()->errorCode);
}

$eic = 'CZ00000019';
$dispatcher = $dispatcherFor($eic);

// Delegation, a negative amount and prepaid items.
$receipt = $receiptFor($eic);
$receipt->eic_poverujiciho = 'CZ8551015704';
$receipt->povereni_vice_popl = true;
$receipt->celk_trzba = -187.2;
$receipt->urceno_cerp_zuct = 343;
$receipt->cerp_zuct = '237.50';
$dispatcher->send($receipt);
Assert::same([], $dispatcher->getWarnings());

// Another attempt to send the same sale.
$dispatcher->send($receipt->forResend());
Assert::same([], $dispatcher->getWarnings());

// Warning: EIC in the message differs from the certificate.
$dispatcher->send($receiptFor('CZ683555118'));
Assert::same([1], array_keys($dispatcher->getWarnings()));

// Warning: the format of the unit does not match the numbers assigned by the portal.
$receipt = $receiptFor($eic);
$receipt->id_jednotky = 7;
Assert::true($dispatcher->check($receipt));
Assert::same([6], array_keys($dispatcher->getWarnings()));

// Warning: the sale is too old.
$receipt = $receiptFor($eic);
$receipt->dat_trzby = new DateTimeImmutable('2026-06-01 12:00:00', new DateTimeZone('Europe/Prague'));
Assert::true($dispatcher->check($receipt));
Assert::same([5], array_keys($dispatcher->getWarnings()));

// Critical error: EIC with the valid format but with a wrong check digit.
Assert::exception(static function () use ($dispatcher, $receiptFor) { return $dispatcher->send($receiptFor('CZ00000018')); }, ServerException::class, null, 6);
Assert::false($dispatcher->check($receiptFor('CZ00000018')));
Assert::same(6, $dispatcher->getLastResponse()->errorCode);

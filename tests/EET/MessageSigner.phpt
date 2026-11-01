<?php

declare(strict_types=1);

use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Security\Namespaces;
use Ondrejnov\EET\Security\ResponseVerifier;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * Names of elements and attributes without their values, used to compare the message structure.
 *
 * @return list<string>
 */
function skeleton(DOMElement $element, string $path = ''): array
{
    $path .= '/' . $element->localName;
    $attributes = [];
    foreach ($element->attributes as $attribute) {
        $attributes[] = $attribute->localName;
    }
    sort($attributes);

    $lines = [$path . '[' . implode(' ', $attributes) . ']'];
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            array_push($lines, ...skeleton($child, $path));
        }
    }

    return $lines;
}

function soapHeader(string $xml): DOMElement
{
    return loadXml($xml)->getElementsByTagNameNS(Namespaces::SOAP, 'Header')->item(0);
}

$certificate = createTestCertificate();
$dispatcher = new Dispatcher(Dispatcher::PLAYGROUND_URL, $certificate);

$xml = $dispatcher->getSignedMessage(createReceipt());
$document = loadXml($xml);
$xpath = new DOMXPath($document);
$xpath->registerNamespace('ds', Namespaces::DS);
$xpath->registerNamespace('wsse', Namespaces::WSSE);

// The prescribed XML declaration.
Assert::same('<?xml version="1.0" encoding="UTF-8"?>', strtok($xml, "\n"));

// The signature verifies and the attached certificate is the signing one.
$signer = (new ResponseVerifier())->verifySignature($document);
openssl_x509_export($signer, $signerPem);
Assert::same($certificate->getBase64(), preg_replace('/-----[A-Z ]+-----|\s+/', '', $signerPem));

// Only soap:Body is signed and no other headers are present (chapter 5.2).
Assert::same(1, $xpath->query('//ds:Reference')->length);
Assert::same(1, $xpath->query('/*/*[local-name()="Header"]/*')->length);
Assert::same(0, $xpath->query('//*[local-name()="Timestamp"]')->length);
Assert::same(0, $xpath->query('//*[contains(namespace-uri(), "addressing")]')->length);

// The structure of the security header is the same as in the official sample messages.
$official = file_get_contents(DIR_FIXTURES . '/CZ00000019.eet.v4.req.xml');
$normalize = static fn (array $lines): array => array_map(
    static fn (string $line): string => str_replace(' mustUnderstand', '', str_replace('[mustUnderstand', '[', $line)),
    $lines,
);
Assert::same($normalize(skeleton(soapHeader($official))), $normalize(skeleton(soapHeader($xml))));

// The content matches the published XSD, including all optional items and the verification mode.
$receipt = createReceipt();
$receipt->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9125';
$receipt->eic_poverujiciho = 'CZ8551015704';
$receipt->povereni_vice_popl = false;
$receipt->prvni_zaslani = false;
$receipt->celk_trzba = -0.5;
$receipt->urceno_cerp_zuct = 0;
$receipt->cerp_zuct = 99999999.99;

foreach ([$dispatcher->getSignedMessage(createReceipt()), $dispatcher->getSignedMessage($receipt, true)] as $message) {
    $sale = loadXml($message)->getElementsByTagNameNS(Namespaces::EET, 'Trzba')->item(0);
    $standalone = new DOMDocument();
    $standalone->appendChild($standalone->importNode($sale, true));
    Assert::true($standalone->schemaValidate(FILE_XSD), $message);
}

$checked = $dispatcher->getSignedMessage($receipt, true);
Assert::same('b3a09b52-7c87-4014-a496-4c7a53cf9125', requestAttribute($checked, 'Hlavicka', 'uuid_zpravy'));
Assert::same('false', requestAttribute($checked, 'Hlavicka', 'prvni_zaslani'));
Assert::same('true', requestAttribute($checked, 'Hlavicka', 'overeni'));
Assert::same('CZ8551015704', requestAttribute($checked, 'Data', 'eic_poverujiciho'));
Assert::same('false', requestAttribute($checked, 'Data', 'povereni_vice_popl'));
Assert::same('2026-11-01T12:30:00+01:00', requestAttribute($checked, 'Data', 'dat_trzby'));
Assert::same('-0.50', requestAttribute($checked, 'Data', 'celk_trzba'));
Assert::same('0.00', requestAttribute($checked, 'Data', 'urceno_cerp_zuct'));
Assert::same('99999999.99', requestAttribute($checked, 'Data', 'cerp_zuct'));

// Optional items without a value are not present, empty attributes are forbidden.
$plain = $dispatcher->getSignedMessage(createReceipt());
Assert::same('true', requestAttribute($plain, 'Hlavicka', 'prvni_zaslani'));
Assert::same('1000.00', requestAttribute($plain, 'Data', 'celk_trzba'));
foreach (['eic_poverujiciho', 'povereni_vice_popl', 'urceno_cerp_zuct', 'cerp_zuct'] as $item) {
    Assert::null(requestAttribute($plain, 'Data', $item));
}
Assert::null(requestAttribute($plain, 'Hlavicka', 'overeni'));
Assert::notContains('=""', $plain);

// The send time is in the Czech time zone regardless of the configured one.
$sentAt = new DateTimeImmutable(requestAttribute($plain, 'Hlavicka', 'dat_odesl'));
$expectedOffset = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('P');
Assert::same($expectedOffset, $sentAt->format('P'));

// The longest possible message fits into the limit of 12 kB with the real 2048-bit certificate size.
$longest = createReceipt();
$longest->eic_poverujiciho = 'CZ8551015704';
$longest->povereni_vice_popl = true;
$longest->id_pokl = str_repeat('x', 20);
$longest->porad_cis = str_repeat('x', 25);
$longest->celk_trzba = '-99999999.99';
$longest->urceno_cerp_zuct = '-99999999.99';
$longest->cerp_zuct = '-99999999.99';
Assert::true(strlen($dispatcher->getSignedMessage($longest, true)) < 12 * 1024);

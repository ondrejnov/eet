<?php

declare(strict_types=1);

use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Exceptions\ClientException;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$files = createKeyPairFiles('CZ8551015704');

$certificate = Certificate::fromPem($files['key'], $files['cert']);
Assert::same('CZ8551015704', $certificate->getEic());
Assert::true($certificate->getValidTo() > new DateTimeImmutable());
Assert::match('~^MII[A-Za-z0-9+/=]+$~', $certificate->getBase64());

// The private key never appears in a dump.
$dump = print_r($certificate, true);
Assert::contains('CZ8551015704', $dump);
Assert::notContains('PRIVATE', $dump);
Assert::notContains('OpenSSLAsymmetricKey', $dump);

// Passphrase protected key.
$protected = createKeyPairFiles('CZ00000019', 'Test', 'secret');
Assert::same('CZ00000019', Certificate::fromPem($protected['key'], $protected['cert'], 'secret')->getEic());
Assert::exception(static function () use ($protected) { return Certificate::fromPem($protected['key'], $protected['cert'], 'wrong'); }, ClientException::class);

// A key of a different certificate.
$other = createKeyPairFiles();
Assert::exception(static function () use ($other, $files) { return Certificate::fromPem($other['key'], $files['cert']); }, ClientException::class, '~does not belong~');

// Missing files.
Assert::exception(static function () use ($files) { return Certificate::fromPem('/nonexistent', $files['cert']); }, ClientException::class, '~Cannot read file~');
Assert::exception(static function () { return Certificate::fromPkcs12('/nonexistent', 'x'); }, ClientException::class, '~Cannot read file~');

// PKCS#12 with a modern cipher.
$p12File = tempnam(sys_get_temp_dir(), 'eet');
openssl_pkcs12_export_to_file(file_get_contents($files['cert']), $p12File, file_get_contents($files['key']), 'secret');
Assert::same('CZ8551015704', Certificate::fromPkcs12($p12File, 'secret')->getEic());
Assert::exception(static function () use ($p12File) { return Certificate::fromPkcs12($p12File, 'wrong'); }, ClientException::class, '~Cannot read PKCS#12~');
unlink($p12File);

// Secrets are hidden in stack traces where the language supports it.
if (PHP_VERSION_ID >= 80200 && !ini_get('zend.exception_ignore_args')) {
    foreach ([
        Assert::exception(static function () { Certificate::fromPkcs12('/nonexistent', 'secret'); }, ClientException::class),
        Assert::exception(static function () use ($protected) { Certificate::fromPem($protected['key'], '/nonexistent', 'secret'); }, ClientException::class),
    ] as $exception) {
        foreach ($exception->getTrace() as $frame) {
            if (in_array($frame['function'], ['fromPkcs12', 'fromPem'], true)) {
                Assert::notContains('secret', print_r($frame['args'], true));
            }
        }
    }
}

// PKCS#12 published for the playground: either it loads, or the error explains the OpenSSL 3 limitation.
$playground = DIR_CERT . '/CA_EET-Playground-CZ00000019.p12';
if (is_file($playground)) {
    $password = trim(file_get_contents(DIR_CERT . '/password_pokladni_cert_playground.txt'));
    try {
        Assert::same('CZ00000019', Certificate::fromPkcs12($playground, $password)->getEic());
    } catch (ClientException $e) {
        Assert::contains('openssl pkcs12 -legacy', $e->getMessage());
    }
}

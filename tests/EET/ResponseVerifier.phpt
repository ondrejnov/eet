<?php

declare(strict_types=1);

use Ondrejnov\EET\Exceptions\ResponseVerificationException;
use Ondrejnov\EET\Security\Namespaces;
use Ondrejnov\EET\Security\ResponseVerifier;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$verifier = new ResponseVerifier();
$assertRejected = static function (string $xml, string $reason) use ($verifier) {
    $exception = Assert::exception(
        static function () use ($verifier, $xml) { return $verifier->verifySignature(loadXml($xml)); },
        ResponseVerificationException::class
    );
    Assert::contains($reason, $exception->getMessage());
};

// Test vectors: official sample messages and a real response of the playground.
foreach (['CZ00000019', 'CZ683555118', 'CZ8551015704'] as $eic) {
    $verifier->verifySignature(loadXml(file_get_contents(DIR_FIXTURES . '/' . $eic . '.eet.v4.req.xml')));
}

$confirmation = file_get_contents(DIR_FIXTURES . '/playground-confirmation.xml');
$certificate = $verifier->verifySignature(loadXml($confirmation));

// Identity of the signing certificate.
(new ResponseVerifier(null, 'Generální finanční ředitelství'))->verifyCertificate($certificate);
Assert::exception(
    static function () use ($certificate) { return (new ResponseVerifier(null, 'Někdo jiný'))->verifyCertificate($certificate); },
    ResponseVerificationException::class,
    '~unexpected organization~'
);

// A certificate from an unknown authority with the expected organization is refused.
$attacker = createKeyPairFiles('CZ00000019', 'Generální finanční ředitelství');
Assert::exception(
    static function () use ($attacker) { return (new ResponseVerifier(FILE_CA_BUNDLE, 'Generální finanční ředitelství'))
        ->verifyCertificate(openssl_x509_read(file_get_contents($attacker['cert']))); },
    ResponseVerificationException::class,
    '~untrusted authority~'
);

// The real signing certificate of the playground is trusted, until it expires.
if (openssl_x509_parse($certificate)['validTo_time_t'] > time()) {
    ResponseVerifier::forPlayground()->verifyCertificate($certificate);
}

// Only the given bundle is trusted, not the certificate store of the system.
$caKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$caCsr = openssl_csr_new(['commonName' => 'System CA', 'countryName' => 'CZ'], $caKey, ['digest_alg' => 'sha256']);
$ca = openssl_csr_sign($caCsr, null, $caKey, 2, ['digest_alg' => 'sha256']);
$leafKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$leafCsr = openssl_csr_new(['commonName' => 'CZ00000019', 'organizationName' => 'Generální finanční ředitelství', 'countryName' => 'CZ'], $leafKey, ['digest_alg' => 'sha256']);
$leaf = openssl_csr_sign($leafCsr, $ca, $caKey, 2, ['digest_alg' => 'sha256']);

$systemStore = sys_get_temp_dir() . '/eet-system-store-' . getmypid();
mkdir($systemStore);
openssl_x509_export_to_file($ca, $systemStore . '/' . openssl_x509_parse($ca)['hash'] . '.0');
$ownBundle = $systemStore . '/own-bundle.pem';
openssl_x509_export_to_file($ca, $ownBundle);
register_shutdown_function(static function () use ($systemStore) { array_map('unlink', glob($systemStore . '/*')); rmdir($systemStore); });
putenv('SSL_CERT_DIR=' . $systemStore);
putenv('SSL_CERT_FILE=' . $ownBundle);

(new ResponseVerifier($ownBundle, 'Generální finanční ředitelství'))->verifyCertificate($leaf);
Assert::exception(
    static function () use ($leaf) { ResponseVerifier::forPlayground()->verifyCertificate($leaf); },
    ResponseVerificationException::class,
    '~untrusted authority~'
);

// A wrong path must not fall back to the certificate store of the system.
Assert::exception(
    static function () use ($leaf, $systemStore) { (new ResponseVerifier($systemStore . '/missing.pem', 'Generální finanční ředitelství'))->verifyCertificate($leaf); },
    ResponseVerificationException::class,
    '~does not exist~'
);
putenv('SSL_CERT_DIR');
putenv('SSL_CERT_FILE');

// Changed content.
$assertRejected(str_replace('c8e6e4cf-92bf-46ee-80fe-986a62bcad2a-ff', '00000000-92bf-46ee-80fe-986a62bcad2a-ff', $confirmation), 'digest');
$assertRejected(str_replace('test="true"', 'test="false"', $confirmation), 'digest');

// Changed signature value and signed info.
$assertRejected((string) preg_replace('~<SignatureValue>Ki5g~', '<SignatureValue>AAAA', $confirmation), 'signature');
$document = loadXml($confirmation);
$digestValue = $document->getElementsByTagNameNS(Namespaces::DS, 'DigestValue')->item(0);
$body = $document->getElementsByTagNameNS(Namespaces::SOAP, 'Body')->item(0);
$body->getElementsByTagNameNS(Namespaces::EET, 'Potvrzeni')->item(0)->setAttribute('pok', '00000000-92bf-46ee-80fe-986a62bcad2a-ff');
$digestValue->nodeValue = base64_encode(hash('sha256', $body->C14N(true, false), true));
$assertRejected((string) $document->saveXML(), 'signature');

// Unsigned message.
$assertRejected(file_get_contents(DIR_FIXTURES . '/playground-verification.xml'), 'exactly one Signature');

// Weak algorithms.
$assertRejected(str_replace(Namespaces::ALGO_RSA_SHA256, 'http://www.w3.org/2000/09/xmldsig#rsa-sha1', $confirmation), 'Unsupported algorithm');
$assertRejected(str_replace(Namespaces::ALGO_SHA256, 'http://www.w3.org/2000/09/xmldsig#sha1', $confirmation), 'Unsupported algorithm');

// Signature wrapping: the valid signed body hidden in the header and a forged one read by the application.
$document = loadXml($confirmation);
$envelope = $document->documentElement;
$signedBody = $document->getElementsByTagNameNS(Namespaces::SOAP, 'Body')->item(0);
$forged = $signedBody->cloneNode(true);
$forged->removeAttributeNS(Namespaces::WSU, 'Id');
$forged->getElementsByTagNameNS(Namespaces::EET, 'Potvrzeni')->item(0)->setAttribute('pok', '00000000-92bf-46ee-80fe-986a62bcad2a-ff');
$envelope->replaceChild($forged, $signedBody);
$document->getElementsByTagNameNS(Namespaces::SOAP, 'Header')->item(0)->appendChild($signedBody);
$assertRejected((string) $document->saveXML(), 'does not cover soap:Body');

// Signature wrapping: a second body with the same identifier.
$document = loadXml($confirmation);
$envelope = $document->documentElement;
$envelope->appendChild($document->getElementsByTagNameNS(Namespaces::SOAP, 'Body')->item(0)->cloneNode(true));
$assertRejected((string) $document->saveXML(), 'exactly one Body');

// Signature wrapping: a different element carrying the identifier of the body.
$document = loadXml($confirmation);
$signedBody = $document->getElementsByTagNameNS(Namespaces::SOAP, 'Body')->item(0);
$decoy = $document->createElementNS(Namespaces::EET, 'eet:Decoy');
$decoy->setAttributeNS(Namespaces::WSU, 'wsu:Id', $signedBody->getAttributeNS(Namespaces::WSU, 'Id'));
$document->getElementsByTagNameNS(Namespaces::SOAP, 'Header')->item(0)->appendChild($decoy);
$assertRejected((string) $document->saveXML(), 'not unique');

// Second signature.
$document = loadXml($confirmation);
$signature = $document->getElementsByTagNameNS(Namespaces::DS, 'Signature')->item(0);
$signature->parentNode->appendChild($signature->cloneNode(true));
$assertRejected((string) $document->saveXML(), 'exactly one Signature');

// Certificate reference pointing outside of the message.
$assertRejected((string) preg_replace('~<wsse:Reference URI="#SecurityToken~', '<wsse:Reference URI="http://evil/SecurityToken', $confirmation), 'embedded certificate');
$assertRejected((string) preg_replace('~<wsse:Reference URI="#SecurityToken~', '<wsse:Reference URI="#Missing', $confirmation), 'not found');

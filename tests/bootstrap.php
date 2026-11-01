<?php

declare(strict_types=1);

use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Http\HttpResponse;
use Ondrejnov\EET\Http\Transport;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Security\MessageSigner;
use Ondrejnov\EET\Security\Namespaces;

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
date_default_timezone_set('UTC');

const DIR_FIXTURES = __DIR__ . '/fixtures';
const DIR_CERT = __DIR__ . '/../_cert';
const FILE_XSD = __DIR__ . '/../src/Schema/EETXMLSchema.xsd';
const FILE_CA_BUNDLE = __DIR__ . '/../resources/ica-signing-ca.pem';

/**
 * Throw-away self-signed key pair, valid for the current process.
 *
 * @return array{key: string, cert: string} paths to PEM files
 */
function createKeyPairFiles(string $commonName = 'CZ00000019', string $organization = 'Test', ?string $passphrase = null): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => $commonName, 'organizationName' => $organization, 'countryName' => 'CZ'], $key, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 2, ['digest_alg' => 'sha256']);

    openssl_pkey_export($key, $keyPem, $passphrase);
    openssl_x509_export($cert, $certPem);

    $keyFile = tempnam(sys_get_temp_dir(), 'eet');
    $certFile = tempnam(sys_get_temp_dir(), 'eet');
    file_put_contents($keyFile, $keyPem);
    file_put_contents($certFile, $certPem);
    register_shutdown_function(static function () use ($keyFile, $certFile): void {
        @unlink($keyFile);
        @unlink($certFile);
    });

    return ['key' => $keyFile, 'cert' => $certFile];
}

function createTestCertificate(string $commonName = 'CZ00000019', string $organization = 'Test'): Certificate
{
    $files = createKeyPairFiles($commonName, $organization);

    return Certificate::fromPem($files['key'], $files['cert']);
}

function createReceipt(): Receipt
{
    $receipt = new Receipt();
    $receipt->eic_popl = 'CZ00000019';
    $receipt->id_jednotky = 181;
    $receipt->id_pokl = '00/2535/CN58';
    $receipt->porad_cis = '0/2482/IE25';
    $receipt->dat_trzby = new DateTimeImmutable('2026-11-01 12:30:00', new DateTimeZone('Europe/Prague'));
    $receipt->celk_trzba = 1000;

    return $receipt;
}

function loadXml(string $xml): DOMDocument
{
    $document = new DOMDocument();
    $document->loadXML($xml);

    return $document;
}

/**
 * Envelope with the given content of Odpoved, signed by the given certificate when provided.
 */
function createResponseXml(string $content, ?Certificate $signer = null): string
{
    $document = loadXml(sprintf(
        '<soapenv:Envelope xmlns:soapenv="%s" xmlns:v4="%s"><soapenv:Header/><soapenv:Body><v4:Odpoved>%s</v4:Odpoved></soapenv:Body></soapenv:Envelope>',
        Namespaces::SOAP,
        Namespaces::EET,
        $content,
    ));

    if ($signer !== null) {
        (new MessageSigner())->sign($document, $signer);
    }

    return (string) $document->saveXML();
}

/**
 * Transport with a prepared response. The response may depend on the sent message.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{url: string, body: string, action: string}> */
    public array $requests = [];

    /** @param (\Closure(string): HttpResponse)|HttpResponse|\Throwable $reply */
    public function __construct(private \Closure|HttpResponse|\Throwable $reply)
    {
    }

    public function post(string $url, string $body, string $soapAction): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'body' => $body, 'action' => $soapAction];

        if ($this->reply instanceof \Throwable) {
            throw $this->reply;
        }

        return $this->reply instanceof HttpResponse ? $this->reply : ($this->reply)($body);
    }
}

/**
 * Reads an attribute of the header or data element from a request.
 */
function requestAttribute(string $xml, string $element, string $attribute): ?string
{
    $node = loadXml($xml)->getElementsByTagNameNS(Namespaces::EET, $element)->item(0);

    return $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : null;
}

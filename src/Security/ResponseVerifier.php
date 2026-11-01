<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Security;

use Ondrejnov\EET\Exceptions\ResponseVerificationException;

/**
 * Verifies the WS-Security signature of a SOAP message with exactly one signed element, soap:Body.
 *
 * Only the algorithms prescribed by the EET interface are accepted and the signature must point
 * to the same soap:Body the application reads from, which rules out signature wrapping.
 */
final class ResponseVerifier
{
    /** @var string|null */
    private $caFile;

    /** @var string|null */
    private $expectedOrganization;

    /**
     * @param string|null $caFile PEM bundle with the issuers of the signing certificate, NULL skips the trust check
     * @param string|null $expectedOrganization required O of the signing certificate, NULL skips the check
     */
    public function __construct($caFile = null, $expectedOrganization = null)
    {
        $this->caFile = $caFile;
        $this->expectedOrganization = $expectedOrganization;
    }

    /**
     * Verifier for responses of the playground environment.
     */
    public static function forPlayground(): self
    {
        return new self(__DIR__ . '/../../resources/ica-signing-ca.pem', 'Generální finanční ředitelství');
    }

    /**
     * @throws ResponseVerificationException
     */
    public function verify(\DOMDocument $document)
    {
        $certificate = $this->verifySignature($document);
        $this->verifyCertificate($certificate);
    }

    /**
     * @return resource|\OpenSSLCertificate the certificate which created the verified signature
     *
     * @throws ResponseVerificationException
     */
    public function verifySignature(\DOMDocument $document)
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', Namespaces::SOAP);
        $xpath->registerNamespace('wsse', Namespaces::WSSE);
        $xpath->registerNamespace('wsu', Namespaces::WSU);
        $xpath->registerNamespace('ds', Namespaces::DS);
        $xpath->registerNamespace('ec', Namespaces::EXC_C14N);

        $signature = $this->single($xpath, '/s:Envelope/s:Header/wsse:Security/ds:Signature', null, 'Signature');
        $signedInfo = $this->single($xpath, 'ds:SignedInfo', $signature, 'SignedInfo');

        $this->assertAlgorithm($xpath, 'ds:CanonicalizationMethod', $signedInfo, Namespaces::EXC_C14N);
        $this->assertAlgorithm($xpath, 'ds:SignatureMethod', $signedInfo, Namespaces::ALGO_RSA_SHA256);

        $reference = $this->single($xpath, 'ds:Reference', $signedInfo, 'Reference');
        $this->assertAlgorithm($xpath, 'ds:Transforms/ds:Transform', $reference, Namespaces::EXC_C14N);
        $this->assertAlgorithm($xpath, 'ds:DigestMethod', $reference, Namespaces::ALGO_SHA256);

        $body = $this->single($xpath, '/s:Envelope/s:Body', null, 'Body');
        $this->assertReferencesBody($xpath, $reference, $body);

        $digest = hash(
            'sha256',
            $body->C14N(true, false, null, $this->prefixes($xpath, 'ds:Transforms/ds:Transform', $reference)),
            true
        );
        $expectedDigest = base64_decode($this->single($xpath, 'ds:DigestValue', $reference, 'DigestValue')->textContent, true);
        if (!is_string($expectedDigest) || !hash_equals($expectedDigest, $digest)) {
            throw new ResponseVerificationException('The digest of soap:Body does not match the signature.');
        }

        $certificate = $this->readCertificate($xpath, $signature);

        $signatureValue = base64_decode($this->single($xpath, 'ds:SignatureValue', $signature, 'SignatureValue')->textContent, true);
        $signedData = $signedInfo->C14N(true, false, null, $this->prefixes($xpath, 'ds:CanonicalizationMethod', $signedInfo));
        if (!is_string($signatureValue) || openssl_verify($signedData, $signatureValue, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw new ResponseVerificationException('The signature of the message is not valid.');
        }

        return $certificate;
    }

    /**
     * @param resource|\OpenSSLCertificate $certificate
     *
     * @throws ResponseVerificationException
     */
    public function verifyCertificate($certificate)
    {
        if ($this->caFile !== null) {
            // PHP adds the system CA file or directory when the list contains no file or no directory.
            // A missing file and the directory below, which holds no hashed CA links, prevent that.
            if (!is_file($this->caFile)) {
                throw new ResponseVerificationException(sprintf('The CA file %s does not exist.', $this->caFile));
            }

            if (openssl_x509_checkpurpose($certificate, X509_PURPOSE_ANY, [$this->caFile, __DIR__ . '/../../resources']) !== true) {
                throw new ResponseVerificationException('The signing certificate is expired or issued by an untrusted authority.');
            }
        }

        if ($this->expectedOrganization !== null) {
            $organization = openssl_x509_parse($certificate)['subject']['O'] ?? null;
            if ($organization !== $this->expectedOrganization) {
                throw new ResponseVerificationException(
                    sprintf('The signing certificate belongs to an unexpected organization: %s', var_export($organization, true))
                );
            }
        }
    }

    private function assertReferencesBody(\DOMXPath $xpath, \DOMElement $reference, \DOMElement $body)
    {
        $bodyId = $body->getAttributeNS(Namespaces::WSU, 'Id');
        if (preg_match('/^[\w.-]+$/D', $bodyId) !== 1 || $reference->getAttribute('URI') !== '#' . $bodyId) {
            throw new ResponseVerificationException('The signature does not cover soap:Body.');
        }

        if ($xpath->query(sprintf('//@wsu:Id[. = "%s"] | //@Id[. = "%s"]', $bodyId, $bodyId))->length !== 1) {
            throw new ResponseVerificationException('The identifier of soap:Body is not unique.');
        }
    }

    /**
     * @return resource|\OpenSSLCertificate
     */
    private function readCertificate(\DOMXPath $xpath, \DOMElement $signature)
    {
        $tokenReference = $this->single($xpath, 'ds:KeyInfo/wsse:SecurityTokenReference/wsse:Reference', $signature, 'SecurityTokenReference');
        $uri = $tokenReference->getAttribute('URI');
        if (strpos($uri, '#') !== 0) {
            throw new ResponseVerificationException('The signature does not reference an embedded certificate.');
        }

        $tokens = $xpath->query('/s:Envelope/s:Header/wsse:Security/wsse:BinarySecurityToken');
        foreach ($tokens as $token) {
            if ($token->getAttributeNS(Namespaces::WSU, 'Id') === substr($uri, 1)) {
                $pem = "-----BEGIN CERTIFICATE-----\n"
                    . chunk_split((string) preg_replace('/\s+/', '', $token->textContent), 64, "\n")
                    . "-----END CERTIFICATE-----\n";

                $certificate = openssl_x509_read($pem);
                if ($certificate === false) {
                    throw new ResponseVerificationException('The embedded certificate cannot be read.');
                }

                return $certificate;
            }
        }

        throw new ResponseVerificationException('The certificate referenced by the signature was not found.');
    }

    private function assertAlgorithm(\DOMXPath $xpath, string $query, \DOMElement $context, string $expected)
    {
        $element = $this->single($xpath, $query, $context, $query);
        if ($element->getAttribute('Algorithm') !== $expected) {
            throw new ResponseVerificationException(sprintf('Unsupported algorithm in %s: %s', $query, $element->getAttribute('Algorithm')));
        }
    }

    /**
     * @return list<string>|null
     */
    private function prefixes(\DOMXPath $xpath, string $query, \DOMElement $context)
    {
        $list = $xpath->query($query . '/ec:InclusiveNamespaces/@PrefixList', $context)->item(0);
        if ($list === null) {
            return null;
        }

        return preg_split('/\s+/', trim($list->nodeValue), -1, PREG_SPLIT_NO_EMPTY) ?: null;
    }

    /**
     * @param \DOMElement|null $context
     */
    private function single(\DOMXPath $xpath, string $query, $context, string $name): \DOMElement
    {
        $nodes = $xpath->query($query, $context);
        if ($nodes === false || $nodes->length !== 1 || !$nodes->item(0) instanceof \DOMElement) {
            throw new ResponseVerificationException(sprintf('The message must contain exactly one %s.', $name));
        }

        return $nodes->item(0);
    }
}

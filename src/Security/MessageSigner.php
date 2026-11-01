<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Security;

use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Exceptions\ClientException;

/**
 * Adds the WS-Security signature required by chapter 5.2 of the interface description:
 * only soap:Body is signed (exclusive C14N, SHA-256, RSA-SHA256), the certificate is attached
 * as wsse:BinarySecurityToken and no other headers (Timestamp, WS-Addressing) are present.
 *
 * The structure follows the sample messages published with the specification.
 */
final class MessageSigner
{
    private const SIGNED_INFO_PREFIXES = ['soapenv', 'v4'];
    private const BODY_PREFIXES = ['v4'];

    public function sign(\DOMDocument $document, Certificate $certificate): void
    {
        $envelope = $document->documentElement;
        $header = $envelope->getElementsByTagNameNS(Namespaces::SOAP, 'Header')->item(0);
        $body = $envelope->getElementsByTagNameNS(Namespaces::SOAP, 'Body')->item(0);

        $suffix = strtoupper(bin2hex(random_bytes(12)));
        $tokenId = 'X509-' . $suffix;
        $bodyId = 'id-' . $suffix;

        $security = $document->createElementNS(Namespaces::WSSE, 'wsse:Security');
        $security->setAttributeNS(Namespaces::XMLNS, 'xmlns:wsu', Namespaces::WSU);
        $security->setAttributeNS(Namespaces::SOAP, 'soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $token = $document->createElementNS(Namespaces::WSSE, 'wsse:BinarySecurityToken', $certificate->getBase64());
        $token->setAttribute('EncodingType', Namespaces::TOKEN_ENCODING);
        $token->setAttribute('ValueType', Namespaces::TOKEN_TYPE);
        $token->setAttributeNS(Namespaces::WSU, 'wsu:Id', $tokenId);
        $security->appendChild($token);

        $signature = $this->ds($document, 'Signature');
        $signature->setAttribute('Id', 'SIG-' . $suffix);
        $security->appendChild($signature);

        $signedInfo = $this->ds($document, 'SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalization = $this->ds($document, 'CanonicalizationMethod');
        $canonicalization->setAttribute('Algorithm', Namespaces::EXC_C14N);
        $canonicalization->appendChild($this->inclusiveNamespaces($document, self::SIGNED_INFO_PREFIXES));
        $signedInfo->appendChild($canonicalization);

        $signatureMethod = $this->ds($document, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', Namespaces::ALGO_RSA_SHA256);
        $signedInfo->appendChild($signatureMethod);

        $reference = $this->ds($document, 'Reference');
        $reference->setAttribute('URI', '#' . $bodyId);
        $signedInfo->appendChild($reference);

        $transforms = $this->ds($document, 'Transforms');
        $reference->appendChild($transforms);

        $transform = $this->ds($document, 'Transform');
        $transform->setAttribute('Algorithm', Namespaces::EXC_C14N);
        $transform->appendChild($this->inclusiveNamespaces($document, self::BODY_PREFIXES));
        $transforms->appendChild($transform);

        $digestMethod = $this->ds($document, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', Namespaces::ALGO_SHA256);
        $reference->appendChild($digestMethod);

        $body->setAttributeNS(Namespaces::WSU, 'wsu:Id', $bodyId);
        $digest = base64_encode(hash('sha256', $body->C14N(true, false, null, self::BODY_PREFIXES), true));
        $reference->appendChild($this->ds($document, 'DigestValue', $digest));

        $signedData = $signedInfo->C14N(true, false, null, self::SIGNED_INFO_PREFIXES);
        if (!openssl_sign($signedData, $signatureValue, $certificate->getPrivateKey(), OPENSSL_ALGO_SHA256)) {
            throw new ClientException('Cannot sign the message: ' . (openssl_error_string() ?: 'unknown error'));
        }
        $signature->appendChild($this->ds($document, 'SignatureValue', base64_encode($signatureValue)));

        $keyInfo = $this->ds($document, 'KeyInfo');
        $keyInfo->setAttribute('Id', 'KI-' . $suffix);
        $signature->appendChild($keyInfo);

        $tokenReference = $document->createElementNS(Namespaces::WSSE, 'wsse:SecurityTokenReference');
        $tokenReference->setAttributeNS(Namespaces::WSU, 'wsu:Id', 'STR-' . $suffix);
        $keyInfo->appendChild($tokenReference);

        $tokenLink = $document->createElementNS(Namespaces::WSSE, 'wsse:Reference');
        $tokenLink->setAttribute('URI', '#' . $tokenId);
        $tokenLink->setAttribute('ValueType', Namespaces::TOKEN_TYPE);
        $tokenReference->appendChild($tokenLink);
    }

    private function ds(\DOMDocument $document, string $name, ?string $value = null): \DOMElement
    {
        return $value === null
            ? $document->createElementNS(Namespaces::DS, 'ds:' . $name)
            : $document->createElementNS(Namespaces::DS, 'ds:' . $name, $value);
    }

    /**
     * @param list<string> $prefixes
     */
    private function inclusiveNamespaces(\DOMDocument $document, array $prefixes): \DOMElement
    {
        $element = $document->createElementNS(Namespaces::EXC_C14N, 'ec:InclusiveNamespaces');
        $element->setAttribute('PrefixList', implode(' ', $prefixes));

        return $element;
    }
}

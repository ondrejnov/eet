<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Security;

/**
 * XML namespaces and algorithm identifiers of the signed EET messages.
 */
final class Namespaces
{
    public const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const EET = 'http://fs.gov.cz/eet/schema/v4';
    public const WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    public const WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    public const DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    public const XMLNS = 'http://www.w3.org/2000/xmlns/';

    public const ALGO_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    public const ALGO_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    public const TOKEN_ENCODING = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    public const TOKEN_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';

    private function __construct()
    {
    }
}

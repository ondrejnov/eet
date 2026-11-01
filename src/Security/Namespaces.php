<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Security;

/**
 * XML namespaces and algorithm identifiers of the signed EET messages.
 */
final class Namespaces
{
    const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const EET = 'http://fs.gov.cz/eet/schema/v4';
    const WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    const WSU = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    const DS = 'http://www.w3.org/2000/09/xmldsig#';
    const EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    const XMLNS = 'http://www.w3.org/2000/xmlns/';

    const ALGO_RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    const ALGO_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';

    const TOKEN_ENCODING = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    const TOKEN_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';

    private function __construct()
    {
    }
}

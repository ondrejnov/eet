<?php

declare(strict_types=1);

namespace Ondrejnov\EET;

use Ondrejnov\EET\Exceptions\ResponseVerificationException;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Exceptions\TransportException;
use Ondrejnov\EET\Http\CurlTransport;
use Ondrejnov\EET\Http\HttpResponse;
use Ondrejnov\EET\Http\Transport;
use Ondrejnov\EET\Security\MessageSigner;
use Ondrejnov\EET\Security\Namespaces;
use Ondrejnov\EET\Security\ResponseVerifier;
use Ondrejnov\EET\Utils\Format;
use Ondrejnov\EET\Utils\UUID;

/**
 * Sends registered sales to the EET 2.0 service.
 */
class Dispatcher
{
    public const PLAYGROUND_URL = 'https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4';

    private const SOAP_ACTION = 'http://fs.gov.cz/eet/OdeslaniTrzby';
    private const LOCAL_TIMEZONE = 'Europe/Prague';
    private const POK_MASK = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}-[0-9a-fA-F]{2}$/D';

    private const ERROR_MESSAGES = [
        -1 => 'Dočasná technická chyba zpracování – odešlete prosím datovou zprávu později.',
        2 => 'Kódování XML není platné.',
        3 => 'XML zpráva nevyhověla kontrole XML schématu.',
        4 => 'Neplatný podpis SOAP zprávy.',
        6 => 'EIČ poplatníka má chybnou strukturu.',
        7 => 'Datová zpráva je příliš velká.',
        8 => 'Datová zpráva nebyla zpracována kvůli technické chybě nebo chybě dat.',
    ];

    private Transport $transport;

    private MessageSigner $signer;

    private ?string $lastRequest = null;

    private ?string $lastResponseBody = null;

    private ?Response $lastResponse = null;

    private ?string $lastUuid = null;

    private ?float $lastResponseTime = null;

    /**
     * @param string $service URL of the service, e.g. Dispatcher::PLAYGROUND_URL
     * @param Certificate $certificate cash register certificate used to sign messages
     * @param ResponseVerifier|null $verifier verifier of signed confirmations, ResponseVerifier::forPlayground() by default
     * @param Transport|null $transport HTTP client, CurlTransport by default
     */
    public function __construct(
        private string $service,
        private Certificate $certificate,
        private ?ResponseVerifier $verifier = null,
        ?Transport $transport = null,
    ) {
        $this->verifier ??= ResponseVerifier::forPlayground();
        $this->transport = $transport ?? new CurlTransport();
        $this->signer = new MessageSigner();
    }

    /**
     * Registers the sale.
     *
     * @return string POK, the confirmation code
     *
     * @throws Exceptions\ValidationException the receipt is invalid, nothing was sent
     * @throws TransportException no valid response was received, send Receipt::forResend() later
     * @throws ServerException the server rejected the message, see ServerException::isRetryable()
     * @throws ResponseVerificationException the response cannot be trusted
     */
    public function send(Receipt $receipt): string
    {
        $response = $this->process($receipt, false);

        if (!$response->isConfirmed()) {
            throw new ServerException('The response does not contain a confirmation code.', (int) $response->errorCode, $response);
        }

        return (string) $response->pok;
    }

    /**
     * Sends the sale in the verification mode (overeni="true"): the sale is not registered and
     * no POK is issued. Details of a refused message are available through getLastResponse().
     *
     * @throws Exceptions\ValidationException the receipt is invalid, nothing was sent
     * @throws TransportException no valid response was received
     * @throws ResponseVerificationException the response cannot be trusted
     */
    public function check(Receipt $receipt): bool
    {
        try {
            return $this->process($receipt, true)->isVerified();
        } catch (ResponseVerificationException $e) {
            throw $e;
        } catch (ServerException) {
            return false;
        }
    }

    /**
     * Returns the signed SOAP message for the sale without sending it.
     */
    public function getSignedMessage(Receipt $receipt, bool $check = false): string
    {
        $receipt->validate();

        return $this->buildMessage($receipt, $receipt->uuid_zpravy ?? UUID::v4(), $check);
    }

    /**
     * @return array<int, string> warnings of the last response [warning code => message]
     */
    public function getWarnings(): array
    {
        return $this->lastResponse?->warnings ?? [];
    }

    public function getLastResponse(): ?Response
    {
        return $this->lastResponse;
    }

    /**
     * @return string|null signed SOAP message sent by the last call
     */
    public function getLastRequest(): ?string
    {
        return $this->lastRequest;
    }

    /**
     * @return string|null UUID of the message sent by the last call
     */
    public function getLastUuid(): ?string
    {
        return $this->lastUuid;
    }

    /**
     * @return string|null raw body of the last HTTP response
     */
    public function getLastResponseBody(): ?string
    {
        return $this->lastResponseBody;
    }

    /**
     * @return float|null duration of the last HTTP call in seconds
     */
    public function getLastResponseTime(): ?float
    {
        return $this->lastResponseTime;
    }

    public function getLastRequestSize(): int
    {
        return strlen((string) $this->lastRequest);
    }

    public function getLastResponseSize(): int
    {
        return strlen((string) $this->lastResponseBody);
    }

    private function process(Receipt $receipt, bool $check): Response
    {
        $receipt->validate();

        $this->lastUuid = $receipt->uuid_zpravy ?? UUID::v4();
        $this->lastRequest = $this->buildMessage($receipt, $this->lastUuid, $check);
        $this->lastResponse = null;
        $this->lastResponseBody = null;
        $this->lastResponseTime = null;

        $start = microtime(true);
        $http = $this->transport->post($this->service, $this->lastRequest, self::SOAP_ACTION);
        $this->lastResponseTime = microtime(true) - $start;
        $this->lastResponseBody = $http->body;

        $response = $this->parseResponse($http);
        $this->lastResponse = $response;

        if ($response->errorCode !== null && $response->errorCode !== 0) {
            throw new ServerException(
                $response->errorMessage ?: (self::ERROR_MESSAGES[$response->errorCode] ?? 'Unknown EET error.'),
                $response->errorCode,
                $response,
            );
        }

        return $response;
    }

    private function buildMessage(Receipt $receipt, string $uuid, bool $check): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');

        $envelope = $document->createElementNS(Namespaces::SOAP, 'soapenv:Envelope');
        $envelope->setAttributeNS(Namespaces::XMLNS, 'xmlns:v4', Namespaces::EET);
        $document->appendChild($envelope);
        $envelope->appendChild($document->createElementNS(Namespaces::SOAP, 'soapenv:Header'));

        $body = $document->createElementNS(Namespaces::SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        $sale = $document->createElementNS(Namespaces::EET, 'v4:Trzba');
        $body->appendChild($sale);

        $sale->appendChild($this->createElement($document, 'v4:Hlavicka', [
            'uuid_zpravy' => $uuid,
            'dat_odesl' => Format::dateTime(new \DateTimeImmutable('now', new \DateTimeZone(self::LOCAL_TIMEZONE))),
            'prvni_zaslani' => Format::boolean($receipt->prvni_zaslani),
            'overeni' => $check ? 'true' : null,
        ]));

        $sale->appendChild($this->createElement($document, 'v4:Data', [
            'eic_popl' => $receipt->eic_popl,
            'eic_poverujiciho' => $receipt->eic_poverujiciho,
            'povereni_vice_popl' => $receipt->povereni_vice_popl === null ? null : Format::boolean($receipt->povereni_vice_popl),
            'id_jednotky' => (string) $receipt->id_jednotky,
            'id_pokl' => (string) $receipt->id_pokl,
            'porad_cis' => (string) $receipt->porad_cis,
            'dat_trzby' => Format::dateTime($receipt->dat_trzby),
            'celk_trzba' => Format::price($receipt->celk_trzba),
            'urceno_cerp_zuct' => Format::price($receipt->urceno_cerp_zuct),
            'cerp_zuct' => Format::price($receipt->cerp_zuct),
        ]));

        $this->signer->sign($document, $this->certificate);

        return (string) $document->saveXML();
    }

    /**
     * @param array<string, string|null> $attributes items with an empty value are not allowed in the message
     */
    private function createElement(\DOMDocument $document, string $name, array $attributes): \DOMElement
    {
        $element = $document->createElementNS(Namespaces::EET, $name);
        foreach ($attributes as $attribute => $value) {
            if ($value !== null) {
                $element->setAttribute($attribute, $value);
            }
        }

        return $element;
    }

    private function parseResponse(HttpResponse $http): Response
    {
        $document = $this->loadXml($http);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', Namespaces::SOAP);
        $xpath->registerNamespace('eet', Namespaces::EET);

        $fault = $xpath->query('/s:Envelope/s:Body/s:Fault')->item(0);
        if ($fault !== null) {
            $message = trim($xpath->evaluate('string(faultstring)', $fault));
            throw new ServerException('SOAP fault: ' . $message, 0);
        }

        $answer = $xpath->query('/s:Envelope/s:Body/eet:Odpoved')->item(0);
        if (!$answer instanceof \DOMElement) {
            throw new TransportException(sprintf('The response with HTTP status %d is not an EET message.', $http->status));
        }

        $header = $xpath->query('eet:Hlavicka', $answer)->item(0);
        $confirmation = $xpath->query('eet:Potvrzeni', $answer)->item(0);
        $error = $xpath->query('eet:Chyba', $answer)->item(0);

        $warnings = [];
        foreach ($xpath->query('eet:Varovani', $answer) as $warning) {
            $warnings[(int) $warning->getAttribute('kod_varov')] = trim($warning->textContent);
        }

        $response = new Response(
            uuid: $this->attribute($header, 'uuid_zpravy'),
            receivedAt: $this->attribute($header, 'dat_prij'),
            rejectedAt: $this->attribute($header, 'dat_odmit'),
            pok: $this->attribute($confirmation, 'pok'),
            errorCode: $error === null ? null : (int) $error->getAttribute('kod'),
            errorMessage: $error === null ? null : trim($error->textContent),
            test: in_array($this->attribute($confirmation ?? $error, 'test'), ['true', '1'], true),
            warnings: $warnings,
            transactionId: $http->transactionId,
        );

        if ($response->isConfirmed()) {
            $this->verifyConfirmation($document, $response);
        }

        return $response;
    }

    private function verifyConfirmation(\DOMDocument $document, Response $response): void
    {
        try {
            $this->verifier->verify($document);
        } catch (ResponseVerificationException $e) {
            throw $e->withResponse($response);
        }

        if ($response->uuid !== $this->lastUuid) {
            throw new ResponseVerificationException('The confirmation belongs to a different message.', $response);
        }

        if (preg_match(self::POK_MASK, (string) $response->pok) !== 1) {
            throw new ResponseVerificationException('The confirmation code has an invalid format.', $response);
        }
    }

    private function loadXml(HttpResponse $http): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $http->body !== '' && $document->loadXML($http->body, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->doctype !== null) {
            throw new TransportException(sprintf('The response with HTTP status %d is not a valid XML message.', $http->status));
        }

        return $document;
    }

    private function attribute(?\DOMNode $element, string $name): ?string
    {
        return $element instanceof \DOMElement && $element->hasAttribute($name)
            ? $element->getAttribute($name)
            : null;
    }
}

<?php

declare(strict_types=1);

use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Exceptions\ResponseVerificationException;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Exceptions\TransportException;
use Ondrejnov\EET\Exceptions\ValidationException;
use Ondrejnov\EET\Http\HttpResponse;
use Ondrejnov\EET\Security\Namespaces;
use Ondrejnov\EET\Security\ResponseVerifier;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

const POK = '987a6be5-6af5-44f3-b4fc-987654321000-ff';

$certificate = createTestCertificate();
$serverCertificate = createTestCertificate('CZ72080043', 'Generální finanční ředitelství');
$verifier = new ResponseVerifier(null, 'Generální finanční ředitelství');

$dispatcherFor = static function ($reply) use ($certificate, $verifier, &$transport): Dispatcher {
    $transport = new FakeTransport($reply);

    return new Dispatcher(Dispatcher::PLAYGROUND_URL, $certificate, $verifier, $transport);
};

/** Signed confirmation of the message in the request, as the server would create it. */
$confirm = static function (string $extra = '', string $pok = POK, ?string $uuid = null, bool $sign = true) use ($serverCertificate): Closure {
    return static function (string $request) use ($extra, $pok, $uuid, $sign, $serverCertificate): HttpResponse {
        $content = sprintf(
            '<v4:Hlavicka uuid_zpravy="%s" dat_prij="2027-03-04T18:25:21+01:00"/><v4:Potvrzeni pok="%s" test="true"/>%s',
            $uuid ?? requestAttribute($request, 'Hlavicka', 'uuid_zpravy'),
            $pok,
            $extra,
        );

        return new HttpResponse(200, createResponseXml($content, $sign ? $serverCertificate : null), 'trans-1');
    };
};

$reject = static function (int $code, string $text = 'Popis chyby', string $extra = ''): Closure {
    return static fn (string $request): HttpResponse => new HttpResponse(200, createResponseXml(sprintf(
        '<v4:Hlavicka uuid_zpravy="%s" dat_odmit="2027-03-04T18:25:21+01:00"/><v4:Chyba kod="%d" test="true">%s</v4:Chyba>%s',
        requestAttribute($request, 'Hlavicka', 'uuid_zpravy'),
        $code,
        $text,
        $extra,
    )), 'trans-2');
};

// Successful registration.
$dispatcher = $dispatcherFor($confirm());
Assert::same(POK, $dispatcher->send(createReceipt()));
Assert::same([], $dispatcher->getWarnings());

$response = $dispatcher->getLastResponse();
Assert::true($response->isConfirmed());
Assert::false($response->isVerified());
Assert::true($response->test);
Assert::same(POK, $response->pok);
Assert::same('2027-03-04T18:25:21+01:00', $response->receivedAt);
Assert::same('trans-1', $response->transactionId);
Assert::same($dispatcher->getLastUuid(), $response->uuid);

Assert::same(Dispatcher::PLAYGROUND_URL, $transport->requests[0]['url']);
Assert::same('http://fs.gov.cz/eet/OdeslaniTrzby', $transport->requests[0]['action']);
Assert::same($transport->requests[0]['body'], $dispatcher->getLastRequest());
Assert::same(strlen($transport->requests[0]['body']), $dispatcher->getLastRequestSize());
Assert::true($dispatcher->getLastResponseSize() > 0);
Assert::type('float', $dispatcher->getLastResponseTime());

// Warnings of the permeable checks do not stop the registration.
$dispatcher = $dispatcherFor($confirm(
    '<v4:Varovani kod_varov="1">  EIC poplatnika v datove zprave se neshoduje s EIC v certifikatu  </v4:Varovani>'
    . '<v4:Varovani kod_varov="6">id_jednotky neodpovida formatem pridelenemu c. evidencni jednotky</v4:Varovani>',
));
Assert::same(POK, $dispatcher->send(createReceipt()));
Assert::same([
    1 => 'EIC poplatnika v datove zprave se neshoduje s EIC v certifikatu',
    6 => 'id_jednotky neodpovida formatem pridelenemu c. evidencni jednotky',
], $dispatcher->getWarnings());

// Every message gets a new UUID, an explicit one is respected.
$dispatcher = $dispatcherFor($confirm());
$receipt = createReceipt();
$dispatcher->send($receipt);
$first = $dispatcher->getLastUuid();
$dispatcher->send($receipt);
Assert::notSame($first, $dispatcher->getLastUuid());
Assert::null($receipt->uuid_zpravy);

$receipt->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9125';
$dispatcher->send($receipt);
Assert::same('b3a09b52-7c87-4014-a496-4c7a53cf9125', $dispatcher->getLastUuid());

// Resending: new UUID, flag of the repeated sending, the sale data untouched.
$dispatcher = $dispatcherFor($confirm());
$receipt = createReceipt();
$dispatcher->send($receipt);
$dispatcher->send($receipt->forResend());
[$firstRequest, $secondRequest] = [$transport->requests[0]['body'], $transport->requests[1]['body']];
Assert::same('true', requestAttribute($firstRequest, 'Hlavicka', 'prvni_zaslani'));
Assert::same('false', requestAttribute($secondRequest, 'Hlavicka', 'prvni_zaslani'));
Assert::notSame(requestAttribute($firstRequest, 'Hlavicka', 'uuid_zpravy'), requestAttribute($secondRequest, 'Hlavicka', 'uuid_zpravy'));
foreach (['eic_popl', 'id_jednotky', 'id_pokl', 'porad_cis', 'dat_trzby', 'celk_trzba'] as $item) {
    Assert::same(requestAttribute($firstRequest, 'Data', $item), requestAttribute($secondRequest, 'Data', $item));
}

// An invalid receipt is not sent at all.
$dispatcher = $dispatcherFor($confirm());
$receipt = createReceipt();
$receipt->eic_popl = 'x';
Assert::exception(static fn () => $dispatcher->send($receipt), ValidationException::class);
Assert::exception(static fn () => $dispatcher->check($receipt), ValidationException::class);
Assert::count(0, $transport->requests);

// Critical errors.
$dispatcher = $dispatcherFor($reject(4, 'Neplatny podpis SOAP zpravy'));
$exception = Assert::exception(static fn () => $dispatcher->send(createReceipt()), ServerException::class, 'Neplatny podpis SOAP zpravy', 4);
Assert::false($exception->isRetryable());
Assert::same(4, $exception->getResponse()->errorCode);
Assert::same('trans-2', $exception->getResponse()->transactionId);

foreach ([-1, 8] as $code) {
    $dispatcher = $dispatcherFor($reject($code));
    Assert::true(Assert::exception(static fn () => $dispatcher->send(createReceipt()), ServerException::class)->isRetryable());
}

// A missing text is replaced by a description of the known code.
$dispatcher = $dispatcherFor($reject(6, ''));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), ServerException::class, 'EIČ poplatníka má chybnou strukturu.', 6);

// Verification mode.
$dispatcher = $dispatcherFor($reject(0, 'Datovou zpravu evidovane trzby v overovacim modu se podarilo zpracovat', '<v4:Varovani kod_varov="5">Datum</v4:Varovani>'));
Assert::true($dispatcher->check(createReceipt()));
Assert::same('true', requestAttribute($transport->requests[0]['body'], 'Hlavicka', 'overeni'));
Assert::true($dispatcher->getLastResponse()->isVerified());
Assert::same([5 => 'Datum'], $dispatcher->getWarnings());

$dispatcher = $dispatcherFor($reject(6, 'EIC poplatnika ma chybnou strukturu'));
Assert::false($dispatcher->check(createReceipt()));
Assert::same(6, $dispatcher->getLastResponse()->errorCode);

// The response of the verification mode is not a registration.
$dispatcher = $dispatcherFor($reject(0));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), ServerException::class, '~confirmation code~');

// Confirmations that cannot be trusted keep the data for diagnostics but are refused.
$dispatcher = $dispatcherFor($confirm('', POK, null, false));
$exception = Assert::exception(static fn () => $dispatcher->send(createReceipt()), ResponseVerificationException::class, '~exactly one Signature~');
Assert::same(POK, $exception->getResponse()->pok);

$dispatcher = $dispatcherFor($confirm('', POK, '123e4567-e89b-42d3-a456-426655440000'));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), ResponseVerificationException::class, '~different message~');

$dispatcher = $dispatcherFor($confirm('', 'not-a-pok'));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), ResponseVerificationException::class, '~invalid format~');

$impostor = new Dispatcher(
    Dispatcher::PLAYGROUND_URL,
    $certificate,
    new ResponseVerifier(null, 'Generální finanční ředitelství'),
    new FakeTransport(static function (string $request): HttpResponse {
        return new HttpResponse(200, createResponseXml(sprintf(
            '<v4:Hlavicka uuid_zpravy="%s" dat_prij="2027-03-04T18:25:21+01:00"/><v4:Potvrzeni pok="%s"/>',
            requestAttribute($request, 'Hlavicka', 'uuid_zpravy'),
            POK,
        ), createTestCertificate('CZ72080043', 'Utocnik')));
    }),
);
Assert::exception(static fn () => $impostor->send(createReceipt()), ResponseVerificationException::class, '~unexpected organization~');

// A response of the verification mode cannot hide a trust failure.
Assert::exception(
    static fn () => (new Dispatcher(Dispatcher::PLAYGROUND_URL, $certificate, $verifier, new FakeTransport($confirm('', POK, null, false))))->check(createReceipt()),
    ResponseVerificationException::class,
);

// SOAP fault.
$fault = sprintf(
    '<soapenv:Envelope xmlns:soapenv="%s"><soapenv:Body><soapenv:Fault><faultcode>soapenv:Client</faultcode><faultstring>Bad message</faultstring></soapenv:Fault></soapenv:Body></soapenv:Envelope>',
    Namespaces::SOAP,
);
$dispatcher = $dispatcherFor(new HttpResponse(500, $fault));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), ServerException::class, 'SOAP fault: Bad message');

function createResponseXmlWithoutDeclaration(string $content): string
{
    return sprintf(
        '<soapenv:Envelope xmlns:soapenv="%s" xmlns:v4="%s"><soapenv:Body><v4:Odpoved>%s</v4:Odpoved></soapenv:Body></soapenv:Envelope>',
        Namespaces::SOAP,
        Namespaces::EET,
        $content,
    );
}

// Situations in which the state of the sale is unknown and it has to be sent again.
$unusable = [
    'empty body, the server may ignore suspicious messages' => new HttpResponse(200, ''),
    'error page' => new HttpResponse(502, '<html><body>Bad gateway<br></body>'),
    'other XML' => new HttpResponse(200, '<foo/>'),
    'document type declaration' => new HttpResponse(200, '<!DOCTYPE foo [<!ENTITY x "y">]>' . createResponseXmlWithoutDeclaration('<v4:Chyba kod="4">&x;</v4:Chyba>')),
];
foreach ($unusable as $description => $reply) {
    $dispatcher = $dispatcherFor($reply);
    Assert::exception(static fn () => $dispatcher->send(createReceipt()), TransportException::class, null, null, $description);
    Assert::null($dispatcher->getLastResponse());
}

$dispatcher = $dispatcherFor(new TransportException('Operation timed out', 28));
Assert::exception(static fn () => $dispatcher->send(createReceipt()), TransportException::class, 'Operation timed out', 28);
Assert::exception(static fn () => $dispatcher->check(createReceipt()), TransportException::class);

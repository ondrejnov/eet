<?php

require_once __DIR__ . "/../bootstrap.php";

use Ondrejnov\EET\Exceptions\CertificateValidityException;
use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\PKCS12CertificateValidator;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;

$dispatcher = new Dispatcher(PLAYGROUND_WSDL, DIR_CERT . '/eet.key', DIR_CERT . '/eet.pem');
$dispatcher->trace = TRUE;

// Example receipt
$r = new Receipt();
$r->uuid_zpravy = UUID::v4();
$r->dic_popl = 'CZ00000019';
$r->id_provoz = '181';
$r->id_pokl = '1';
$r->porad_cis = '1';
$r->dat_trzby = new \DateTime();
$r->celk_trzba = 1000;


echo '<h2>---VALIDATE PKCS12---</h2>';
try {
    $validator = new PKCS12CertificateValidator(DIR_CERT . '/EET_CA1_Playground-CZ00000019.p12', 'eet');

    echo sprintf('<b>Request validity: %s</b><br />', $validator->validate(PLAYGROUND_WSDL, $r, true)); // See response - should be returned
} catch (ServerException $e) {
    var_dump($e); // See exception
} catch (CertificateValidityException $e){
    var_dump($e); // Fatal error
} catch (\Exception $e) {
    var_dump($e); // Fatal error
}


// Valid response should be returned
echo '<h2>---VALID REQUEST---</h2>';
try {
    $fik = $dispatcher->send($r); // Send request

    echo sprintf('<b>Returned FIK code: %s</b><br />', $fik); // See response - should be returned
} catch (ServerException $e) {
    var_dump($e); // See exception
} catch (\Exception $e) {
    var_dump($e); // Fatal error
}

echo sprintf('Request size: %d bytes | Response size: %d bytes | Response time: %f ms | Connection time: %f ms<br />', $dispatcher->getLastRequestSize(), $dispatcher->getLastResponseSize(), $dispatcher->getLastResponseTime(), $dispatcher->getConnectionTime()); // Size of transferred data
// Example of error message
$r->dic_popl = 'x';

// ServerException should be returned
echo '<h2>---ERROR REQUEST---</h2>';
try {
    var_dump($dispatcher->send($r)); // Send request and see response
} catch (ServerException $e) {
    echo sprintf('<b>Error from server of Ministry of Finance: %s</b><br />', $e->getMessage()); // See exception - should be returned
} catch (\Exception $e) {
    var_dump($e); // Fatal error
}

echo sprintf('Request size: %d bytes | Response size: %d bytes | Response time: %f ms | Connection time: %f ms<br />', $dispatcher->getLastRequestSize(), $dispatcher->getLastResponseSize(), $dispatcher->getLastResponseTime(), $dispatcher->getConnectionTime()); // Size of transferred data

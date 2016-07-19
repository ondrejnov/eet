<?php

require_once __DIR__ . "/../bootstrap.php";

use Ondrejnov\EET\Exceptions\ServerException;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;

$dispatcher = new Dispatcher(DIR_CERT . '/eet.key', DIR_CERT . '/eet.pem');

// Example receipt
$r = new Receipt();
$r->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9120';
$r->dic_popl = 'CZ72080043';
$r->id_provoz = '181';
$r->id_pokl = '1';
$r->porad_cis = '1';
$r->dat_trzby = new \DateTime();
$r->celk_trzba = 1000;

// Valid response should be returned
echo '<h2>---VALID REQUEST---</h2>';
try {
    $fik = $dispatcher->send($r); // Send request
    echo sprintf('Returned fik code: %s', $fik); // See response - should be returned
} catch (ServerException $e) {
    var_dump($e); // See exception
} catch (\Exception $e) {
    var_dump($e); // Fatal error
}

// Example of error message
$r->dic_popl = 'x';

// ServerException should be returned
echo '<h2>---ERROR REQUEST---</h2>';
try {
    $fik = $dispatcher->send($r); // Send request
    var_dump($fik); // See response
} catch (ServerException $e) {
    echo sprintf('Error from server of Ministry of Finance: %s', $e->getMessage()); // See exception - should be returned
} catch (\Exception $e) {
    var_dump($e); // Fatal error
}
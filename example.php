<?php
ini_set('display_errors', 1);
define('CERT_DIR', dirname(__FILE__));

require_once ('vendor/wse-php/soap-wsse.php');
require_once ('lib/Receipt.php');

$r = new \eet\Receipt(CERT_DIR.'/eet.key', CERT_DIR.'/eet.pem');
$r->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9120';
$r->dic_popl = 'CZ72080043';
$r->id_provoz = '181';
$r->id_pokl = '1';
$r->porad_cis = '1';
$r->dat_trzby = new DateTime();
$r->celk_trzba = 1000;
$fik = $r->send();
var_dump($fik);

// ukazka chyby
$r->dic_popl = 'x';
try {
	$fik = $r->send();
}
catch (\eet\EETException $e) {
	var_dump($e->getMessage());
}
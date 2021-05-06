# Implementation of EET Client in PHP

[![Downloads this Month](https://img.shields.io/packagist/dm/ondrejnov/eet.svg)](https://packagist.org/packages/ondrejnov/eet)
[![Latest stable](https://img.shields.io/packagist/v/ondrejnov/eet.svg)](https://packagist.org/packages/ondrejnov/eet)

## Installation
Install ondrejnov/eet using  [Composer](http://getcomposer.org/):

```sh
$ composer require ondrejnov/eet
```

### Dependencies
- PHP >=5.6
- robrichards/wse-php
- php extensions: php_openssl.dll, php_curl.dll, php_mbstring.dll, php_soap.dll

Attached WSDL, key and certificate are intended for non-production usage (Playground).

## Example Usage
Sample codes are located in examples/ folder

```php
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;
use Ondrejnov\EET\Utils\UUID;

$dispatcher = new Dispatcher(PLAYGROUND_WSDL, DIR_CERT . '/eet.key', DIR_CERT . '/eet.pem');

$r = new Receipt();
$r->uuid_zpravy = UUID::v4();
$r->dic_popl = 'CZ72080043';
$r->id_provoz = '181';
$r->id_pokl = '1';
$r->porad_cis = '1';
$r->dat_trzby = new \DateTime();
$r->celk_trzba = 1000;

echo $dispatcher->send($r); // FIK code should be returned
```

### License
MIT

---

# Implementace EET klienta v PHP

## Instalace
Instalace ondrejnov/eet pomocí [Composer](http://getcomposer.org/):

```sh
$ composer require ondrejnov/eet
```

### Závislosti
- PHP >=5.6
- robrichards/wse-php
- php extensions: php_openssl.dll, php_soap.dll

Přiložené WSDL, klíč a certifikát jsou pro neprodukční prostředí (Playground).

## Ukázka použití
Ukázky použití naleznete ve složce examples/

```php
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;

$dispatcher = new Dispatcher(PLAYGROUND_WSDL, DIR_CERT . '/eet.key', DIR_CERT . '/eet.pem');

$r = new Receipt();
$r->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9120';
$r->dic_popl = 'CZ72080043';
$r->id_provoz = '181';
$r->id_pokl = '1';
$r->porad_cis = '1';
$r->dat_trzby = new \DateTime();
$r->celk_trzba = 1000;

echo $dispatcher->send($r); // Měl by být vrácen FIK kód
```

### Licence
MIT

---

### Reklama
Komu se nechce do implementace, tak může použít on-line službu <a href="https://www.eetapp.cz/?utm_source=git&utm_medium=link&utm_campaign=eet">EETApp.cz</a>, která má pokročilejší správu účtenek včetně tisku na tiskárnu.

### Bitcoin Donate 
1LZuWFUHeVMrYvZWinxFjjkZtuq56TECot


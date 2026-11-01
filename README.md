# EET 2.0 klient pro PHP

Knihovna pro odesílání evidovaných tržeb do systému [EET 2.0](https://eet.gov.cz/cs/pro-vyvojare/dokumenty-ke-stazeni) (rozhraní v4.1, zákon účinný od 1. 1. 2027). Sestaví zprávu, podepíše ji pokladním certifikátem, odešle a ověří podpis potvrzení.

- Požadavky: PHP 7.0 až 8.5 a rozšíření `curl`, `dom`, `openssl`
- Bez dalších závislostí, nepotřebuje `ext-soap` ani WSDL za běhu
- Otestováno proti živému playgroundu na všech třech testovacích identitách

> [!WARNING]
> Tato verze je zatím určená **jen pro playground**. POK z playgroundu (končí `-ff`) není platným potvrzovacím kódem a tržba se jím neeviduje. Produkční prostředí zatím nebylo vyzkoušené, protože k němu nejsou dostupné certifikáty.

## Rychlý start

```sh
composer install
sh scripts/fetch-playground-certs.sh   # stáhne sdílené testovací certifikáty do _cert/
php examples/simpleClient/Example.php
```

```php
use Ondrejnov\EET\Certificate;
use Ondrejnov\EET\Dispatcher;
use Ondrejnov\EET\Receipt;

$certificate = Certificate::fromPem('_cert/CZ00000019.key', '_cert/CZ00000019.pem');
$dispatcher = new Dispatcher(Dispatcher::PLAYGROUND_URL, $certificate);

$receipt = new Receipt();
$receipt->eic_popl = 'CZ00000019';
$receipt->id_jednotky = 181;
$receipt->id_pokl = 'POKLADNA-1';
$receipt->porad_cis = '2027/0001';
$receipt->dat_trzby = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
$receipt->celk_trzba = 1000;

echo $dispatcher->send($receipt);    // POK, např. 80bd7254-14f1-49f8-a332-986a62bcd5a4-ff
print_r($dispatcher->getWarnings()); // [kód varování => text]
```

Ověřovací mód (`overeni="true"`) tržbu neeviduje, jen ověří spojení a zpracovatelnost zprávy:

```php
$dispatcher->check($receipt); // true = chyba 0, false = detail v $dispatcher->getLastResponse()
```

## Položky tržby

| Položka | Povinná | Hodnota |
| --- | --- | --- |
| `eic_popl` | ano | EIČ poplatníka, `CZ` + 8 až 10 číslic |
| `id_jednotky` | ano | číslo evidenční jednotky z DIS+, 1 až 999999999 |
| `id_pokl` | ano | označení pokladního zařízení, max. 20 znaků |
| `porad_cis` | ano | pořadové číslo tržby, max. 25 znaků |
| `dat_trzby` | ano | `DateTimeInterface`, časová zóna místa tržby |
| `celk_trzba` | ano | částka v Kč, `int`, `float` nebo číselný řetězec |
| `urceno_cerp_zuct` | ne | platby určené k následnému čerpání nebo zúčtování |
| `cerp_zuct` | ne | platby, které jsou čerpáním nebo zúčtováním |
| `eic_poverujiciho` | ne | EIČ pověřujícího poplatníka |
| `povereni_vice_popl` | ne | tržba plyne více poplatníkům |
| `uuid_zpravy` | ne | prázdné = nové UUID při každém odeslání |
| `prvni_zaslani` | ne | výchozí `true`, nastavuje `forResend()` |

Před odesláním se hodnoty kontrolují podle masek z XSD. Neplatná tržba se neodešle a skončí výjimkou `ValidationException`. Neznámá nebo zrušená položka (například `dan1`) vyvolá výjimku hned při přiřazení, aby z daňové zprávy tiše nezmizela.

## Odpovědi a chyby

| Výsledek | Význam | Co dělat |
| --- | --- | --- |
| `send()` vrátí POK | tržba přijata | uložit POK, vyhodnotit `getWarnings()` a zalogovat `getLastResponse()->transactionId` |
| `ValidationException` | neplatná data, nic se neodeslalo | opravit data |
| `TransportException` | timeout, výpadek nebo nečitelná odpověď, stav tržby není známý | co nejdříve poslat `forResend()` |
| `ServerException`, `isRetryable()` je `true` | dočasná chyba serveru (kód -1 nebo 8) | poslat `forResend()` později |
| `ServerException`, `isRetryable()` je `false` | kritická chyba, kód je v `getCode()` | opravit příčinu |
| `ResponseVerificationException` | odpovědi nelze důvěřovat | POK nepoužívat, prověřit |

### Opakované odeslání

Dokud nepřijde POK, je povinnost zaslat tržbu stále aktuální. Lhůta je bezodkladně po pominutí výpadku, nejpozději do 48 hodin.

```php
$dispatcher->send($receipt->forResend()); // stejná data, nové UUID, prvni_zaslani = false
```

> [!NOTE]
> Slide 53 prezentace ze semináře 23. 6. 2026 uvádí opakování „se stejným UUID“. Technická specifikace (kap. 3.3.3.1), popis položek i slide 57 téže prezentace ale požadují UUID nové. Knihovna se řídí specifikací.

Storno je nová zpráva se zápornou částkou, aktuálním datem a novým UUID. Na původní tržbu se neváže.

## Certifikáty

Certifikát od CA EET se dodává jako `.p12`, knihovna ale načte i PEM:

```php
Certificate::fromPkcs12('pokladna.p12', $heslo);
Certificate::fromPem('pokladna.key', 'pokladna.pem', $heslo);
```

> [!IMPORTANT]
> Soubory `.p12` z playgroundu používají zastaralou šifru RC2-40, kterou OpenSSL 3 ve výchozím stavu odmítá. `fromPkcs12()` pak skončí srozumitelnou chybou. Řešení jsou dvě:
>
> - Převést soubor na PEM příkazem `openssl pkcs12 -legacy`, jak to dělá `scripts/fetch-playground-certs.sh`.
> - Zapnout `legacy` provider v konfiguraci `OPENSSL_CONF`.

Jestli stejnou šifru používají i produkční `.p12`, zatím nebylo možné ověřit.

## Zabezpečení

- **Podpis zprávy:** podepisuje se jen `soap:Body` (exclusive C14N, SHA-256, RSA-SHA256). Struktura odpovídá vzorovým zprávám finanční správy a neobsahuje `Timestamp` ani WS-Addressing.
- **Spojení:** jen HTTPS přes HTTP/1.1, TLS 1.2 a vyšší, s ověřením certifikátu serveru. Každý požadavek používá nové spojení, takže se znovu překládá DNS.
- **Potvrzení:** ověřuje se podpis, řetěz k I.CA (`resources/ica-signing-ca.pem`), platnost certifikátu, `O = Generální finanční ředitelství` a shoda UUID se zprávou. Podepsané tělo musí být to samé, ze kterého se čte POK, takže útok typu signature wrapping neprojde. CRL ani OCSP se nekontrolují.
- **Chybové odpovědi:** ty finanční správa nepodepisuje, a proto se neověřují.

Výchozí časové limity jsou 2,5 s na požadavek a 2 s na spojení. Lze je změnit vlastním transportem:

```php
new Dispatcher($url, $certificate, null, new CurlTransport(5000, 3000));
```

## Přechod z verze 0.x

Rozhraní v4 není se starým EET kompatibilní.

| Dříve | Nyní |
| --- | --- |
| `new Dispatcher($wsdl, $key, $cert, $pass)` | `new Dispatcher($url, Certificate::fromPem($key, $cert, $pass))` |
| `dic_popl`, `dic_poverujiciho` | `eic_popl`, `eic_poverujiciho` |
| `id_provoz` | `id_jednotky` |
| FIK | POK |
| `send($r, true)` | `check($r)` |
| PKP, BKP, `getCheckCodes()` | zrušeno |
| položky DPH, `cest_sluz`, `pouzit_zboz*`, `rezim` | zrušeno |
| `getWholeResponse()` | `getLastResponse()` |
| `$dispatcher->trace`, `getConnectionTime()` | zrušeno, `getLastResponseTime()` a velikosti jsou dostupné vždy |
| `robrichards/wse-php`, `ext-soap` | nepotřeba |

> [!CAUTION]
> Starý zápis `send($r, true)` PHP nenahlásí jako chybu, druhý argument jen ignoruje. Tržba se tak odešle **v ostrém módu**. Při přechodu ho proto nahraďte za `check($r)`.

## Testy

```sh
vendor/bin/tester -C tests                    # bez sítě
EET_PLAYGROUND=1 vendor/bin/tester -C tests   # včetně živého playgroundu
```

Testovací vektory v `tests/fixtures` tvoří oficiální vzorové zprávy a skutečné odpovědi playgroundu. Certifikáty playgroundu platí do 14. 5. 2027. Playground má servisní okno obvykle ve čtvrtek mezi 20:00 a 6:00.

## In English

Client for the Czech electronic registration of sales, EET 2.0 (interface v4.1), for PHP 7.0+. It builds and signs the WS-Security message, sends it over HTTPS and verifies the signed confirmation. This version targets the **non-production playground only**. See the code samples above and `examples/simpleClient/Example.php`.

Python port: [ondrejnov/eet-py](https://github.com/ondrejnov/eet-py).

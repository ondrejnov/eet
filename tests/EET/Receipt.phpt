<?php

declare(strict_types=1);

use Ondrejnov\EET\Exceptions\ClientException;
use Ondrejnov\EET\Exceptions\ValidationException;
use Ondrejnov\EET\Receipt;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$assertInvalid = static function (string $item, mixed $value): void {
    $receipt = createReceipt();
    $receipt->$item = $value;

    $exception = Assert::exception(static fn () => $receipt->validate(), ValidationException::class);
    Assert::contains($item, $exception->getMessage(), $item . ' = ' . var_export($value, true));
};

createReceipt()->validate();
Assert::true(true);

// Values taken from the specification.
$receipt = createReceipt();
$receipt->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9125';
$receipt->eic_popl = 'CZ8551015704';
$receipt->eic_poverujiciho = 'CZ683555118';
$receipt->povereni_vice_popl = true;
$receipt->id_jednotky = '164968741';
$receipt->id_pokl = '5a/A-q/5:22d_2';
$receipt->porad_cis = '#25/c-12/1A_2/2027';
$receipt->celk_trzba = '-187.20';
$receipt->urceno_cerp_zuct = 343;
$receipt->cerp_zuct = 237.5;
$receipt->validate();

foreach (['eic_popl', 'id_jednotky', 'id_pokl', 'porad_cis', 'dat_trzby', 'celk_trzba'] as $required) {
    $assertInvalid($required, null);
}

$assertInvalid('uuid_zpravy', 'b3a09b52-7c87-6014-a496-4c7a53cf9125');
$assertInvalid('uuid_zpravy', 'b3a09b52-7c87-4014-c496-4c7a53cf9125');
$assertInvalid('eic_popl', 'x');
$assertInvalid('eic_popl', 'CZ1234567');
$assertInvalid('eic_popl', 'CZ12345678901');
$assertInvalid('eic_popl', "CZ00000019\n");
$assertInvalid('eic_poverujiciho', '');
$assertInvalid('id_jednotky', 0);
$assertInvalid('id_jednotky', '0123');
$assertInvalid('id_jednotky', 1000000000);
$assertInvalid('id_pokl', '');
$assertInvalid('id_pokl', str_repeat('1', 21));
$assertInvalid('id_pokl', 'pokladna č.1');
$assertInvalid('porad_cis', str_repeat('1', 26));
$assertInvalid('porad_cis', '<script>');
$assertInvalid('celk_trzba', '12,50');
$assertInvalid('urceno_cerp_zuct', 100000000);
$assertInvalid('cerp_zuct', 'abc');

// All problems are reported at once.
$receipt = new Receipt();
$exception = Assert::exception(static fn () => $receipt->validate(), ValidationException::class);
Assert::count(6, $exception->getErrors());

// Items removed or renamed in EET 2.0 must not silently disappear.
$exception = Assert::exception(static function (): void {
    $receipt = new Receipt();
    $receipt->dic_popl = 'CZ00000019';
}, ClientException::class);
Assert::contains('eic_popl', $exception->getMessage());

foreach (['id_provoz', 'dan1', 'zakl_dan1', 'rezim', 'celk_trzb'] as $unknown) {
    Assert::exception(static function () use ($unknown): void {
        $receipt = new Receipt();
        $receipt->$unknown = 1;
    }, ClientException::class);
}

// Another attempt to send the same sale.
$receipt = createReceipt();
$receipt->uuid_zpravy = 'b3a09b52-7c87-4014-a496-4c7a53cf9125';

$copy = $receipt->forResend();
Assert::null($copy->uuid_zpravy);
Assert::false($copy->prvni_zaslani);
Assert::same($receipt->dat_trzby, $copy->dat_trzby);
Assert::same($receipt->porad_cis, $copy->porad_cis);
Assert::same('b3a09b52-7c87-4014-a496-4c7a53cf9125', $receipt->uuid_zpravy);
Assert::true($receipt->prvni_zaslani);

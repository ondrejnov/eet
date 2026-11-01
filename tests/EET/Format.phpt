<?php

declare(strict_types=1);

use Ondrejnov\EET\Exceptions\ValidationException;
use Ondrejnov\EET\Utils\Format;
use Ondrejnov\EET\Utils\UUID;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

// Examples of the correct and forbidden representations from chapter 3.3.3.12 of the specification.
Assert::same('250.00', Format::price(250));
Assert::same('-187.20', Format::price(-187.2));
Assert::same('0.56', Format::price('0.56'));
Assert::same('20.45', Format::price('020.45'));
Assert::same('0.00', Format::price(0));
Assert::same('0.00', Format::price(-0.0));
Assert::same('0.00', Format::price(-0.001));
Assert::same('0.20', Format::price('.20'));
Assert::same('-100.00', Format::price('-00100.00'));
Assert::same('-0.01', Format::price(-0.01));
Assert::same('99999999.99', Format::price(99999999.99));
Assert::same('-99999999.99', Format::price('-99999999.99'));
Assert::null(Format::price(null));

Assert::exception(function () { return Format::price(100000000); }, ValidationException::class);
Assert::exception(function () { return Format::price(-100000000); }, ValidationException::class);
Assert::exception(function () { return Format::price(99999999.999); }, ValidationException::class);
Assert::exception(function () { return Format::price('12,50'); }, ValidationException::class);
Assert::exception(function () { return Format::price('abc'); }, ValidationException::class);
Assert::exception(function () { return Format::price(''); }, ValidationException::class);
Assert::exception(function () { return Format::price(NAN); }, ValidationException::class);
Assert::exception(function () { return Format::price(INF); }, ValidationException::class);

$winter = new DateTimeImmutable('2027-01-09 04:25:28.123456', new DateTimeZone('Europe/Prague'));
Assert::same('2027-01-09T04:25:28+01:00', Format::dateTime($winter));

$summer = new DateTime('2027-06-09 05:25:28', new DateTimeZone('Europe/Prague'));
Assert::same('2027-06-09T05:25:28+02:00', Format::dateTime($summer));

Assert::same('true', Format::boolean(true));
Assert::same('false', Format::boolean(false));

$generated = [];
for ($i = 0; $i < 1000; $i++) {
    $uuid = UUID::v4();
    Assert::match('~^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$~', $uuid);
    $generated[$uuid] = true;
}
Assert::count(1000, $generated);

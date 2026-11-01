<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Utils;

use Ondrejnov\EET\Exceptions\ValidationException;

class Format
{
    /** CastkaType mask from the XSD. */
    const PRICE_MASK = '/^((0|-?[1-9]\d{0,7})\.\d\d|-0\.(0[1-9]|[1-9]\d))$/D';

    /**
     * Formats an amount to the only representation accepted by EET, e.g. 1000 => "1000.00".
     *
     * @param int|float|string|null $value
     *
     * @return string|null
     *
     * @throws ValidationException when the value is not a number, has more than two decimals or is out of the allowed range
     */
    public static function price($value)
    {
        if ($value === null) {
            return null;
        }

        $number = is_numeric($value) ? (float) $value : NAN;
        if (!is_finite($number)) {
            throw new ValidationException(['Amount is not a number: ' . var_export($value, true)]);
        }

        if (self::hasMoreThanTwoDecimals($value, $number)) {
            throw new ValidationException(['Amount has more than two decimals: ' . var_export($value, true)]);
        }

        $formatted = number_format($number, 2, '.', '');
        if ($formatted === '-0.00') { // PHP < 8.0 keeps the sign of a negative zero
            $formatted = '0.00';
        }
        if (preg_match(self::PRICE_MASK, $formatted) !== 1) {
            throw new ValidationException(['Amount is out of the allowed range: ' . $formatted]);
        }

        return $formatted;
    }

    /**
     * Rounding would send a different amount than the caller passed. Only the binary noise of
     * float arithmetic is tolerated, e.g. 0.1 + 0.2 is the amount 0.30.
     *
     * @param int|float|string $value
     */
    private static function hasMoreThanTwoDecimals($value, float $number): bool
    {
        if (is_string($value) && preg_match('/^\s*[+-]?\d*\.(\d*)\s*$/D', $value, $matches) === 1) {
            return rtrim(substr($matches[1], 2), '0') !== '';
        }

        return abs($number - round($number, 2)) >= 0.000001;
    }

    /**
     * Formats a date as ISO 8601 with a mandatory UTC offset and without fractional seconds.
     */
    public static function dateTime(\DateTimeInterface $value): string
    {
        return $value->format('Y-m-d\TH:i:sP');
    }

    public static function boolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}

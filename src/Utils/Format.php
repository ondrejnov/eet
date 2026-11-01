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
     * @throws ValidationException when the value is not a number or is out of the allowed range
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

        $formatted = number_format($number, 2, '.', '');
        if ($formatted === '-0.00') { // PHP < 8.0 keeps the sign of a value rounded to zero
            $formatted = '0.00';
        }
        if (preg_match(self::PRICE_MASK, $formatted) !== 1) {
            throw new ValidationException(['Amount is out of the allowed range: ' . $formatted]);
        }

        return $formatted;
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

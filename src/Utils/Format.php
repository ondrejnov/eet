<?php

namespace Ondrejnov\EET\Utils;

class Format {

    public static function price($value) {
        return number_format($value, 2, '.', '');
    }

    public static function BKP($code) {
        return implode('-', str_split(strtoupper($code), 8));
    }

}

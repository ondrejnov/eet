<?php

namespace Ondrejnov\EET\Utils;

class Format {

    public static function price($value) {
        return $value === false ? $value : number_format($value, 2, '.', '');
    }

    public static function BKP($code) {
        return implode('-', str_split(strtoupper($code), 8));
    }
	
	public static function unsetInvalidKeys($data) {
		$to_unset = array();
		
		foreach ($data as $k => $v) {
			if ($v === false) $to_unset[] = $k;
		}
		
		foreach ($to_unset as $k) {
			unset($data[$k]);
		}
		
		return $data;
	}
	
}

<?php
class Validator {
    public static function validate($field, $value, $rule) {
        switch ($rule) {
            case 'postcode':
                return preg_match('/^[A-Za-z0-9 ]{3,8}$/', $value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL);
            case 'phone':
                // UK mobile: starts with 07 and is 11 digits
                // UK landline: starts with 01, 02, 03, 08, 09 and is 10 or 11 digits
                $digits = preg_replace('/\D/', '', $value);
                if (preg_match('/^07\d{9}$/', $digits)) {
                    return true; // UK mobile
                }
                if (preg_match('/^(01|02|03|08|09)\d{8,9}$/', $digits)) {
                    return true; // UK landline (10 or 11 digits)
                }
                return false;
            case 'required':
                return !empty($value);
            default:
                return true;
        }
    }
} 
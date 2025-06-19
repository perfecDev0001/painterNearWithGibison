<?php
/**
 * Enhanced Validator Class
 * Provides comprehensive validation rules for form inputs
 */
class Validator {
    /**
     * Validation error messages
     * @var array
     */
    private static $errorMessages = [
        'required' => 'This field is required',
        'email' => 'Please enter a valid email address',
        'phone' => 'Please enter a valid UK phone number',
        'postcode' => 'Please enter a valid UK postcode',
        'min_length' => 'This field must be at least %d characters',
        'max_length' => 'This field cannot exceed %d characters',
        'numeric' => 'This field must contain only numbers',
        'alpha' => 'This field must contain only letters',
        'alphanumeric' => 'This field must contain only letters and numbers',
        'url' => 'Please enter a valid URL',
        'date' => 'Please enter a valid date in YYYY-MM-DD format',
        'uk_date' => 'Please enter a valid date in DD/MM/YYYY format',
        'matches' => 'This field must match the %s field',
        'in_list' => 'Please select a valid option',
        'not_in_list' => 'This value is not allowed'
    ];
    
    /**
     * Last validation error message
     * @var string
     */
    private static $lastError = '';
    
    /**
     * Validate a field value against a rule
     * 
     * @param string $field Field name (for error messages)
     * @param mixed $value Value to validate
     * @param string|array $rule Validation rule or array of rules
     * @param array $context Additional context data (e.g., other form fields)
     * @return bool Whether validation passed
     */
    public static function validate($field, $value, $rule, $context = []) {
        // Reset last error
        self::$lastError = '';
        
        // Handle array of rules
        if (is_array($rule)) {
            foreach ($rule as $singleRule) {
                if (!self::validateSingleRule($field, $value, $singleRule, $context)) {
                    return false;
                }
            }
            return true;
        }
        
        // Handle single rule
        return self::validateSingleRule($field, $value, $rule, $context);
    }
    
    /**
     * Validate a field value against a single rule
     * 
     * @param string $field Field name
     * @param mixed $value Value to validate
     * @param string $rule Validation rule
     * @param array $context Additional context data
     * @return bool Whether validation passed
     */
    private static function validateSingleRule($field, $value, $rule, $context = []) {
        // Extract rule parameters if any (e.g., min_length[8])
        $params = [];
        if (preg_match('/^([a-z_]+)(?:\[(.+)\])?$/', $rule, $matches)) {
            $rule = $matches[1];
            if (isset($matches[2])) {
                $params = explode(',', $matches[2]);
            }
        }
        
        // Skip validation for empty values unless rule is 'required'
        if ($rule !== 'required' && empty($value) && $value !== '0' && $value !== 0) {
            return true;
        }
        
        switch ($rule) {
            case 'required':
                $valid = !empty($value) || $value === '0' || $value === 0;
                if (!$valid) self::$lastError = self::$errorMessages['required'];
                return $valid;
                
            case 'email':
                $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                if (!$valid) self::$lastError = self::$errorMessages['email'];
                return $valid;
                
            case 'phone':
                // UK mobile: starts with 07 and is 11 digits
                // UK landline: starts with 01, 02, 03, 08, 09 and is 10 or 11 digits
                // International: starts with + and has 10+ digits
                $digits = preg_replace('/\D/', '', $value);
                $valid = false;
                
                // Check for UK mobile
                if (preg_match('/^07\d{9}$/', $digits)) {
                    $valid = true; // UK mobile
                }
                // Check for UK landline
                else if (preg_match('/^(01|02|03|08|09)\d{8,9}$/', $digits)) {
                    $valid = true; // UK landline
                }
                // Check for international format with + prefix
                else if (preg_match('/^\+\d{10,15}$/', str_replace(' ', '', $value))) {
                    $valid = true; // International
                }
                
                if (!$valid) self::$lastError = self::$errorMessages['phone'];
                return $valid;
                
            case 'postcode':
                // More comprehensive UK postcode validation
                // Format: AA9A 9AA, A9A 9AA, A9 9AA, A99 9AA, AA9 9AA, AA99 9AA
                $postcode = strtoupper(str_replace(' ', '', $value));
                $valid = preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $postcode);
                if (!$valid) self::$lastError = self::$errorMessages['postcode'];
                return $valid;
                
            case 'min_length':
                $minLength = isset($params[0]) ? (int)$params[0] : 1;
                $valid = mb_strlen($value) >= $minLength;
                if (!$valid) self::$lastError = sprintf(self::$errorMessages['min_length'], $minLength);
                return $valid;
                
            case 'max_length':
                $maxLength = isset($params[0]) ? (int)$params[0] : 255;
                $valid = mb_strlen($value) <= $maxLength;
                if (!$valid) self::$lastError = sprintf(self::$errorMessages['max_length'], $maxLength);
                return $valid;
                
            case 'numeric':
                $valid = is_numeric($value);
                if (!$valid) self::$lastError = self::$errorMessages['numeric'];
                return $valid;
                
            case 'alpha':
                $valid = preg_match('/^[A-Za-z]+$/', $value);
                if (!$valid) self::$lastError = self::$errorMessages['alpha'];
                return $valid;
                
            case 'alphanumeric':
                $valid = preg_match('/^[A-Za-z0-9]+$/', $value);
                if (!$valid) self::$lastError = self::$errorMessages['alphanumeric'];
                return $valid;
                
            case 'url':
                $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                if (!$valid) self::$lastError = self::$errorMessages['url'];
                return $valid;
                
            case 'date':
                $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
                if (!$valid) self::$lastError = self::$errorMessages['date'];
                return $valid;
                
            case 'uk_date':
                $valid = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value);
                if ($valid) {
                    list($day, $month, $year) = explode('/', $value);
                    $valid = checkdate($month, $day, $year);
                }
                if (!$valid) self::$lastError = self::$errorMessages['uk_date'];
                return $valid;
                
            case 'matches':
                $otherField = isset($params[0]) ? $params[0] : '';
                $otherValue = isset($context[$otherField]) ? $context[$otherField] : '';
                $valid = $value === $otherValue;
                if (!$valid) self::$lastError = sprintf(self::$errorMessages['matches'], $otherField);
                return $valid;
                
            case 'in_list':
                $valid = in_array($value, $params);
                if (!$valid) self::$lastError = self::$errorMessages['in_list'];
                return $valid;
                
            case 'not_in_list':
                $valid = !in_array($value, $params);
                if (!$valid) self::$lastError = self::$errorMessages['not_in_list'];
                return $valid;
                
            default:
                return true;
        }
    }
    
    /**
     * Get the last validation error message
     * 
     * @return string Error message
     */
    public static function getLastError() {
        return self::$lastError;
    }
    
    /**
     * Set a custom error message for a validation rule
     * 
     * @param string $rule Validation rule
     * @param string $message Custom error message
     */
    public static function setErrorMessage($rule, $message) {
        self::$errorMessages[$rule] = $message;
    }
}
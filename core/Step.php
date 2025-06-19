<?php
class Step {
    public $config;
    public $data;
    public $errors = [];

    public function __construct($config, $data = []) {
        $this->config = $config;
        $this->data = $data;
    }

    /**
     * Validate all fields in the step
     * 
     * @return bool Whether all validations passed
     */
    public function validate() {
        require_once __DIR__ . '/Validator.php';
        $fields = $this->getFields();
        
        // First pass: collect all field values for context-aware validation
        $allValues = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $allValues[$fieldName] = isset($this->data[$fieldName]) ? $this->data[$fieldName] : '';
        }
        
        // Second pass: validate each field with full context
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $value = $allValues[$fieldName];
            
            if (!empty($field['validation'])) {
                $rules = is_array($field['validation']) ? $field['validation'] : [$field['validation']];
                
                foreach ($rules as $rule) {
                    if (!Validator::validate($fieldName, $value, $rule, $allValues)) {
                        // Use the specific error message from the validator
                        $this->errors[$fieldName] = Validator::getLastError() ?: 
                            'Invalid value for ' . $field['label'];
                        
                        // Break on first validation failure for this field
                        break;
                    }
                }
            }
            
            // Custom field-specific validations
            if (empty($this->errors[$fieldName])) {
                $this->validateSpecificField($fieldName, $value, $field, $allValues);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Perform field-specific validations beyond standard rules
     * 
     * @param string $fieldName Field name
     * @param mixed $value Field value
     * @param array $fieldConfig Field configuration
     * @param array $allValues All form values
     */
    protected function validateSpecificField($fieldName, $value, $fieldConfig, $allValues) {
        // Field-specific validations can be added here
        switch ($fieldName) {
            case 'email':
                // Check for disposable email domains
                if (!empty($value) && $this->isDisposableEmail($value)) {
                    $this->errors[$fieldName] = 'Please use a permanent email address';
                }
                break;
                
            case 'postcode':
                // Additional postcode validation if needed
                break;
                
            // Add more field-specific validations as needed
        }
    }
    
    /**
     * Check if an email is from a disposable/temporary domain
     * 
     * @param string $email Email to check
     * @return bool Whether it's a disposable email
     */
    private function isDisposableEmail($email) {
        $disposableDomains = [
            'mailinator.com', 'tempmail.com', 'throwawaymail.com', 
            'temp-mail.org', 'guerrillamail.com', 'yopmail.com'
        ];
        
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        return in_array($domain, $disposableDomains);
    }

    public function getFields() {
        // For now, assume one field per step, can be extended for multi-field steps
        $fields = [];
        if ($this->config['type'] === 'input' || $this->config['type'] === 'textarea') {
            $fields[] = [
                'name' => strtolower($this->config['id']),
                'label' => $this->config['label'],
                'validation' => $this->config['validation'] ?? 'required',
            ];
        } elseif ($this->config['type'] === 'radio') {
            $fields[] = [
                'name' => strtolower($this->config['id']),
                'label' => $this->config['label'],
                'validation' => $this->config['validation'] ?? 'required',
            ];
        } elseif ($this->config['type'] === 'contact') {
            $fields[] = [
                'name' => 'fullname',
                'label' => 'Full name',
                'validation' => ['required'],
            ];
            $fields[] = [
                'name' => 'email',
                'label' => 'Email',
                'validation' => ['required', 'email'],
            ];
            $fields[] = [
                'name' => 'phone',
                'label' => 'Phone number',
                'validation' => ['required', 'phone'],
            ];
        }
        return $fields;
    }
} 
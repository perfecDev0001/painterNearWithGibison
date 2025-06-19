<?php
class Step {
    public $config;
    public $data;
    public $errors = [];

    public function __construct($config, $data = []) {
        $this->config = $config;
        $this->data = $data;
    }

    public function validate() {
        require_once __DIR__ . '/Validator.php';
        $fields = $this->getFields();
        foreach ($fields as $field) {
            $value = isset($this->data[$field['name']]) ? $this->data[$field['name']] : '';
            if (!empty($field['validation'])) {
                $rules = is_array($field['validation']) ? $field['validation'] : [$field['validation']];
                foreach ($rules as $rule) {
                    if (!Validator::validate($field['name'], $value, $rule)) {
                        $this->errors[$field['name']] = 'Invalid value for ' . $field['label'];
                    }
                }
            }
        }
        return empty($this->errors);
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
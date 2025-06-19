<?php
return [
    'steps' => [
        [
            'id' => 'Postcode',
            'type' => 'input',
            'label' => 'Postcode',
            'validation' => 'postcode',
        ],
        [
            'id' => 'RequestType',
            'type' => 'radio',
            'label' => 'Request for',
            'options' => ['Interior', 'Exterior', 'Both'],
        ],
        [
            'id' => 'JobType',
            'type' => 'radio',
            'label' => 'Type of job',
            'options' => ['Residential', 'Commercial'],
        ],
        [
            'id' => 'PropertyType',
            'type' => 'radio',
            'label' => 'Your property',
            'options' => ['House', 'Apartment/Flat'],
        ],
        [
            'id' => 'ProjectDescription',
            'type' => 'textarea',
            'label' => 'Project description',
        ],
        [
            'id' => 'ContactDetails',
            'type' => 'contact',
            'label' => 'Contact details',
        ],
    ],
    'theme' => [
        'primaryColor' => '#00b050',
        'logo' => '/assets/images/logo.svg',
    ],
]; 
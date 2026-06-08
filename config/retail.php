<?php

return [
    'currency' => [
        'code' => env('RETAIL_CURRENCY_CODE', 'NPR'),
        'symbol' => env('RETAIL_CURRENCY_SYMBOL', 'रू'),
        'decimal_places' => (int) env('RETAIL_CURRENCY_DECIMALS', 2),
    ],

    'country' => env('RETAIL_COUNTRY', 'Nepal'),

    'timezone' => env('RETAIL_TIMEZONE', 'Asia/Kathmandu'),

    'office_types' => [
        'front_office' => 'Front office',
        'back_office' => 'Back office',
    ],
];

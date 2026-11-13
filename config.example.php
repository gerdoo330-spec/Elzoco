<?php
// نمونه تنظیمات امن؛ مقادیر واقعی را فقط روی هاست و خارج از Git قرار دهید.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'DATABASE_NAME',
        'user' => 'DATABASE_USER',
        'pass' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'app_name' => 'Elzoco HRM',
    'timezone' => 'Asia/Tehran',
    'upload_max_mb' => 20,
    'advance_round' => 10000000,
    'advance_ratio' => 0.333333,
    'leave_max_hours_per_day' => 8,
    'sheet_sync' => [
        'url' => '',
        'secret' => '',
    ],
];

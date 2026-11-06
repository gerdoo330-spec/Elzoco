<?php
declare(strict_types=1);
// تنظیمات اتصال هاست جدید را وارد کنید. این فایل نباید حاوی رمز واقعی در مخزن عمومی باشد.
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'CPANEL_DATABASE_NAME',
        'user'    => 'CPANEL_DATABASE_USER',
        'pass'    => 'CHANGE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app_name'       => 'پرتال حقوق و خدمات کارکنان الزوکو',
    'timezone'       => 'Asia/Tehran',
    'upload_max_mb'  => 40,
    'advance_round'  => 10000000,
    'advance_ratio'  => 1 / 3,
    'leave_max_hours_per_day' => 8,
];

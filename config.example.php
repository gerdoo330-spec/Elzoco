<?php
declare(strict_types=1);

// نمونه تنظیمات محیط؛ این فایل را به config.php کپی کنید و مقادیر واقعی را فقط روی هاست وارد کنید.
// config.php واقعی، رمز دیتابیس و کلید همگام‌سازی را هرگز در مخزن عمومی قرار ندهید.
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'CPANEL_DATABASE_NAME',
        'user'    => 'CPANEL_DATABASE_USER',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'app_name'       => 'سامانه پرسنلی کارکنان الزوکو',
    'timezone'       => 'Asia/Tehran',
    'upload_max_mb'  => 40,
    'advance_round'  => 10000000,
    'advance_ratio'  => 1 / 3,
    'leave_max_hours_per_day' => 8,
    // اختیاری: همگام‌سازی با Google Apps Script؛ مقادیر واقعی فقط در config.php محیط هاست.
    'sheet_sync' => [
        'url'    => '',
        'secret' => '',
    ],
];

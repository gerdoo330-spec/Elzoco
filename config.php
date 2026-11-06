<?php
// تنظیمات اصلی — قبل از نصب مقادیر را با اطلاعات هاست خود تغییر دهید.
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'h428624_elzoco',
        'user'    => 'h428624_admin',
        'pass'    => '@Vahid146325@',
        'charset' => 'utf8mb4',
    ],
    'app_name'       => 'پرتال حقوق و خدمات کارکنان الزوکو',
    'timezone'       => 'Asia/Tehran',
    'upload_max_mb'  => 40,
    // سقف مساعده: یک‌سوم حقوق، گرد به مضرب این مبلغ (ریال)
    'advance_round'  => 10000000,
    'advance_ratio'  => 1 / 3,
    // مرخصی ساعتی: حداکثر ساعت در روز
    'leave_max_hours_per_day' => 8,
];

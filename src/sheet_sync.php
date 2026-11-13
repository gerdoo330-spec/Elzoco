<?php
declare(strict_types=1);

/**
 * همگام‌سازی تأیید نهایی درخواست‌ها با گوگل شیت.
 * هر کارمند در هر ماه فقط یک درخواست مساعده و یک درخواست وام دارد،
 * پس مقدار همان ردیف و ستون جایگزین می‌شود (جمع نمی‌شود).
 * خطای ارسال فقط در لاگ ثبت می‌شود و هرگز مانع ثبت تأیید در پرونده نمی‌شود.
 */

const SHEET_MONTHS = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

function sheet_sync_push(array $request, int $approved, string $kind, string $personnelCode): void
{
    $cfg = cfg('sheet_sync');
    if (empty($cfg['url']) || empty($cfg['secret'])) {
        return; // هنوز تنظیم نشده؛ هیچ کاری انجام نمی‌شود
    }
    try {
        $j = jalali_parse((string)($request['window_start'] ?? ''));
        if (!$j || !isset($j[1]) || $j[1] < 1 || $j[1] > 12) {
            error_log('sheet sync: بازه‌ی نامعتبر برای درخواست ' . ($request['tracking'] ?? ''));
            return;
        }
        if ($personnelCode === '') {
            error_log('sheet sync: کد پرسنلی خالی برای درخواست ' . ($request['tracking'] ?? ''));
            return;
        }
        $payload = [
            'token'     => $cfg['secret'],
            'month'     => SHEET_MONTHS[$j[1] - 1],
            'personnel' => $personnelCode,
            'kind'      => $kind,
            'amount'    => $approved,
            'tracking'  => (string)($request['tracking'] ?? ''),
        ];
        $ch = curl_init($cfg['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $http >= 400) {
            error_log('sheet sync: خطای ارتباط با شیت (HTTP ' . $http . ')');
            return;
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['ok'])) {
            error_log('sheet sync: ' . (is_array($data) ? ($data['error'] ?? 'unknown') : 'پاسخ نامعتبر'));
        }
    } catch (Throwable $e) {
        error_log('sheet sync: ' . $e->getMessage());
    }
}

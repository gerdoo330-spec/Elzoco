<?php
declare(strict_types=1);

/** میلادی به شمسی: [سال، ماه، روز] */
function jalali_from_gregorian(int $gy, int $gm, int $gd): array
{
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
    $jy = -1595 + 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/** شمسی به میلادی: [سال، ماه، روز] */
function gregorian_from_jalali(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd
        + ($jm < 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * intdiv($days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = ($gy % 4 == 0 && $gy % 100 != 0) || $gy % 400 == 0;
    $sal = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) {
        $gd -= $sal[$gm];
    }
    return [$gy, $gm, $gd];
}

function jalali_month_length(int $jy, int $jm): int
{
    if ($jm <= 6) {
        return 31;
    }
    if ($jm <= 11) {
        return 30;
    }
    [$gy, $gm, $gd] = gregorian_from_jalali($jy, 12, 30);
    [, $bm, $bd] = jalali_from_gregorian($gy, $gm, $gd);
    return ($bm === 12 && $bd === 30) ? 30 : 29;
}

function jalali_today(): array
{
    $d = new DateTimeImmutable('now', new DateTimeZone(APP_TZ));
    return jalali_from_gregorian((int)$d->format('Y'), (int)$d->format('n'), (int)$d->format('j'));
}

function jalali_num(array $j): int
{
    return $j[0] * 10000 + $j[1] * 100 + $j[2];
}

function jalali_str(array $j): string
{
    return sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
}

/** ورودی کاربر مثل «1405/07/20» یا «۱۴۰۵/۰۷/۲۰» به آرایه؛ نامعتبر = null */
function jalali_parse(string $s): ?array
{
    $s = fa_digits_to_en($s);
    if (!preg_match('/^\s*(\d{4})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*$/', $s, $m)) {
        return null;
    }
    $j = [(int)$m[1], (int)$m[2], (int)$m[3]];
    if ($j[1] < 1 || $j[1] > 12 || $j[2] < 1 || $j[2] > jalali_month_length($j[0], $j[1])) {
        return null;
    }
    return $j;
}

/** تاریخ شروع به کار از کد yymmdd (۶ رقم) یا yyyymmdd (۸ رقم) */
function jalali_from_start_code(string $code): ?array
{
    $d = preg_replace('/\D/', '', $code);
    if (strlen($d) === 6) {
        $y = 1400 + (int)substr($d, 0, 2);
        $m = (int)substr($d, 2, 2);
        $dd = (int)substr($d, 4, 2);
    } elseif (strlen($d) === 8) {
        $y = (int)substr($d, 0, 4);
        $m = (int)substr($d, 4, 2);
        $dd = (int)substr($d, 6, 2);
    } else {
        return null;
    }
    if ($m < 1 || $m > 12 || $dd < 1 || $dd > 31) {
        return null;
    }
    return [$y, $m, $dd];
}

/** شماره‌ی روز برای اختلاف دو تاریخ شمسی */
function jalali_day_number(array $j): int
{
    [$gy, $gm, $gd] = gregorian_from_jalali($j[0], $j[1], $j[2]);
    return intdiv(gmmktime(0, 0, 0, $gm, $gd, $gy), 86400);
}

function fa_digits_to_en(string $s): string
{
    return strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

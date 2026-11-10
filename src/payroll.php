<?php
declare(strict_types=1);

const SHEET_INFO = 'اطلاعات پایه';
const SHEET_WORK = 'کارکرد ماه';
const COLOR_INACTIVE = ['FFFF0000', 'FFFF9900']; // قرمز و نارنجی = غیرفعال

const MSG_BAD_PERSONNEL = 'اطلاعات این پرسنل به‌درستی در دیتابیس وارد نشده است.';
const MSG_NO_WORK       = 'کارکرد این ماه ثبت نشده است.';
const MSG_INACTIVE      = 'حساب شما غیرفعال است. برای پیگیری با واحد منابع انسانی تماس بگیرید.';
const MSG_NOT_FOUND     = 'کد ملی یا کد پرسنلی اشتباه است.';
const MSG_BAD_NATIONAL  = 'کد ملی وارد شده معتبر نیست.';
const MSG_NO_PHONE      = 'شماره موبایل برای این حساب ثبت نشده است. با واحد منابع انسانی تماس بگیرید.';
const MSG_PHONE_MISMATCH = 'شماره موبایل با اطلاعات ثبت‌شده مطابقت ندارد.';

// برچسب‌ها و ترتیب دقیقاً مطابق شیت «فیش حقوقی»
const SLIP_PAY = [
    'G10' => 'مزد ثابت ماهانه',
    'G11' => 'حق مسکن، بن کارگری',
    'G12' => 'حق تأهل و اولاد',
    'G13' => 'حق سنوات و یک دوم پاداش پایان سال',
    'G14' => 'حق سرپرستی',
    'G15' => 'اضافه کار / شب / تعطیل / جمعه کاری',
    'G16' => 'معوقات',
    'G17' => 'پاداش عملکرد',
    'G18' => 'بازخرید مرخصی / آف',
];
const SLIP_DED = [
    'M10' => 'فیش سفارش غذا',
    'M11' => 'وام',
    'M12' => 'مانده وام (در جمع کسورات حساب نمی‌شود)',
    'M13' => 'مساعده',
    'M14' => 'حق بیمه',
    'M15' => 'اسکان و سرویس',
    'M16' => 'کسر کار و جریمه',
];

/* ---------- نرمال‌سازی ---------- */

function ident($v): string
{
    if ($v === null) {
        return '';
    }
    if (is_float($v) || is_int($v)) {
        return (string)(int)round((float)$v);
    }
    $s = trim((string)$v);
    if (preg_match('/^\d+\.0$/', $s)) {
        $s = substr($s, 0, -2);
    }
    return fa_digits_to_en($s);
}

function digits(string $s): string
{
    return preg_replace('/\D/', '', fa_digits_to_en($s));
}

/** کد ملی معتبر (۱۰ رقم)، خالی/صفر/نامعلوم = ZERO_NATIONAL؛ مقدار غیرقابل‌قبول = '' */
function national_code_norm($v): string
{
    $s = ident($v);
    if ($s === '' || $s === '0' || $s === ZERO_NATIONAL) {
        return ZERO_NATIONAL;
    }
    if (preg_match('/^\d{1,10}$/', $s)) {
        return str_pad($s, 10, '0', STR_PAD_LEFT);
    }
    return '';
}

function phone_norm(string $s): string
{
    $d = digits($s);
    return strlen($d) >= 10 ? substr($d, -10) : $d;
}

/* ---------- خواندن و ساختن رکوردها از فایل ---------- */

/** نمای ساده‌ی شیت: [ردیف][ستون] => مقدار */
function sheet_values(array $rows): array
{
    $out = [];
    foreach ($rows as $r => $cells) {
        foreach ($cells as $c => $cell) {
            $out[$r][$c] = $cell['v'] ?? null;
        }
    }
    return $out;
}

/** پیدا کردن ستون‌های هویتی/بانکی تکمیلی از روی عنوان ستون‌های شیت اطلاعات پایه */
function employee_profile_import_header_map(array $rows): array
{
    $aliases = employee_profile_header_aliases();
    $reverse = [];
    $normalizeHeader = static function (string $name): string {
        $name = preg_replace('/[：:()（）\[\]ـ_\-]/u', '', $name) ?? $name;
        return XlsxReader::norm($name);
    };
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) $reverse[$normalizeHeader($name)] = $field;
    }
    $map = [];
    // عنوان‌ها معمولاً در دو ردیف نخست‌اند؛ ردیف سوم هم بررسی می‌شود چون در بعضی نسخه‌ها هدر با داده شروع می‌شود.
    foreach ($rows as $rowNo => $cells) {
        if ((int)$rowNo > 3) break;
        foreach ($cells as $col => $cell) {
            $label = trim((string)($cell['v'] ?? ''));
            if ($label === '') continue;
            $field = $reverse[$normalizeHeader($label)] ?? null;
            if ($field && !array_key_exists($field, $map)) $map[$field] = (int)$col;
        }
    }
    return $map;
}

/** تبدیل مقادیر سلول‌های تکمیلی؛ تاریخ‌های عددی اکسل به تاریخ شمسی تبدیل می‌شوند. */
function employee_profile_import_value($value, string $field): ?string
{
    if ($value === null) return null;
    if (is_bool($value)) $value = $value ? 'بله' : 'خیر';
    if (is_float($value) || is_int($value)) {
        if ($field === 'birth_date' && $value >= 20000 && $value <= 80000) {
            $days = (int)floor((float)$value);
            $date = (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))->modify('+' . $days . ' days');
            return jalali_str(jalali_from_gregorian((int)$date->format('Y'), (int)$date->format('n'), (int)$date->format('j')));
        }
        $value = ident($value);
    }
    $value = trim(fa_digits_to_en((string)$value));
    return $value === '' ? null : mb_substr($value, 0, 1000);
}

/**
 * خواندن یک جزء کد پرسنلی از سلول اکسل به‌صورت متن.
 * N جزء اول (کد تاریخ شروع) و F پسوند کد است؛ صفرهای ابتدایی باید حفظ شوند.
 */
function personnel_code_component(array $cell, int $minimumWidth): string
{
    $value = $cell['v'] ?? null;
    if ($value === null || $value === '') return '';

    // مقدار متنی دست‌نخورده می‌ماند؛ سلول عددی از نمایش قالب‌بندی‌شده‌ی XLSX خوانده می‌شود.
    if (is_string($value)) {
        $text = trim(fa_digits_to_en($value));
    } else {
        $text = trim(fa_digits_to_en((string)($cell['display'] ?? ident($value))));
    }
    $text = digits($text);
    if ($text === '') return '';

    // جزء N حداقل ۶ رقم و جزء F حداقل ۳ رقم است.
    // نمونه: N=050101 و F=002 → کد کامل 050101002.
    if (strlen($text) < $minimumWidth) {
        $text = str_pad($text, $minimumWidth, '0', STR_PAD_LEFT);
    }
    return $text;
}

/** خواندن اطلاعات پایه‌ی یک کارمند از ردیف شیت */
function base_record(array $cells, int $row, array $profileHeaderMap = []): array
{
    $g = fn(int $c) => $cells[$c]['v'] ?? null;
    $present = fn(int $c) => array_key_exists($c, $cells) && ($cells[$c]['v'] ?? null) !== null;
    $nameFill = $cells[2]['fill'] ?? '';
    $startCode = personnel_code_component($cells[13] ?? [], 6); // N: جزء اول کد پرسنلی و تاریخ شروع
    $startDate = in_array(strlen($startCode), [6, 8], true) ? jalali_from_start_code($startCode) : null;
    if ($startDate && !jalali_parse(jalali_str($startDate))) $startDate = null;
    $startValid = $startDate !== null;
    $suffix = personnel_code_component($cells[5] ?? [], 3); // F: جزء دوم کد پرسنلی
    $personnelOk = $startValid && $suffix !== '';
    $name = trim((string)$g(2));
    $salaryPresent = $present(30) || $present(24) || $present(29);
    $salary = $g(30) !== null ? (float)$g(30) : (float)($g(24) ?? 0) + (float)($g(29) ?? 0); // AE یا Y+AD
    $baseColumns = ['S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'AD' => 29];
    $base = $basePresent = [];
    foreach ($baseColumns as $key => $col) {
        $base[$key] = (float)($g($col) ?? 0);
        $basePresent[$key] = $present($col);
    }
    $extra = [];
    foreach ($profileHeaderMap as $field => $col) {
        $value = employee_profile_import_value($g($col), $field);
        if ($value !== null) $extra[$field] = $value;
    }
    return [
        'row' => $row,
        'name' => $name,
        'name_key' => XlsxReader::norm($name),
        'national' => national_code_norm($g(3)),
        'national_raw' => ident($g(3)),
        'national_present' => $present(3) && ident($g(3)) !== '',
        // ترتیب کد حیاتی است: N سپس F؛ هرگز برعکس یا به‌صورت بخش مستقل ذخیره نشود.
        'personnel' => $personnelOk ? ($startCode . $suffix) : '',
        'personnel_ok' => $personnelOk,
        'start_code' => $startCode,
        'start_valid' => $startValid,
        'hire_date' => $startDate ? jalali_str($startDate) : '',
        'start_key' => $startValid ? (int)jalali_str($startDate) : 0,
        'phone' => phone_norm(ident($g(12))),
        'phone_present' => $present(12) && ident($g(12)) !== '',
        'post' => trim((string)$g(14)),
        'post_present' => $present(14) && trim((string)$g(14)) !== '',
        'sheba' => ident($g(35)),
        'sheba_present' => $present(35) && ident($g(35)) !== '',
        'salary' => (int)round($salary),
        'salary_present' => $salaryPresent,
        'color' => $nameFill,
        'inactive' => in_array(strtoupper($nameFill), COLOR_INACTIVE, true),
        'base' => $base,
        'base_present' => $basePresent,
        'extra' => $extra,
    ];
}

/** ردیف کارکرد ماه برای یک نام (طبق VLOOKUP — اولین ردیف) */
function work_record(array $cells): array
{
    $g = fn(int $c) => $cells[$c]['v'] ?? null;
    return [
        'days'         => (float)($g(4) ?? 0),       // E  کارکرد روز
        'overtime'     => (float)($g(8) ?? 0),       // I  معوقات
        'bonus'        => (float)($g(9) ?? 0),       // J  پاداش
        'leave_amt'    => (float)($g(11) ?? 0),      // L  آف و مرخصی
        'penalty'      => (float)($g(12) ?? 0),      // M  جریمه
        'absence'      => (float)($g(13) ?? 0),      // N  کسر کار
        'food'         => (float)($g(14) ?? 0),      // O  فیش غذا
        'loan'         => (float)($g(15) ?? 0),      // P  وام
        'loan_balance' => (float)($g(16) ?? 0),      // Q  مانده وام
        'advance'      => (float)($g(17) ?? 0),      // R  مساعده
        'insurance'    => (float)($g(18) ?? 0),      // S  حق بیمه
        'sheba'        => ident($g(20)),             // U  شماره شبا
        'section'      => trim((string)($g(27) ?? '')), // AB سکشن کارمند
    ];
}

/**
 * محاسبه‌ی فیش دقیقاً طبق فرمول‌های شیت «فیش حقوقی»
 * (ضریب ۳۱ ثابت است و مقدار در ایام کارکرد ضرب می‌شود)
 */
function compute_slip(array $base, ?array $work): array
{
    $days = $work['days'] ?? 0.0;
    $k = $days / 31;
    $v = [];
    $v['G10'] = $base['S'] * $k;
    $v['G11'] = $base['U'] * $k;
    $v['G12'] = $base['V'] * $k;
    $v['G13'] = $base['T'] * $k;
    $v['G14'] = 0.0;
    $v['G15'] = ($base['W'] + $base['AD']) * $k;
    $v['G16'] = $work['overtime'] ?? 0.0;
    $v['G17'] = $work['bonus'] ?? 0.0;
    $v['G18'] = $work['leave_amt'] ?? 0.0;
    $v['G19'] = array_sum(array_map(fn($c) => $v[$c], array_keys(SLIP_PAY)));

    $w = $work ?? [];
    $v['M10'] = $w['food'] ?? 0.0;
    $v['M11'] = $w['loan'] ?? 0.0;
    $v['M12'] = $w['loan_balance'] ?? 0.0;
    $v['M13'] = $w['advance'] ?? 0.0;
    $v['M14'] = $w['insurance'] ?? 0.0;
    $v['M15'] = 0.0;
    $v['M16'] = ($w['penalty'] ?? 0.0) + ($w['absence'] ?? 0.0);
    $v['M19'] = $v['M10'] + $v['M11'] + $v['M13'] + $v['M14'] + $v['M15'] + $v['M16'];
    $v['E20'] = $v['G19'] - $v['M19'];
    $v['days'] = $days;
    return $v;
}

/* ---------- ایمپورت فایل ماهانه ---------- */

/** بازیابی مبالغ پایه از آخرین فیش قدیمی در صورتی که پرونده‌ی تکمیلی پیش از این وجود نداشته باشد. */
function payroll_recover_base_from_latest_slip(array $employee): array
{
    $row = one('SELECT s.data FROM payslips s JOIN periods p ON p.id = s.period_id
        WHERE s.employee_id = ? AND s.has_work = 1
        ORDER BY p.year DESC, p.month DESC LIMIT 1', [(int)$employee['id']]);
    $data = json_decode((string)($row['data'] ?? ''), true);
    $v = is_array($data['values'] ?? null) ? $data['values'] : [];
    $days = (float)($v['days'] ?? 0);
    $k = $days / 31;
    if ($k <= 0) return [];
    return [
        'S' => (float)($v['G10'] ?? 0) / $k,
        'T' => (float)($v['G13'] ?? 0) / $k,
        'U' => (float)($v['G11'] ?? 0) / $k,
        'V' => (float)($v['G12'] ?? 0) / $k,
        // در فیش مقدار W و AD با هم نمایش داده می‌شود؛ جمع آن‌ها حفظ می‌شود.
        'W' => (float)($v['G15'] ?? 0) / $k,
        'AD' => 0.0,
    ];
}

/**
 * کلید تشخیص نام برای پرونده‌های تکراری ناشی از استخدام مجدد.
 * عدد انتهایی مانند «2» در نام اکسل، شناسه‌ی کمکی ردیف است و بخشی از نام واقعی نیست.
 */
function payroll_name_identity_key(string $name): string
{
    $key = XlsxReader::norm(fa_digits_to_en($name));
    $key = preg_replace('/[0-9]+$/u', '', $key);
    return trim((string)$key);
}

/** دو رکورد فقط در صورت نبود تضاد کد ملی و وجود حداقل یک نشانه هویتی مشترک، یک نفر محسوب می‌شوند. */
function payroll_same_employee_identity(array $candidate, array $rec): bool
{
    $candidateNational = national_code_norm($candidate['national_code'] ?? '');
    $recordNational = (string)($rec['national'] ?? ZERO_NATIONAL);
    $candidateValidNational = $candidateNational !== '' && $candidateNational !== ZERO_NATIONAL;
    $recordValidNational = $recordNational !== '' && $recordNational !== ZERO_NATIONAL;

    // دو کد ملی معتبر و متفاوت، حتی با نام مشابه، به معنی دو هویت جداگانه است.
    if ($candidateValidNational && $recordValidNational && $candidateNational !== $recordNational) {
        return false;
    }
    if ($candidateValidNational && $recordValidNational && $candidateNational === $recordNational) {
        return true;
    }

    $candidatePersonnel = digits(ident($candidate['personnel_code'] ?? ''));
    $recordPersonnel = digits(ident($rec['personnel'] ?? ''));
    if ($candidatePersonnel !== '' && $recordPersonnel !== '' && $candidatePersonnel === $recordPersonnel) {
        return true;
    }

    $candidateName = payroll_name_identity_key((string)($candidate['name'] ?? ''));
    $recordName = payroll_name_identity_key((string)($rec['name'] ?? ''));
    if ($candidateName === '' || $candidateName !== $recordName) return false;

    // اگر کد ملی ناقص است، اختلاف دو شماره موبایل ثبت‌شده مانع ادغام دو هم‌نام می‌شود.
    $candidatePhone = phone_norm(ident($candidate['phone'] ?? ''));
    $recordPhone = phone_norm(ident($rec['phone'] ?? ''));
    if ($candidatePhone !== '' && $recordPhone !== '' && $candidatePhone !== $recordPhone) return false;

    return true;
}

/** خلاصه‌ی امن و قابل جست‌وجوی پرونده‌های متداخل برای نمایش در پیام خطا. */
function payroll_profile_conflict_details(array $profiles): string
{
    $details = [];
    foreach ($profiles as $profile) {
        $id = (int)($profile['id'] ?? 0);
        $name = trim((string)($profile['name'] ?? 'نام ثبت نشده'));
        $national = national_code_norm($profile['national_code'] ?? '');
        if ($national === '' || $national === ZERO_NATIONAL) $national = 'ثبت نشده';
        $personnel = digits(ident($profile['personnel_code'] ?? ''));
        if ($personnel === '') $personnel = 'ثبت نشده';
        $latest = (int)($profile['_previous_in_latest'] ?? $profile['in_latest'] ?? 0) === 1
            ? 'در آخرین اکسل: بله' : 'در آخرین اکسل: خیر';
        $details[] = 'شناسه پرونده #' . $id . '؛ نام: ' . $name . '؛ کد ملی: ' . $national .
            '؛ کد پرسنلی N+F: ' . $personnel . '؛ ' . $latest;
    }
    return $details ? implode(' | ', $details) : 'جزئیات پرونده در دسترس نیست';
}

/** پیام خطای دارای شماره ردیف اکسل و شناسه‌ی پرونده‌های متداخل. */
function payroll_multiple_active_profile_error(array $rec, array $profiles): RuntimeException
{
    $row = max(0, (int)($rec['row'] ?? 0));
    $rowLabel = $row > 0 ? 'ردیف ' . $row : 'ردیف نامشخص';
    $national = (string)($rec['national'] ?? '');
    if ($national === '' || $national === ZERO_NATIONAL) $national = 'ثبت نشده/نامعتبر';
    $personnel = (string)($rec['personnel'] ?? '');
    if ($personnel === '') $personnel = 'ناقص یا ناموجود';

    return new RuntimeException(
        'در شیت «اطلاعات پایه»، ' . $rowLabel . ' برای «' . (string)($rec['name'] ?? 'نامشخص') .
        '» بیش از یک پرونده فعال با اطلاعات این ردیف تطبیق داده شد. ' .
        'کد ملی ردیف اکسل: ' . $national . '؛ کد پرسنلی ترکیبی N+F: ' . $personnel . '. ' .
        'پرونده‌های پیدا شده در دیتابیس: ' . payroll_profile_conflict_details($profiles) . '. ' .
        'لطفاً همین ردیف اکسل و شناسه پرونده‌های بالا را بررسی کنید. هیچ تغییری از این بارگذاری ذخیره نشد.'
    );
}

/**
 * از میان پرونده‌های یک هویت، ابتدا پروفایل فعالِ حاضر در آخرین اکسل موفق را انتخاب می‌کند.
 * اگر تنها یک پرونده فعال وجود داشته باشد همان انتخاب می‌شود؛ در غیر این صورت تعارض با ردیف اکسل گزارش می‌شود.
 * پروفایل غیرفعال برای کارمند فعال هرگز به‌روزرسانی نمی‌شود.
 */
function payroll_select_employee_profile(array $candidates, array $rec): ?array
{
    $unique = [];
    foreach ($candidates as $candidate) {
        if (isset($candidate['id'])) $unique[(int)$candidate['id']] = $candidate;
    }
    $candidates = array_values($unique);
    if (!$candidates) return null;

    $active = array_values(array_filter($candidates, static fn($row) => !(bool)($row['inactive'] ?? 0)));
    if (!empty($rec['inactive']) && $active) {
        $row = max(0, (int)($rec['row'] ?? 0));
        throw new RuntimeException('در شیت «اطلاعات پایه»، ردیف ' . ($row ?: 'نامشخص') . ' («' . $rec['name'] . '») به پرونده فعال متصل شده است. این ردیف تاریخی نادیده گرفته نشد تا اطلاعات پرونده فعال اشتباهاً تغییر نکند؛ وضعیت رنگ/غیرفعال بودن ردیف را بررسی کنید. هیچ تغییری ذخیره نشد.');
    }
    if (count($active) === 1) return $active[0];
    if (count($active) > 1) {
        // in_latest قبل از شروع ایمپورت نگهداری می‌شود؛ تنها پرونده‌ی فعالِ موجود در آخرین اکسل قبلی، انتخاب قطعی است.
        $latestActive = array_values(array_filter($active, static function ($row) {
            return (int)($row['_previous_in_latest'] ?? $row['in_latest'] ?? 0) === 1;
        }));
        if (count($latestActive) === 1) return $latestActive[0];
        throw payroll_multiple_active_profile_error($rec, $active);
    }

    // اگر رکورد ورودی فعال است ولی فقط پرونده‌های قدیمی غیرفعال وجود دارند، آن‌ها را دست نمی‌زنیم.
    if (empty($rec['inactive'])) return null;

    $sameStart = array_values(array_filter($candidates, static function ($row) use ($rec) {
        $oldStart = digits(ident($row['start_code'] ?? ''));
        if ($oldStart !== '' && strlen($oldStart) < 6) $oldStart = str_pad($oldStart, 6, '0', STR_PAD_LEFT);
        return $oldStart !== '' && $oldStart === (string)($rec['start_code'] ?? '');
    }));
    if (count($sameStart) === 1) return $sameStart[0];
    if (count($candidates) === 1) return $candidates[0];
    $row = max(0, (int)($rec['row'] ?? 0));
    throw new RuntimeException('در شیت «اطلاعات پایه»، ردیف ' . ($row ?: 'نامشخص') . ' («' . $rec['name'] . '») به چند پرونده غیرفعال احتمالی متصل شده و تشخیص پرونده تاریخی دقیق ممکن نیست. شناسه پرونده‌ها: ' . payroll_profile_conflict_details($candidates) . '. هیچ تغییری ذخیره نشد.');
}

/**
 * فایل اکسل را می‌خواند و در دوره‌ی (سال/ماه) ذخیره می‌کند.
 * اطلاعات غیرخالی با فایل تازه ادغام می‌شوند؛ فیلد خالی، سابقه و مقدار قبلی را پاک نمی‌کند.
 */
function payroll_import(string $path, int $year, int $month, string $fileName, string $by): array
{
    $x = new XlsxReader($path);
    foreach ([SHEET_INFO, SHEET_WORK] as $need) {
        if ($x->findSheet($need) === null) {
            throw new RuntimeException('شیت «' . $need . '» در فایل پیدا نشد.');
        }
    }
    $infoRows = $x->readSheet(SHEET_INFO);
    $workRows = $x->readSheet(SHEET_WORK);
    $profileHeaderMap = employee_profile_import_header_map($infoRows);

    $report = [
        'base_rows' => 0, 'employees' => 0, 'active' => 0, 'inactive' => 0,
        'new' => 0, 'updated' => 0, 'missing_from_file' => 0,
        'duplicates' => [], 'skipped_inactive_rehire_rows' => [], 'missing_work' => [], 'name_mismatch' => [],
        'personnel_incomplete' => [], 'national_invalid' => [], 'zero_national' => 0,
        'zero_national_no_phone' => [], 'error_cells' => [],
        'profile_fields_detected' => array_values(array_unique(array_keys($profileHeaderMap))),
        'profile_data_updated' => 0, 'preserved_existing_fields' => 0,
        'sections_detected' => [], 'section_assignments' => [], 'section_missing' => [],
    ];

    // ۱) کارکرد ماه: ردیف‌های این شیت با همان شماره ردیف به «اطلاعات پایه» متناظرند.
    // وضعیت استخدام فقط از رنگ سلول نام در ستون C شیت اطلاعات پایه تعیین می‌شود.
    $work = [];
    $workNames = [];
    $sectionByBaseRow = [];
    foreach ($workRows as $r => $cells) {
        if ($r < 6) {
            continue;
        }
        $raw = $cells[1]['v'] ?? null;
        if ($raw === null || trim((string)$raw) === '') {
            continue;
        }
        $key = XlsxReader::norm((string)$raw);
        if ($key === '') {
            continue;
        }
        $workNames[$key][] = (string)$raw;

        // رنگ قرمز/نارنجی سلول نام در اطلاعات پایه یعنی دوره همکاری خاتمه‌یافته.
        $baseNameFill = strtoupper((string)($infoRows[$r][2]['fill'] ?? ''));
        $baseRowInactive = in_array($baseNameFill, COLOR_INACTIVE, true);

        $sectionValue = trim((string)($cells[27]['v'] ?? '')); // ستون AB شیت کارکرد ماه
        // مقدار صفر، مقدار خالی یا سکشن ردیف غیرفعال نباید به پرونده فعال منتقل شود.
        if (!$baseRowInactive && $sectionValue !== '' && $sectionValue !== '0') {
            $sectionByBaseRow[(int)$r] = $sectionValue;
        }

        // اگر نام در چند دوره تکرار شده باشد، ردیف متناظر با پرونده فعال بر ردیف قرمز/نارنجی اولویت دارد.
        if (!isset($work[$key]) || !$baseRowInactive) {
            $work[$key] = work_record($cells);
            $work[$key]['section'] = (!$baseRowInactive && $sectionValue !== '' && $sectionValue !== '0')
                ? $sectionValue
                : '';
        }
        foreach ([4, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18] as $c) {
            if (!empty($cells[$c]['err'])) {
                $report['error_cells'][] = 'کارکرد ماه!' . $r . ':' . $c;
            }
        }
    }

    // ۲) اطلاعات پایه: یک رکورد برای هر نام (جدیدترین تاریخ شروع به کار)
    $byName = [];
    $baseNames = [];
    foreach ($infoRows as $r => $cells) {
        if ($r < 3) {
            continue;
        }
        $rec = base_record($cells, $r, $profileHeaderMap);
        if ($rec['name'] === '' || $rec['name'] === 'نام و نام خانوادگی') {
            continue;
        }
        $report['base_rows']++;
        $baseNames[$rec['name_key']] = true;
        $k = $rec['name_key'];
        $byName[$k][] = $rec;
    }
    $records = [];
    foreach ($byName as $k => $list) {
        $validNationalCodes = [];
        foreach ($list as $rowRecord) {
            $nc = (string)($rowRecord['national'] ?? '');
            if ($nc !== '' && $nc !== ZERO_NATIONAL) $validNationalCodes[$nc] = true;
        }
        // هم‌نام بودن با کد ملی معتبر متفاوت، به معنی دو کارمند مستقل است؛ هیچ‌کدام حذف نمی‌شوند.
        if (count($list) > 1 && count($validNationalCodes) > 1) {
            foreach ($list as $rowRecord) $records[] = $rowRecord;
            $report['duplicates'][] = [
                'name' => $list[0]['name'],
                'rows' => array_map(fn($x) => $x['row'], $list),
                'chosen_row' => null,
                'chosen_color' => '',
                'status' => 'هم‌نام با کد ملی متفاوت؛ پرونده‌ها جدا نگه داشته شدند',
            ];
            continue;
        }
        usort($list, static function ($a, $b) {
            if ((bool)$a['inactive'] !== (bool)$b['inactive']) {
                return (int)$a['inactive'] <=> (int)$b['inactive']; // فعال قبل از غیرفعال
            }
            return [$b['start_key'], $b['row']] <=> [$a['start_key'], $a['row']];
        });
        $chosen = $list[0];
        if (count($list) > 1) {
            $report['duplicates'][] = [
                'name' => $chosen['name'],
                'rows' => array_map(fn($x) => $x['row'], $list),
                'chosen_row' => $chosen['row'],
                'chosen_color' => $chosen['color'],
                'status' => $chosen['inactive'] ? 'غیرفعال' : 'فعال',
            ];
            if (empty($chosen['inactive'])) {
                foreach ($list as $ignoredRow) {
                    if (!empty($ignoredRow['inactive'])) {
                        $report['skipped_inactive_rehire_rows'][] = [
                            'name' => $ignoredRow['name'], 'row' => (int)$ignoredRow['row'],
                            'active_name' => $chosen['name'], 'active_row' => (int)$chosen['row'],
                            'reason' => 'ردیف غیرفعال هم‌نام؛ ردیف فعال انتخاب شد',
                        ];
                    }
                }
            }
        }
        $records[] = $chosen;
    }

    // اگر نام یک نفر با پسوند عددی در اکسل تکرار شده و فقط یکی از ردیف‌ها فعال است،
    // ردیف غیرفعالِ استخدام قبلی کنار گذاشته می‌شود و پرونده‌ی فعال به‌روزرسانی خواهد شد.
    $identityGroups = [];
    foreach ($records as $index => $rec) {
        $identityKey = payroll_name_identity_key((string)$rec['name']);
        if ($identityKey !== '') $identityGroups[$identityKey][] = $index;
    }
    $skipRecordIndexes = [];
    foreach ($identityGroups as $indexes) {
        if (count($indexes) < 2) continue;
        $group = array_map(static fn($i) => $records[$i], $indexes);
        $activeRows = array_values(array_filter($group, static fn($r) => empty($r['inactive'])));
        $inactiveRows = array_values(array_filter($group, static fn($r) => !empty($r['inactive'])));
        $validNationalCodes = [];
        foreach ($group as $row) {
            $nc = (string)($row['national'] ?? '');
            if ($nc !== '' && $nc !== ZERO_NATIONAL) $validNationalCodes[$nc] = true;
        }
        // اگر کدهای ملی معتبر متفاوت باشند، هم‌نامی برای ادغام کافی نیست.
        if (count($activeRows) === 1 && count($inactiveRows) >= 1 && count($validNationalCodes) <= 1) {
            $activeRow = $activeRows[0];
            foreach ($indexes as $index) {
                if (!empty($records[$index]['inactive'])) {
                    $skipRecordIndexes[$index] = true;
                    $report['skipped_inactive_rehire_rows'][] = [
                        'name' => $records[$index]['name'],
                        'row' => (int)$records[$index]['row'],
                        'active_name' => $activeRow['name'],
                        'active_row' => (int)$activeRow['row'],
                        'reason' => 'پرونده غیرفعالِ استخدام قبلی؛ پروفایل فعال انتخاب شد',
                    ];
                }
            }
        }
    }
    if ($skipRecordIndexes) {
        $records = array_values(array_filter($records, static fn($r, $i) => !isset($skipRecordIndexes[$i]), ARRAY_FILTER_USE_BOTH));
    }

    // وضعیت پرونده‌ها را پیش از صفر کردن in_latest نگه می‌داریم تا سابقه‌ی پروفایل غیرفعال دست‌نخورده بماند.
    $knownEmployeesBeforeImport = all('SELECT * FROM employees');
    // وضعیت حضور در آخرین فایلِ موفق را قبل از صفرکردن in_latest نگه می‌داریم.
    // این وضعیت کمک می‌کند اگر چند پرونده با کد ملی/نام تطبیق شدند، پروفایلی انتخاب شود
    // که در آخرین اکسل معتبر هم وجود داشته و در جست‌وجوی عادی مدیر دیده می‌شود.
    $previousLatestByEmployeeId = [];
    foreach ($knownEmployeesBeforeImport as $knownEmployee) {
        $previousLatestByEmployeeId[(int)$knownEmployee['id']] = (int)($knownEmployee['in_latest'] ?? 0);
    }
    $restorePreviousLatestFlags = static function (array $rows) use ($previousLatestByEmployeeId): array {
        foreach ($rows as &$row) {
            $id = (int)($row['id'] ?? 0);
            $row['_previous_in_latest'] = array_key_exists($id, $previousLatestByEmployeeId)
                ? $previousLatestByEmployeeId[$id]
                : (int)($row['in_latest'] ?? 0);
        }
        unset($row);
        return $rows;
    };
    $preservedInactiveLatest = [];
    // هر پروفایل غیرفعالی که هم‌هویت با یک ردیف فعال ورودی باشد کاملاً از به‌روزرسانی این فایل خارج می‌ماند.
    $activeIncomingRecords = array_values(array_filter($records, static fn($r) => empty($r['inactive'])));
    foreach ($knownEmployeesBeforeImport as $oldProfileRow) {
        if (empty($oldProfileRow['inactive'])) continue;
        foreach ($activeIncomingRecords as $activeIncoming) {
            if (payroll_same_employee_identity($oldProfileRow, $activeIncoming)) {
                $preservedInactiveLatest[(int)$oldProfileRow['id']] = (int)($oldProfileRow['in_latest'] ?? 0);
                break;
            }
        }
    }
    $recordsAfterDbStatusCheck = [];
    foreach ($records as $rec) {
        if (!empty($rec['inactive'])) {
            $activeMatches = array_values(array_filter($knownEmployeesBeforeImport, static function ($candidate) use ($rec) {
                return empty($candidate['inactive']) && payroll_same_employee_identity($candidate, $rec);
            }));
            if (count($activeMatches) > 1) {
                // اگر چند پرونده فعالِ قدیمی وجود دارد، پروفایلی که در آخرین اکسل موفق حاضر بوده اولویت دارد.
                $latestActiveMatches = array_values(array_filter($activeMatches, static fn($row) => (int)($row['in_latest'] ?? 0) === 1));
                if (count($latestActiveMatches) === 1) $activeMatches = $latestActiveMatches;
            }
            if (count($activeMatches) > 1) {
                throw payroll_multiple_active_profile_error($rec, $activeMatches);
            }
            if (count($activeMatches) === 1) {
                $inactiveMatches = array_values(array_filter($knownEmployeesBeforeImport, static function ($candidate) use ($rec) {
                    return !empty($candidate['inactive']) && payroll_same_employee_identity($candidate, $rec);
                }));
                foreach ($inactiveMatches as $oldInactive) {
                    $preservedInactiveLatest[(int)$oldInactive['id']] = (int)($oldInactive['in_latest'] ?? 0);
                }
                $report['skipped_inactive_rehire_rows'][] = [
                    'name' => $rec['name'], 'row' => (int)$rec['row'],
                    'active_profile_id' => (int)$activeMatches[0]['id'],
                    'reason' => 'پرونده فعال هم‌هویت در سامانه وجود دارد؛ ردیف غیرفعال نادیده گرفته شد',
                ];
                continue;
            }
        }
        $recordsAfterDbStatusCheck[] = $rec;
    }
    $records = $recordsAfterDbStatusCheck;

    // کد ترکیبی N+F باید در همان فایل هم یکتا باشد؛ تکرار آن می‌تواند دو پرونده را به هم متصل کند.
    $incomingPersonnelCodes = [];
    foreach ($records as $rec) {
        if (empty($rec['personnel_ok'])) continue;
        $code = (string)$rec['personnel'];
        if (isset($incomingPersonnelCodes[$code])) {
            $first = $incomingPersonnelCodes[$code];
            throw new RuntimeException(
                'کد پرسنلی ترکیبی N+F («' . $code . '») در شیت «اطلاعات پایه» تکرار شده است: ردیف ' .
                (int)$first['row'] . ' (' . $first['name'] . ') و ردیف ' . (int)$rec['row'] .
                ' (' . $rec['name'] . '). هر دو ردیف را از نظر سلول‌های N و F بررسی کنید. هیچ تغییری ذخیره نشد.'
            );
        }
        $incomingPersonnelCodes[$code] = ['name' => (string)$rec['name'], 'row' => (int)$rec['row']];
    }

    // اتصال سکشن با شماره ردیف متناظر در «اطلاعات پایه» انجام می‌شود، نه صرفاً نام.
    // بنابراین ردیف قرمز/نارنجی استخدام قبلی هرگز سکشن پرونده فعال را آلوده نمی‌کند.
    // مقدار خالی/صفر اکسل سکشن قبلی را پاک نمی‌کند و قفل ویرایش دستی مدیر در merge رعایت می‌شود.
    $detectedSections = [];
    foreach ($records as &$sectionRecord) {
        $section = trim((string)($sectionByBaseRow[(int)$sectionRecord['row']] ?? ''));
        if ($section !== '' && empty($sectionRecord['inactive'])) {
            $sectionRecord['extra']['section'] = $section;
            $detectedSections[$section] = true;
            $report['section_assignments'][] = [
                'name' => (string)$sectionRecord['name'],
                'row' => (int)$sectionRecord['row'],
                'section' => $section,
            ];
        } elseif (empty($sectionRecord['inactive'])) {
            $report['section_missing'][] = [
                'name' => (string)$sectionRecord['name'],
                'row' => (int)$sectionRecord['row'],
            ];
        }
    }
    unset($sectionRecord);
    $report['sections_detected'] = array_values(array_keys($detectedSections));

    // ۳) بررسی‌های داده
    foreach ($records as $rec) {
        if ($rec['inactive']) {
            $report['inactive']++;
        } else {
            $report['active']++;
        }
        if (!$rec['personnel_ok']) {
            $report['personnel_incomplete'][] = ['name' => $rec['name'], 'row' => $rec['row']];
        }
        if ($rec['national'] === '' ) {
            $report['national_invalid'][] = ['name' => $rec['name'], 'raw' => $rec['national_raw'], 'row' => $rec['row']];
        }
        if ($rec['national'] === ZERO_NATIONAL) {
            $report['zero_national']++;
            if ($rec['phone'] === '') {
                $report['zero_national_no_phone'][] = $rec['name'];
            }
        }
        if (!$rec['inactive'] && !isset($work[$rec['name_key']])) {
            $report['missing_work'][] = $rec['name'];
        }
    }
    foreach ($workNames as $k => $names) {
        if (!isset($baseNames[$k])) {
            $report['name_mismatch'][] = ['name' => $names[0], 'reason' => 'در اطلاعات پایه پیدا نشد'];
        } elseif (count(array_unique($names)) === 1 && $names[0] !== trim($names[0])) {
            $report['name_mismatch'][] = ['name' => $names[0], 'reason' => 'فاصله‌ی انتهایی در شیت کارکرد'];
        }
    }
    $report['employees'] = count($records);

    // جداول جانبی پیش از تراکنش ساخته می‌شوند؛ CREATE TABLE در MySQL می‌تواند تراکنش را commit کند.
    employee_profiles_ensure();
    employee_history_ensure();

    // ۴) ذخیره در یک تراکنش؛ هیچ رکورد کارمند یا دوره‌ی قبلی حذف نمی‌شود.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO periods (year, month, file_name, uploaded_by, uploaded_at, report)
           VALUES (?, ?, ?, ?, NOW(), ?)
           ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), uploaded_by = VALUES(uploaded_by),
             uploaded_at = NOW(), report = VALUES(report)',
            [$year, $month, $fileName, $by, json_encode($report, JSON_UNESCAPED_UNICODE)]);
        $periodId = (int)one('SELECT id FROM periods WHERE year = ? AND month = ?', [$year, $month])['id'];

        // فیش‌های همان دوره حذف نمی‌شوند؛ هر فیش با کلید یکتای کارمند/دوره به‌روزرسانی می‌شود.
        // بنابراین بارگذاری مجدد یا ورود تکراری هیچ فیش قبلی را ناخواسته پاک نمی‌کند.
        $periodEmployeeSeen = [];

        $before = (int)one('SELECT COUNT(*) AS c FROM employees')['c'];
        q('UPDATE employees SET in_latest = 0');
        // پرونده‌ی غیرفعالِ استخدام قبلی حتی از نظر پرچم حضور در آخرین فایل هم تغییر نمی‌کند.
        foreach ($preservedInactiveLatest as $inactiveId => $wasLatest) {
            q('UPDATE employees SET in_latest = ? WHERE id = ?', [$wasLatest, $inactiveId]);
        }

        foreach ($records as $rec) {
            $candidateKey = $rec['personnel_ok'] ? 'PC:' . $rec['personnel'] : 'NAME:' . $rec['name_key'];

            // انتخاب پرونده بر اساس هویت و وضعیت: پروفایل فعال بر پروفایل غیرفعال اولویت قطعی دارد.
            // به این ترتیب استخدام مجدد، سابقه‌ی قطع همکاری را بازنویسی نمی‌کند.
            $candidateRows = [];
            if ($rec['national_present'] && $rec['national'] !== '' && $rec['national'] !== ZERO_NATIONAL) {
                $candidateRows = $restorePreviousLatestFlags(all('SELECT * FROM employees WHERE national_code = ? ORDER BY inactive ASC, id DESC', [$rec['national']]));
                $candidateRows = array_values(array_filter($candidateRows, static fn($row) => payroll_same_employee_identity($row, $rec)));
            }
            $existing = payroll_select_employee_profile($candidateRows, $rec);

            if (!$existing && $rec['personnel'] !== '') {
                $candidateRows = $restorePreviousLatestFlags(all('SELECT * FROM employees WHERE personnel_code = ? ORDER BY inactive ASC, id DESC', [$rec['personnel']]));
                $candidateRows = array_values(array_filter($candidateRows, static function ($row) use ($rec) {
                    if (payroll_same_employee_identity($row, $rec)) return true;
                    // اگر مدیر قبلاً کد ملی را دستی اصلاح کرده باشد، N+F ثابت همچنان کلید اتصال استخدام است.
                    // این استثنا فقط برای پرونده‌ای مجاز است که خودش قفل دستی کد ملی دارد.
                    $samePc = digits(ident($row['personnel_code'] ?? '')) === digits(ident($rec['personnel'] ?? ''));
                    if (!$samePc) return false;
                    $manual = employee_profile_get((int)$row['id'])['manual'] ?? [];
                    return array_key_exists('national_code', $manual['core'] ?? []);
                }));
                $existing = payroll_select_employee_profile($candidateRows, $rec);
            }

            if (!$existing) {
                $identityName = payroll_name_identity_key((string)$rec['name']);
                if ($identityName !== '') {
                    $candidateRows = $restorePreviousLatestFlags(all('SELECT * FROM employees WHERE name_key LIKE ?', [$identityName . '%']));
                    $candidateRows = array_values(array_filter($candidateRows, static function ($row) use ($identityName, $rec) {
                        return payroll_name_identity_key((string)($row['name'] ?? '')) === $identityName
                            && payroll_same_employee_identity($row, $rec);
                    }));
                    $existing = payroll_select_employee_profile($candidateRows, $rec);
                }
            }

            // اگر فقط پرونده‌ی قدیمی غیرفعال وجود دارد، برای فرد فعال آن پرونده را بازنویسی نمی‌کنیم.
            // در صورت استخدام مجدد و تکرار emp_key، کلید مستقل برای پروفایل فعال ساخته می‌شود.
            if ($rec['personnel'] !== '') {
                $codeOwners = all('SELECT id, name, national_code, personnel_code, inactive FROM employees WHERE personnel_code = ?', [$rec['personnel']]);
                foreach ($codeOwners as $codeOwner) {
                    if ($existing && (int)$codeOwner['id'] === (int)$existing['id']) continue;
                    $sameIdentity = payroll_same_employee_identity($codeOwner, $rec);
                    if ($sameIdentity && !empty($codeOwner['inactive']) && empty($rec['inactive'])) continue;
                    throw new RuntimeException('در شیت «اطلاعات پایه»، ردیف ' . (int)$rec['row'] . ' («' . $rec['name'] . '») از کد پرسنلی N+F «' . $rec['personnel'] . '» استفاده می‌کند، اما این کد قبلاً به پرونده «' . $codeOwner['name'] . '» با شناسه #' . (int)$codeOwner['id'] . ' تعلق دارد. کد ملی و سلول‌های N و F این ردیف را بررسی کنید. هیچ تغییری ذخیره نشد.');
                }
            }

            // کلید شناسایی بر اساس نام اصلی اکسل برای اتصال به شیت کارکرد نگه داشته می‌شود؛
            // نام نمایشی می‌تواند قبلاً توسط مدیر اصلاح شده باشد.
            $workLookupKey = (string)$rec['name_key'];
            $oldProfile = $existing ? employee_profile_get((int)$existing['id']) : ['extra' => [], 'payroll_base' => [], 'manual' => []];
            $oldBase = $oldProfile['payroll_base'] ?? [];
            if ($existing && !$oldBase) {
                $recovered = payroll_recover_base_from_latest_slip($existing);
                if ($recovered) $oldBase = $recovered;
            }

            // مقادیر هویتی خالی در اکسل، اطلاعات قبلی پرونده را پاک نمی‌کنند.
            if ($existing) {
                if (!$rec['personnel_ok']) {
                    $rec['personnel'] = (string)$existing['personnel_code'];
                    $rec['personnel_ok'] = (bool)$existing['personnel_ok'];
                }
                if (!$rec['national_present'] || $rec['national'] === '') {
                    $rec['national'] = (string)$existing['national_code'];
                }
                if (!$rec['phone_present']) $rec['phone'] = (string)$existing['phone'];
                if (!$rec['post_present']) $rec['post'] = (string)$existing['post'];
                if (!$rec['start_valid']) {
                    $rec['start_code'] = (string)$existing['start_code'];
                    $rec['hire_date'] = (string)$existing['hire_date'];
                }
                if (!$rec['sheba_present']) $rec['sheba'] = (string)$existing['sheba'];
            }

            // مبالغ پایه‌ی حقوق و اجزای فرمول فیش نیز به‌صورت ادغامی حفظ می‌شوند.
            foreach ($rec['base'] as $field => $value) {
                if (empty($rec['base_present'][$field]) && array_key_exists($field, $oldBase)) {
                    $rec['base'][$field] = (float)$oldBase[$field];
                    $report['preserved_existing_fields']++;
                }
            }
            if (!$rec['salary_present']) {
                if (array_key_exists('salary', $oldBase)) {
                    $rec['salary'] = (int)$oldBase['salary'];
                } elseif ($existing) {
                    $oldPromo = (int)($existing['promo_salary_add'] ?? 0);
                    $rec['salary'] = max(0, (int)$existing['salary'] - (promo_apply_mode() === 'carry' ? $oldPromo : 0));
                }
                $report['preserved_existing_fields']++;
            }

            // ویرایش‌های ثبت‌شده توسط مدیر بر داده‌ی اکسل اولویت دارند و در بارگذاری مجدد نیز حفظ می‌شوند.
            if ($existing && !empty($oldProfile['manual'])) {
                employee_profile_apply_manual_overrides_to_record($rec, $oldProfile['manual']);
            }

            $importSalary = (int)$rec['salary']; // حقوق منبع فایل، قبل از اعمال افزایش ثبت‌شده در سامانه
            $profileBase = $rec['base'];
            $profileBase['salary'] = $importSalary;

            $currentPost = $rec['post'];
            $currentSalary = $importSalary;
            $currentBase = $rec['base'];
            if ($existing && promo_apply_mode() === 'carry') {
                $add = (int)$existing['promo_salary_add'];
                if ($add > 0) {
                    $currentSalary += $add;
                    $currentBase['S'] += $add; // مزد ثابت ماهانه در فیش
                }
                if ((string)$existing['promo_post'] !== '') {
                    $currentPost = (string)$existing['promo_post'];
                }
            }
            // اگر مدیر عنوان شغلی را دستی اصلاح کرده باشد، حتی در حالت اعمال افزایش ارتقا نیز همان مقدار حفظ می‌شود.
            if (array_key_exists('post', $oldProfile['manual']['core'] ?? [])) {
                $currentPost = (string)$oldProfile['manual']['core']['post'];
            }
            $rec['salary'] = $currentSalary;
            $rec['post'] = $currentPost;
            $rec['base'] = $currentBase;
            $rec['hire_date'] = $rec['hire_date'] !== '' ? $rec['hire_date'] : (string)($existing['hire_date'] ?? '');

            if ($existing) {
                $empId = (int)$existing['id'];
                $oldPersonnelCode = (string)($existing['personnel_code'] ?? '');
                if ($rec['personnel'] !== '' && $oldPersonnelCode !== '' && $oldPersonnelCode !== $rec['personnel']) {
                    // در صورت اصلاح کد قبلی، حساب مدیریتی و ارجاع سرپرست نیز به کد کامل جدید متصل می‌مانند.
                    q('UPDATE admins SET personnel_code = ? WHERE personnel_code = ?', [$rec['personnel'], $oldPersonnelCode]);
                    q('UPDATE employees SET manager_pc = ? WHERE manager_pc = ?', [$rec['personnel'], $oldPersonnelCode]);
                }
                // کلید پرونده‌ی قبلی حفظ می‌شود تا تمام درخواست‌ها و فیش‌های قدیمی به همین کارمند متصل بمانند.
                q('UPDATE employees SET personnel_code = ?, personnel_ok = ?, national_code = ?, name = ?, name_key = ?,
                     phone = ?, post = ?, start_code = ?, hire_date = ?, sheba = ?, salary = ?, color = ?, inactive = ?,
                     in_latest = 1, updated_at = NOW() WHERE id = ?', [
                    $rec['personnel'], $rec['personnel_ok'] ? 1 : 0, $rec['national'], $rec['name'], $rec['name_key'],
                    $rec['phone'], $rec['post'], $rec['start_code'], $rec['hire_date'], $rec['sheba'], $rec['salary'],
                    $rec['color'], $rec['inactive'] ? 1 : 0, $empId,
                ]);
                $report['updated']++;
            } else {
                $key = $candidateKey;
                $keyOwner = one('SELECT id, inactive FROM employees WHERE emp_key = ? LIMIT 1', [$key]);
                if ($keyOwner && empty($rec['inactive']) && !empty($keyOwner['inactive'])) {
                    $key = substr($candidateKey . ':REHIRE:' . $periodId, 0, 120);
                    $suffixNo = 2;
                    while (one('SELECT id FROM employees WHERE emp_key = ? LIMIT 1', [$key])) {
                        $key = substr($candidateKey . ':REHIRE:' . $periodId . ':' . $suffixNo, 0, 120);
                        $suffixNo++;
                    }
                } elseif ($keyOwner) {
                    throw new RuntimeException('کلید داخلی پرونده «' . $rec['name'] . '» قبلاً استفاده شده اما پرونده متناظر فعال پیدا نشد؛ برای جلوگیری از ادغام ناخواسته، بارگذاری متوقف شد.');
                }
                q('INSERT INTO employees (emp_key, personnel_code, personnel_ok, national_code, name, name_key, phone, post,
                     start_code, hire_date, sheba, salary, color, inactive, in_latest, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())', [
                    $key, $rec['personnel'], $rec['personnel_ok'] ? 1 : 0, $rec['national'], $rec['name'], $rec['name_key'],
                    $rec['phone'], $rec['post'], $rec['start_code'], $rec['hire_date'], $rec['sheba'], $rec['salary'],
                    $rec['color'], $rec['inactive'] ? 1 : 0,
                ]);
                $empId = (int)db()->lastInsertId();
                $report['new']++;
            }

            // یک کارمند در هر دوره فقط یک فیش دارد. اگر چند ردیف اکسل به یک پرونده
            // متصل شوند، به‌جای خطای خام SQL، بارگذاری با پیام روشن rollback می‌شود.
            if (isset($periodEmployeeSeen[$empId])) {
                $first = $periodEmployeeSeen[$empId];
                throw new RuntimeException(
                    'دو ردیف از شیت «اطلاعات پایه» به یک پرونده پرسنلی متصل شده‌اند: ردیف ' .
                    $first['row'] . ' (' . $first['name'] . ') و ردیف ' .
                    (int)$rec['row'] . ' (' . $rec['name'] . '). برای هر کارمند در هر دوره فقط یک فیش مجاز است. ' .
                    'فایل اکسل را از نظر کد ملی و کد پرسنلی N+F بررسی و ردیف تکراری/متناقض را اصلاح کنید. هیچ تغییری از این بارگذاری ذخیره نشد.'
                );
            }
            $periodEmployeeSeen[$empId] = ['row' => (int)$rec['row'], 'name' => (string)$rec['name']];

            $w = $work[$workLookupKey] ?? null;
            if ($w && array_key_exists('sheba', $oldProfile['manual']['core'] ?? [])) {
                $w['sheba'] = (string)$oldProfile['manual']['core']['sheba'];
            }
            $v = compute_slip($rec['base'], $w);
            $header = [
                'name' => $rec['name'], 'national' => $rec['national'], 'personnel' => $rec['personnel'],
                'start_code' => $rec['start_code'], 'hire_date' => $rec['hire_date'], 'days' => $v['days'],
                'sheba' => ($w && $w['sheba'] !== '') ? $w['sheba'] : $rec['sheba'], 'post' => $rec['post'],
            ];
            $data = ['header' => $header, 'values' => $v];

            // نسخه‌ی کامل این بارگذاری (شامل محاسبات فیش) در پرونده محفوظ می‌ماند؛
            // بارگذاری مجدد همان ماه، snapshot قبلی را حذف یا جایگزین نمی‌کند.
            $employeeSnapshot = [
                'name' => $rec['name'], 'personnel_code' => $rec['personnel'],
                'national_code' => $rec['national'], 'phone' => $rec['phone'], 'post' => $rec['post'],
                'hire_date' => $rec['hire_date'], 'sheba' => $rec['sheba'],
                'salary' => $rec['salary'], 'source_salary' => $importSalary,
                'section' => (string)($rec['extra']['section'] ?? ''),
                'inactive' => (bool)$rec['inactive'],
                '_payslip' => [
                    'period_id' => $periodId, 'year' => $year, 'month' => $month,
                    'has_work' => $w !== null, 'net' => round($v['E20']), 'data' => $data,
                ],
            ];
            employee_profile_merge_import($empId, $rec['extra'], $profileBase, $periodId, $fileName, $by, $employeeSnapshot);
            if (!empty($rec['extra'])) $report['profile_data_updated']++;

            q('INSERT INTO payslips (period_id, employee_id, personnel_code, has_work, net, data)
               VALUES (?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE personnel_code = VALUES(personnel_code),
                 has_work = VALUES(has_work), net = VALUES(net), data = VALUES(data)',
                [$periodId, $empId, $rec['personnel'], $w !== null ? 1 : 0, round($v['E20']),
                 json_encode($data, JSON_UNESCAPED_UNICODE)]);
        }
        $report['missing_from_file'] = (int)one('SELECT COUNT(*) AS c FROM employees WHERE in_latest = 0')['c'];
        q('UPDATE periods SET report = ? WHERE id = ?', [json_encode($report, JSON_UNESCAPED_UNICODE), $periodId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    // پس از ثبت موفق دوره، برای کارکنانی که فیش این دوره را دارند اعلان انتشار ثبت می‌شود.
    // برای جلوگیری از پیام تکراری در بارگذاری مجدد همان دوره، عنوان/دوره قبلاً بررسی می‌شود.
    try {
        $noticeTitle = 'فیش حقوقی دوره ' . $year . '/' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . ' منتشر شد';
        $published = all('SELECT employee_id FROM payslips WHERE period_id = ? AND has_work = 1', [$periodId]);
        foreach ($published as $slipRow) {
            $recipient = 'emp:' . (int)$slipRow['employee_id'];
            $exists = one('SELECT id FROM notifications WHERE recipient = ? AND kind = ? AND title = ? LIMIT 1',
                [$recipient, 'payslip', $noticeTitle]);
            if (!$exists) {
                notify_emp((int)$slipRow['employee_id'], 'payslip', $noticeTitle,
                    'فیش حقوقی شما در سامانه ثبت شده است. برای مشاهده وارد بخش فیش حقوقی شوید.', 'ok');
            }
        }
    } catch (Throwable $noticeError) {
        error_log('[payslip notification] ' . $noticeError->getMessage());
    }
    return $report + ['period_id' => $periodId];
}

/* ---------- ورود و خواندن فیش ---------- */

/**
 * ورود کارمند.
 * کد ملی معتبر: کد ملی + کد پرسنلی.
 * کد ملی 0000000000 (یا خالی/صفر در فایل): شماره پرسنلی + شماره موبایل.
 */
function payroll_login(string $nationalInput, string $personnelInput, string $phoneInput): array
{
    $rawNat = trim(fa_digits_to_en($nationalInput));
    $nat = $rawNat === '' ? '' : national_code_norm($rawNat);
    $pc = digits($personnelInput);
    $phone = phone_norm($phoneInput);
    if ($nat === '') {
        return ['ok' => false, 'message' => MSG_BAD_NATIONAL];
    }
    if ($nat === ZERO_NATIONAL) {
        if ($pc === '') {
            return ['ok' => false, 'message' => 'کد پرسنلی را وارد کنید.'];
        }
        // کد پرسنلی درست است؛ وضعیت حساب را جدا بررسی می‌کنیم تا پیام درست نمایش داده شود
        $emp = one('SELECT * FROM employees WHERE national_code = ? AND personnel_code = ? ORDER BY in_latest DESC, id DESC LIMIT 1',
            [ZERO_NATIONAL, $pc]);
        if (!$emp) {
            return ['ok' => false, 'message' => MSG_NOT_FOUND];
        }
        if (!(bool)$emp['personnel_ok']) {
            return ['ok' => false, 'message' => MSG_BAD_PERSONNEL];
        }
        if (!payroll_is_active($emp)) {
            return ['ok' => false, 'message' => MSG_INACTIVE];
        }
        if ($phone === '') {
            return ['ok' => false, 'message' => 'شماره موبایل را وارد کنید.', 'need_phone' => true];
        }
        if ($emp['phone'] === '') {
            return ['ok' => false, 'message' => MSG_NO_PHONE];
        }
        if ($emp['phone'] !== $phone) {
            return ['ok' => false, 'message' => MSG_PHONE_MISMATCH, 'need_phone' => true];
        }
    } else {
        $emp = one('SELECT * FROM employees WHERE national_code = ? ORDER BY in_latest DESC, id DESC LIMIT 1', [$nat]);
        if (!$emp) {
            return ['ok' => false, 'message' => MSG_NOT_FOUND];
        }
        if (!(bool)$emp['personnel_ok']) {
            return ['ok' => false, 'message' => MSG_BAD_PERSONNEL];
        }
        if ($emp['personnel_code'] !== $pc) {
            return ['ok' => false, 'message' => MSG_NOT_FOUND];
        }
        if (!payroll_is_active($emp)) {
            return ['ok' => false, 'message' => MSG_INACTIVE];
        }
    }
    return ['ok' => true, 'employee' => $emp];
}

/** حساب کارمند فعال است؛ در فایل آخرین بارگذاری باشد و غیرفعال نشده باشد */
function payroll_is_active(array $emp): bool
{
    return (bool)$emp['in_latest'] && !(bool)$emp['inactive'];
}

/** دوره‌هایی که برای کارمند فیش دارند (جدیدترین اول) */
function employee_periods(int $employeeId): array
{
    return all('SELECT p.id, p.year, p.month, s.has_work
                FROM payslips s JOIN periods p ON p.id = s.period_id
                WHERE s.employee_id = ? ORDER BY p.year DESC, p.month DESC', [$employeeId]);
}

function period_label(array $p): string
{
    $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
    return ($months[(int)$p['month']] ?? $p['month']) . ' ' . $p['year'];
}

/** فیش یک کارمند برای یک دوره؛ همان ساختار شیت فیش حقوقی */
function payslip_for(int $employeeId, int $periodId): ?array
{
    $row = one('SELECT s.*, p.year, p.month FROM payslips s JOIN periods p ON p.id = s.period_id
                WHERE s.employee_id = ? AND s.period_id = ?', [$employeeId, $periodId]);
    if (!$row) {
        return null;
    }
    $data = json_decode((string)$row['data'], true) ?: ['header' => [], 'values' => []];
    if (!(bool)$row['has_work']) {
        return ['period' => ['id' => $periodId, 'label' => period_label($row)], 'has_work' => false,
            'message' => MSG_NO_WORK, 'header' => $data['header'] ?? []];
    }
    $values = is_array($data['values'] ?? null) ? $data['values'] : [];
    $amount = static function (string $key) use ($values): float {
        $value = $values[$key] ?? 0;
        if (is_string($value)) $value = str_replace([',', '٬', ' '], '', fa_digits_to_en($value));
        return is_numeric($value) ? (float)$value : 0.0;
    };
    $pay = array_map(static fn($label, $code) => ['label' => $label, 'code' => $code, 'amount' => $amount($code)], array_values(SLIP_PAY), array_keys(SLIP_PAY));
    $ded = array_map(static fn($label, $code) => ['label' => $label, 'code' => $code, 'amount' => $amount($code)], array_values(SLIP_DED), array_keys(SLIP_DED));
    $gross = $amount('G19');
    if (abs($gross) < 0.0001) $gross = array_sum(array_column($pay, 'amount'));
    // M12 is a balance display only and is intentionally excluded from total deductions.
    $deductions = $amount('M19');
    if (abs($deductions) < 0.0001) $deductions = array_sum(array_map(static fn($item) => $item['code'] === 'M12' ? 0.0 : $item['amount'], $ded));
    $net = $amount('E20');
    if (abs($net) < 0.0001 && abs((float)($row['net'] ?? 0)) >= 0.0001) $net = (float)$row['net'];
    if (abs($net) < 0.0001 && abs($gross - $deductions) >= 0.0001) $net = $gross - $deductions;
    return [
        'period' => ['id' => $periodId, 'label' => period_label($row)],
        'has_work' => true,
        'header' => $data['header'] ?? [],
        'values' => $values,
        'pay' => $pay,
        'ded' => $ded,
        'totals' => ['pay' => $gross, 'ded' => $deductions, 'net' => $net],
        'net' => $net,
    ];
}

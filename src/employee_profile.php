<?php
declare(strict_types=1);

/**
 * نگهداری اطلاعات تکمیلی خوانده‌شده از شیت اطلاعات پایه.
 * فیلد خالی در فایل اکسل هیچ‌گاه مقدار قبلی را پاک نمی‌کند.
 */
function employee_profiles_ensure(): void
{
    static $ready = false;
    if ($ready) return;

    db()->exec("CREATE TABLE IF NOT EXISTS employee_profiles (
        employee_id INT NOT NULL PRIMARY KEY,
        extra_json MEDIUMTEXT NOT NULL,
        payroll_base_json MEDIUMTEXT NOT NULL,
        manual_json MEDIUMTEXT NULL,
        manual_updated_by VARCHAR(120) NOT NULL DEFAULT '',
        manual_updated_at DATETIME NULL,
        last_period_id INT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_employee_profiles_period (last_period_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ارتقای جدول موجود در هاست بدون حذف یا بازنویسی اطلاعات قبلی.
    foreach ([
        'manual_json' => "ALTER TABLE employee_profiles ADD COLUMN manual_json MEDIUMTEXT NULL AFTER payroll_base_json",
        'manual_updated_by' => "ALTER TABLE employee_profiles ADD COLUMN manual_updated_by VARCHAR(120) NOT NULL DEFAULT '' AFTER manual_json",
        'manual_updated_at' => "ALTER TABLE employee_profiles ADD COLUMN manual_updated_at DATETIME NULL AFTER manual_updated_by",
    ] as $column => $sql) {
        $exists = one('SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', ['employee_profiles', $column]);
        if ((int)($exists['c'] ?? 0) === 0) {
            try { db()->exec($sql); }
            catch (Throwable $e) {
                $check = one('SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', ['employee_profiles', $column]);
                if ((int)($check['c'] ?? 0) === 0) throw $e;
            }
        }
    }

    db()->exec("CREATE TABLE IF NOT EXISTS employee_profile_edit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        changed_by VARCHAR(120) NOT NULL DEFAULT '',
        changed_at DATETIME NOT NULL,
        before_json MEDIUMTEXT NOT NULL,
        after_json MEDIUMTEXT NOT NULL,
        INDEX idx_profile_edit_employee (employee_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // هر بارگذاری باید نسخه‌ی مستقل داشته باشد؛ حتی اگر همان ماه دوباره بارگذاری شود.
    db()->exec("CREATE TABLE IF NOT EXISTS employee_import_snapshots (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        period_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL DEFAULT '',
        uploaded_by VARCHAR(120) NOT NULL DEFAULT '',
        created_jdate VARCHAR(20) NOT NULL DEFAULT '',
        snapshot_json MEDIUMTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_employee_import_employee (employee_id, id),
        INDEX idx_employee_import_period (period_id, employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ارتقای امن نسخه‌ی قبلی: قید یکتای کارمند/ماه حذف می‌شود تا نسخه‌های بارگذاری مجدد هم حفظ شوند.
    $oldUnique = one("SELECT COUNT(*) AS c FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
          AND index_name = 'uq_employee_import_period'");
    if ((int)($oldUnique['c'] ?? 0) > 0) {
        try {
            db()->exec('ALTER TABLE employee_import_snapshots DROP INDEX uq_employee_import_period');
        } catch (Throwable $e) {
            // اگر درخواست هم‌زمان دیگری ایندکس را حذف کرده باشد، آن را خطا تلقی نکن.
            $stillExists = one("SELECT COUNT(*) AS c FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
                  AND index_name = 'uq_employee_import_period'");
            if ((int)($stillExists['c'] ?? 0) > 0) throw $e;
        }
    }
    $periodIndex = one("SELECT COUNT(*) AS c FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
          AND index_name = 'idx_employee_import_period'");
    if ((int)($periodIndex['c'] ?? 0) === 0) {
        try {
            db()->exec('ALTER TABLE employee_import_snapshots ADD INDEX idx_employee_import_period (period_id, employee_id)');
        } catch (Throwable $e) {
            $stillExists = one("SELECT COUNT(*) AS c FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
                  AND index_name = 'idx_employee_import_period'");
            if ((int)($stillExists['c'] ?? 0) === 0) throw $e;
        }
    }
    $createdJdateColumn = one("SELECT COUNT(*) AS c FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
          AND column_name = 'created_jdate'");
    if ((int)($createdJdateColumn['c'] ?? 0) === 0) {
        try {
            db()->exec("ALTER TABLE employee_import_snapshots ADD COLUMN created_jdate VARCHAR(20) NOT NULL DEFAULT '' AFTER uploaded_by");
        } catch (Throwable $e) {
            $stillExists = one("SELECT COUNT(*) AS c FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'employee_import_snapshots'
                  AND column_name = 'created_jdate'");
            if ((int)($stillExists['c'] ?? 0) === 0) throw $e;
        }
    }

    $ready = true;
}

function employee_profile_decode(?string $json): array
{
    if (!$json) return [];
    $v = json_decode($json, true);
    return is_array($v) ? $v : [];
}

function employee_profile_get(int $employeeId): array
{
    employee_profiles_ensure();
    $row = one('SELECT extra_json, payroll_base_json, manual_json, manual_updated_by, manual_updated_at, last_period_id FROM employee_profiles WHERE employee_id = ?', [$employeeId]);
    return [
        'extra' => employee_profile_decode($row['extra_json'] ?? null),
        'payroll_base' => employee_profile_decode($row['payroll_base_json'] ?? null),
        'manual' => employee_profile_decode($row['manual_json'] ?? null),
        'manual_updated_by' => (string)($row['manual_updated_by'] ?? ''),
        'manual_updated_at' => $row['manual_updated_at'] ?? null,
        'last_period_id' => isset($row['last_period_id']) ? (int)$row['last_period_id'] : null,
    ];
}

/** ادغام داده‌های تازه با پرونده؛ مقادیر خالی از اکسل نادیده گرفته می‌شوند. */
function employee_profile_merge_import(
    int $employeeId,
    array $incomingExtra,
    array $incomingBase,
    int $periodId,
    string $fileName,
    string $uploadedBy,
    array $employeeSnapshot
): void {
    employee_profiles_ensure();
    $old = employee_profile_get($employeeId);
    $manual = $old['manual'] ?? [];
    $manualExtra = is_array($manual['extra'] ?? null) ? $manual['extra'] : [];
    $manualBase = is_array($manual['payroll_base'] ?? null) ? $manual['payroll_base'] : [];
    $extra = $old['extra'];
    foreach ($incomingExtra as $key => $value) {
        if (array_key_exists($key, $manualExtra)) continue; // ویرایش مدیر بر اکسل اولویت دارد.
        if (is_string($value)) $value = trim($value);
        if ($value === null || $value === '') continue;
        $extra[$key] = $value;
    }
    foreach ($manualExtra as $key => $value) $extra[$key] = $value;

    $base = $old['payroll_base'];
    foreach ($incomingBase as $key => $value) {
        if (array_key_exists($key, $manualBase)) continue;
        if ($value !== null) $base[$key] = $value;
    }
    foreach ($manualBase as $key => $value) $base[$key] = $value;

    q('INSERT INTO employee_profiles (employee_id, extra_json, payroll_base_json, manual_json, manual_updated_by, manual_updated_at, last_period_id, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
       ON DUPLICATE KEY UPDATE extra_json = VALUES(extra_json), payroll_base_json = VALUES(payroll_base_json),
         manual_json = VALUES(manual_json), manual_updated_by = VALUES(manual_updated_by),
         manual_updated_at = VALUES(manual_updated_at), last_period_id = VALUES(last_period_id), updated_at = NOW()', [
        $employeeId,
        json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($manual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $old['manual_updated_by'] ?? '',
        $old['manual_updated_at'] ?? null,
        $periodId,
    ]);

    $payslipSnapshot = $employeeSnapshot['_payslip'] ?? null;
    unset($employeeSnapshot['_payslip']);
    $snapshot = [
        'employee' => $employeeSnapshot,
        'extra' => $extra,
        'payroll_base' => $base,
        'payslip' => is_array($payslipSnapshot) ? $payslipSnapshot : null,
    ];
    $createdJdate = jalali_str(jalali_today()) . ' ' . date('H:i');
    q('INSERT INTO employee_import_snapshots
        (employee_id, period_id, file_name, uploaded_by, created_jdate, snapshot_json, created_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW())', [
        $employeeId,
        $periodId,
        mb_substr($fileName, 0, 255),
        mb_substr($uploadedBy, 0, 120),
        $createdJdate,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function employee_import_snapshots_list(int $employeeId): array
{
    employee_profiles_ensure();
    $rows = all('SELECT s.period_id, s.file_name, s.uploaded_by, s.snapshot_json, s.created_at,
            s.created_jdate, p.year, p.month
        FROM employee_import_snapshots s
        LEFT JOIN periods p ON p.id = s.period_id
        WHERE s.employee_id = ? ORDER BY s.id DESC LIMIT 120', [$employeeId]);
    foreach ($rows as &$row) {
        $row['snapshot'] = employee_profile_decode($row['snapshot_json'] ?? null);
        unset($row['snapshot_json']);
        $row['period_label'] = isset($row['year'], $row['month'])
            ? period_label(['year' => (int)$row['year'], 'month' => (int)$row['month']])
            : 'دوره نامشخص';
        // تاریخ شمسی هنگام بارگذاری ذخیره می‌شود تا به منطقه زمانی MySQL وابسته نباشد.
        $row['created_at_jalali'] = (string)($row['created_jdate'] ?? '');
    }
    unset($row);
    return $rows;
}

/** نگاشت عنوان ستون‌های شناخته‌شده‌ی شیت اطلاعات پایه به فیلدهای تکمیلی پرونده. */
function employee_profile_header_aliases(): array
{
    return [
        'father_name' => ['نامپدر', 'نامپدرکارمند', 'نامپدر پرسنل'],
        'birth_date' => ['تاریختولد', 'تاریختولدشمسی', 'تولد', 'تاریخ تولد پرسنل'],
        'birth_place' => ['محلتولد', 'محلتولدکارمند'],
        'gender' => ['جنسیت'],
        'marital_status' => ['وضعیتتاهل', 'وضعیتتأهل', 'تاهل', 'تاهلکارمند'],
        'children_count' => ['تعدادفرزند', 'تعدادفرزندان', 'تعداداولاد'],
        'education' => ['تحصیلات', 'مدرکتحصیلی', 'مدرک'],
        'field_of_study' => ['رشتهتحصیلی', 'رشته'],
        'address' => ['نشانی', 'آدرس', 'آدرسکارمند', 'آدرس‌محلسکونت', 'آدرس محل سکونت'],
        'postal_code' => ['کدپستی'],
        'emergency_phone' => ['شمارهتماساضطراری', 'تماساضطراری', 'تلفناضطراری', 'شماره تماس ضروری'],
        'bank_name' => ['نامبانک', 'بانک'],
        'bank_account' => ['شمارهحساب', 'حساببانکی', 'شماره حساب بانکی'],
        'bank_card' => ['شمارهکارت', 'کارتبانکی'],
        'insurance_number' => ['شمارهبیمه', 'کدبیمه', 'شمارهبیمهتامیناجتماعی', 'شماره بیمه تامین اجتماعی'],
        'employment_type' => ['نوعاستخدام', 'نوعهمکاری'],
        'branch' => ['شعبه', 'محلخدمت'],
        'section' => ['سکشن', 'نامسکشن', 'عنوانسکشن', 'سکشنکاری', 'بخش', 'بخشکاری', 'بخشسازمانی', 'واحد', 'واحدکاری', 'واحدسازمانی', 'دپارتمان', 'گروهکاری', 'section', 'sectionname', 'department', 'departmentname', 'worksection', 'organizationalunit'],
    ];
}

function employee_profile_field_labels(): array
{
    return [
        'father_name' => 'نام پدر', 'birth_date' => 'تاریخ تولد', 'birth_place' => 'محل تولد',
        'gender' => 'جنسیت', 'marital_status' => 'وضعیت تأهل', 'children_count' => 'تعداد فرزند',
        'education' => 'مدرک تحصیلی', 'field_of_study' => 'رشته تحصیلی', 'address' => 'نشانی',
        'postal_code' => 'کد پستی', 'emergency_phone' => 'شماره تماس اضطراری', 'bank_name' => 'نام بانک',
        'bank_account' => 'شماره حساب', 'bank_card' => 'شماره کارت', 'insurance_number' => 'شماره بیمه',
        'employment_type' => 'نوع استخدام', 'branch' => 'شعبه', 'section' => 'سکشن / واحد کاری',
    ];
}


/** اعمال مقادیر دستی پرونده به رکورد وارداتی؛ این مقادیر بر داده‌ی اکسل اولویت دارند. */
function employee_profile_apply_manual_overrides_to_record(array &$rec, array $manual): void
{
    $core = is_array($manual['core'] ?? null) ? $manual['core'] : [];
    if (array_key_exists('name', $core)) {
        $rec['name'] = (string)$core['name'];
        $rec['name_key'] = XlsxReader::norm((string)$core['name']);
    }
    if (array_key_exists('national_code', $core)) {
        $rec['national'] = (string)$core['national_code'];
        $rec['national_present'] = true;
    }
    if (array_key_exists('phone', $core)) { $rec['phone'] = (string)$core['phone']; $rec['phone_present'] = true; }
    if (array_key_exists('post', $core)) { $rec['post'] = (string)$core['post']; $rec['post_present'] = true; }
    if (array_key_exists('hire_date', $core)) { $rec['hire_date'] = (string)$core['hire_date']; }
    if (array_key_exists('sheba', $core)) { $rec['sheba'] = (string)$core['sheba']; $rec['sheba_present'] = true; }
    if (array_key_exists('salary', $core)) { $rec['salary'] = (int)$core['salary']; $rec['salary_present'] = true; }

    $base = is_array($manual['payroll_base'] ?? null) ? $manual['payroll_base'] : [];
    foreach ($base as $key => $value) {
        if (in_array((string)$key, ['S','T','U','V','W','AD'], true)) {
            $rec['base'][$key] = (float)$value;
            $rec['base_present'][$key] = true;
        }
    }
    $extra = is_array($manual['extra'] ?? null) ? $manual['extra'] : [];
    foreach ($extra as $key => $value) $rec['extra'][$key] = $value;
}

/**
 * ایجاد پرونده‌ی دستی با همان قرارداد داده‌ی واردسازی اکسل.
 * کد پرسنلی از تاریخ شروع (N) و پسوند سه‌رقمی (F) ساخته می‌شود؛
 * همه‌ی مقادیر دستی در manual_json قفل می‌شوند تا ایمپورت بعدی آن‌ها را بازنویسی نکند.
 */
function employee_create_manual(array $input, string $actor): array
{
    employee_profiles_ensure();
    $name = mb_substr(trim((string)($input['name'] ?? '')), 0, 190);
    if ($name === '') throw new RuntimeException('نام و نام خانوادگی را وارد کنید.');
    $national = digits((string)($input['national_code'] ?? ''));
    if (strlen($national) !== 10) throw new RuntimeException('کد ملی باید ۱۰ رقم باشد؛ برای کد ملی نامشخص 0000000000 وارد کنید.');
    $phone = phone_norm((string)($input['phone'] ?? ''));
    if ($phone !== '' && !in_array(strlen($phone), [10, 11], true)) throw new RuntimeException('شماره موبایل معتبر نیست.');

    $hire = jalali_parse(trim(fa_digits_to_en((string)($input['hire_date'] ?? ''))));
    if (!$hire) throw new RuntimeException('تاریخ شروع همکاری را به شکل شمسی مانند ۱۴۰۵/۰۵/۲۷ وارد کنید.');
    $hireDate = jalali_str($hire);
    [$jy, $jm, $jd] = $hire;
    $startCode = ($jy >= 1400 && $jy <= 1499)
        ? sprintf('%02d%02d%02d', $jy - 1400, $jm, $jd)
        : sprintf('%04d%02d%02d', $jy, $jm, $jd);

    $suffix = digits((string)($input['personnel_suffix'] ?? ''));
    if ($suffix === '' || strlen($suffix) > 3) throw new RuntimeException('سه رقم انتهایی کد پرسنلی را وارد کنید.');
    $suffix = str_pad($suffix, 3, '0', STR_PAD_LEFT);
    $personnel = $startCode . $suffix;
    if (one('SELECT id FROM employees WHERE personnel_code = ? LIMIT 1', [$personnel])) {
        throw new RuntimeException('این کد پرسنلی قبلاً ثبت شده است. برای استخدام مجدد، پرونده قبلی را بررسی کنید.');
    }
    if ($national !== ZERO_NATIONAL && one('SELECT id FROM employees WHERE national_code = ? AND inactive = 0 ORDER BY in_latest DESC LIMIT 1', [$national])) {
        throw new RuntimeException('برای این کد ملی یک پرونده فعال وجود دارد؛ از ثبت کارمند تکراری خودداری شد.');
    }

    $post = mb_substr(trim((string)($input['post'] ?? '')), 0, 190);
    $sheba = strtoupper(preg_replace('/\s+/', '', trim((string)($input['sheba'] ?? ''))) ?? '');
    if ($sheba !== '' && !preg_match('/^(IR)?\d{24}$/', $sheba)) throw new RuntimeException('شماره شبا باید ۲۴ رقم یا IR به‌همراه ۲۴ رقم باشد.');
    $branch = trim((string)($input['branch'] ?? ''));
    $section = trim((string)($input['section'] ?? ''));
    if ($branch === '') throw new RuntimeException('شعبه را وارد کنید.');
    if ($section === '') throw new RuntimeException('سکشن / واحد کاری را وارد کنید.');

    $salaryRaw = trim((string)($input['salary'] ?? ''));
    $salaryDigits = digits($salaryRaw);
    if ($salaryRaw !== '' && $salaryDigits === '') throw new RuntimeException('حقوق باید عددی و غیرمنفی باشد.');
    $salary = $salaryDigits === '' ? 0 : (int)$salaryDigits;
    $managerPc = digits((string)($input['manager_pc'] ?? ''));
    if ($managerPc !== '' && !one('SELECT id FROM employees WHERE personnel_code = ? AND in_latest = 1 AND inactive = 0 ORDER BY id DESC LIMIT 1', [$managerPc])) {
        throw new RuntimeException('مدیر مستقیم انتخاب‌شده پیدا نشد یا فعال نیست.');
    }

    $base = [];
    foreach (['S','T','U','V','W','AD'] as $key) {
        $raw = trim((string)($input['base_' . $key] ?? ''));
        if ($raw === '') continue;
        $value = digits($raw);
        if ($value === '') throw new RuntimeException('مبالغ حقوق و مزایا باید عددی و غیرمنفی باشند.');
        $base[$key] = (float)$value;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO employees (emp_key, personnel_code, personnel_ok, national_code, name, name_key, phone, post,
             start_code, hire_date, sheba, salary, color, inactive, in_latest, manager_pc, updated_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,1,?,NOW())', [
            'PC:' . $personnel, $personnel, 1, $national, $name, XlsxReader::norm($name), $phone, $post,
            $startCode, $hireDate, $sheba, $salary, '', $managerPc,
        ]);
        $employeeId = (int)$pdo->lastInsertId();
        $fields = [
            'name' => $name, 'national_code' => $national, 'phone' => $phone, 'post' => $post,
            'hire_date' => $hireDate, 'sheba' => $sheba,
            'extra_branch' => $branch, 'extra_section' => $section,
        ];
        if ($salaryRaw !== '') $fields['salary'] = (string)$salary;
        foreach ($base as $key => $value) $fields['base_' . $key] = (string)$value;
        employee_profile_save_manual($employeeId, $fields, [], $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['employee_id' => $employeeId, 'personnel_code' => $personnel,
        'message' => 'کارمند با موفقیت ثبت شد. اطلاعات دستی در واردسازی‌های بعدی اکسل حفظ می‌شوند.'];
}

/**
 * ذخیره‌ی ویرایش مدیر. فقط فیلدهای مجاز قابل تغییرند؛ کد پرسنلی N+F و شناسه‌ی پرونده قفل هستند.
 * useExcel برای هر فیلد، قفل دستی آن را حذف می‌کند تا بارگذاری بعدی اکسل دوباره منبع شود.
 */
function employee_profile_save_manual(int $employeeId, array $fields, array $useExcel, string $actor): array
{
    employee_profiles_ensure();
    $pdo = db();
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('ذخیره اطلاعات پرونده باید داخل تراکنش امن دیتابیس انجام شود.');
    }
    $employee = one('SELECT * FROM employees WHERE id = ? FOR UPDATE', [$employeeId]);
    if (!$employee) throw new RuntimeException('کارمند پیدا نشد.');

    $profile = employee_profile_get($employeeId);
    $manual = is_array($profile['manual'] ?? null) ? $profile['manual'] : [];
    $extra = $profile['extra'];
    $base = $profile['payroll_base'];
    $before = [
        'employee' => array_intersect_key($employee, array_flip(['name','national_code','phone','post','hire_date','sheba','salary'])),
        'extra' => $extra,
        'payroll_base' => $base,
        'manual' => $manual,
    ];

    $coreAllowed = ['name','national_code','phone','post','hire_date','sheba','salary'];
    $baseAllowed = ['S','T','U','V','W','AD'];
    $extraAllowed = array_keys(employee_profile_field_labels());
    $cleanCore = [];
    $cleanExtra = [];
    $cleanBase = [];
    $removedLocks = [];

    foreach ($fields as $key => $raw) {
        $key = (string)$key;
        if (isset($useExcel[$key]) && (bool)$useExcel[$key]) {
            if (in_array($key, $coreAllowed, true)) { unset($manual['core'][$key]); $removedLocks[] = $key; }
            elseif (preg_match('/^base_(S|T|U|V|W|AD)$/', $key, $m)) { unset($manual['payroll_base'][$m[1]]); $removedLocks[] = $key; }
            elseif (preg_match('/^extra_([a-z_]+)$/', $key, $m) && in_array($m[1], $extraAllowed, true)) { unset($manual['extra'][$m[1]]); $removedLocks[] = $key; }
            continue;
        }

        if (in_array($key, $coreAllowed, true)) {
            $value = trim(fa_digits_to_en((string)$raw));
            if ($key === 'name' || $key === 'post') {
                $max = $key === 'name' ? 190 : 190;
                if ($key === 'name' && $value === '') throw new RuntimeException('نام و نام خانوادگی نمی‌تواند خالی باشد.');
                $value = mb_substr($value, 0, $max);
            } elseif ($key === 'national_code') {
                $d = digits($value);
                if (strlen($d) !== 10) throw new RuntimeException('کد ملی باید دقیقاً ۱۰ رقم باشد.');
                $value = $d;
            } elseif ($key === 'phone') {
                $d = digits($value);
                if ($d !== '' && !in_array(strlen($d), [10,11], true)) throw new RuntimeException('شماره همراه باید ۱۰ یا ۱۱ رقم باشد.');
                $value = $d;
            } elseif ($key === 'hire_date') {
                if ($value !== '') {
                    $parsed = jalali_parse($value);
                    if (!$parsed) throw new RuntimeException('تاریخ شروع همکاری را به شکل شمسی مانند ۱۴۰۵/۰۷/۰۱ وارد کنید.');
                    $value = jalali_str($parsed);
                }
            } elseif ($key === 'sheba') {
                $value = strtoupper(preg_replace('/\s+/', '', $value) ?? $value);
                if ($value !== '' && !preg_match('/^(IR)?\d{24}$/', $value)) throw new RuntimeException('شماره شبا باید ۲۴ رقم یا IR به‌همراه ۲۴ رقم باشد.');
            } elseif ($key === 'salary') {
                $d = digits($value);
                if ($value !== '' && $d === '') throw new RuntimeException('حقوق پایه باید عددی و غیرمنفی باشد.');
                $value = $d === '' ? '0' : $d;
                if (strlen($value) > 15) throw new RuntimeException('مبلغ حقوق بیش از حد مجاز است.');
                $value = (string)(int)$value;
            }
            $cleanCore[$key] = $value;
            $manual['core'][$key] = $value;
            continue;
        }

        if (preg_match('/^base_(S|T|U|V|W|AD)$/', $key, $m)) {
            $d = digits((string)$raw);
            if (trim((string)$raw) !== '' && $d === '') throw new RuntimeException('مقادیر حقوق و دستمزد باید عددی و غیرمنفی باشند.');
            $value = (float)($d === '' ? 0 : $d);
            if ($value > 999999999999999) throw new RuntimeException('یکی از مبالغ حقوق بیش از حد مجاز است.');
            $cleanBase[$m[1]] = $value;
            $manual['payroll_base'][$m[1]] = $value;
            continue;
        }

        if (preg_match('/^extra_([a-z_]+)$/', $key, $m) && in_array($m[1], $extraAllowed, true)) {
            $field = $m[1];
            $value = trim((string)$raw);
            $value = mb_substr($value, 0, 1000);
            if ($field === 'children_count' && $value !== '') {
                $d = digits($value);
                if ($d === '' || (int)$d > 30) throw new RuntimeException('تعداد فرزند باید عددی بین صفر تا ۳۰ باشد.');
                $value = (string)(int)$d;
            }
            if ($field === 'birth_date' && $value !== '') {
                $parsedBirth = jalali_parse(fa_digits_to_en($value));
                if (!$parsedBirth) throw new RuntimeException('تاریخ تولد را به شکل شمسی مانند ۱۳۷۵/۰۲/۱۵ وارد کنید.');
                $value = jalali_str($parsedBirth);
            }
            $cleanExtra[$field] = $value;
            $manual['extra'][$field] = $value;
        }
    }

    foreach (['core','extra','payroll_base'] as $group) {
        if (isset($manual[$group]) && !$manual[$group]) unset($manual[$group]);
    }

    // API مالک تراکنش است؛ تمام تغییرات کارمند، پرونده و گزارش ویرایش با هم ثبت می‌شوند.
    try {
        if (array_key_exists('national_code', $cleanCore) && $cleanCore['national_code'] !== ZERO_NATIONAL) {
            $duplicate = one('SELECT id, name FROM employees WHERE national_code = ? AND id <> ? AND inactive = 0 AND in_latest = 1 LIMIT 1', [$cleanCore['national_code'], $employeeId]);
            if ($duplicate) throw new RuntimeException('این کد ملی هم‌اکنون به پرونده فعال «' . $duplicate['name'] . '» متصل است. برای جلوگیری از جابه‌جایی سوابق، ذخیره انجام نشد.');
        }
        if ($cleanCore) {
            $sets = [];
            $params = [];
            $columnMap = ['name'=>'name','national_code'=>'national_code','phone'=>'phone','post'=>'post','hire_date'=>'hire_date','sheba'=>'sheba'];
            foreach ($columnMap as $field => $column) {
                if (!array_key_exists($field, $cleanCore)) continue;
                $sets[] = "`$column` = ?"; $params[] = $cleanCore[$field];
            }
            if (array_key_exists('name', $cleanCore)) { $sets[] = 'name_key = ?'; $params[] = XlsxReader::norm($cleanCore['name']); }
            if (array_key_exists('salary', $cleanCore)) {
                $sourceSalary = (int)$cleanCore['salary'];
                $currentSalary = $sourceSalary + (promo_apply_mode() === 'carry' ? (int)$employee['promo_salary_add'] : 0);
                $sets[] = 'salary = ?'; $params[] = $currentSalary;
                $base['salary'] = $sourceSalary;
            }
            if ($sets) { $params[] = $employeeId; q('UPDATE employees SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?', $params); }
        }

        foreach ($cleanExtra as $field => $value) $extra[$field] = $value;
        foreach ($cleanBase as $field => $value) $base[$field] = $value;

        q('INSERT INTO employee_profiles (employee_id, extra_json, payroll_base_json, manual_json, manual_updated_by, manual_updated_at, last_period_id, updated_at)
           VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())
           ON DUPLICATE KEY UPDATE extra_json = VALUES(extra_json), payroll_base_json = VALUES(payroll_base_json),
             manual_json = VALUES(manual_json), manual_updated_by = VALUES(manual_updated_by), manual_updated_at = NOW(),
             last_period_id = VALUES(last_period_id), updated_at = NOW()', [
            $employeeId,
            json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($manual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            mb_substr($actor, 0, 120),
            $profile['last_period_id'],
        ]);

        $afterEmployee = one('SELECT name,national_code,phone,post,hire_date,sheba,salary FROM employees WHERE id = ?', [$employeeId]);
        $after = ['employee' => $afterEmployee, 'extra' => $extra, 'payroll_base' => $base, 'manual' => $manual];
        q('INSERT INTO employee_profile_edit_log (employee_id, changed_by, changed_at, before_json, after_json)
           VALUES (?, ?, NOW(), ?, ?)', [
            $employeeId, mb_substr($actor, 0, 120),
            json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        throw $e; // API در صورت خطا کل تراکنش را Rollback می‌کند.
    }

    return ['ok' => true, 'message' => 'اطلاعات پرونده با موفقیت ذخیره شد. ویرایش‌های دستی در بارگذاری‌های بعدی اکسل حفظ می‌شوند.',
        'manual_overrides' => $manual, 'removed_excel_locks' => $removedLocks];
}

function employee_profile_edit_history_list(int $employeeId): array
{
    employee_profiles_ensure();
    $rows = all('SELECT id, changed_by, changed_at, before_json, after_json FROM employee_profile_edit_log WHERE employee_id = ? ORDER BY id DESC LIMIT 30', [$employeeId]);
    foreach ($rows as &$row) {
        $row['before'] = employee_profile_decode($row['before_json'] ?? null);
        $row['after'] = employee_profile_decode($row['after_json'] ?? null);
        unset($row['before_json'], $row['after_json']);
    }
    unset($row);
    return $rows;
}

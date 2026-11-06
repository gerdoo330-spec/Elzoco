<?php
declare(strict_types=1);

/** آیا انتساب مدیر $supPc به کارمند $empPc چرخه‌ی مدیریتی ایجاد می‌کند؟ */
function manager_creates_cycle(string $empPc, string $supPc): bool
{
    if ($empPc === '' || $supPc === '') {
        return false;
    }
    $cur = $supPc;
    for ($i = 0; $i < 100 && $cur !== ''; $i++) {
        if ($cur === $empPc) {
            return true;
        }
        $row = one('SELECT manager_pc FROM employees WHERE personnel_code = ? LIMIT 1', [$cur]);
        if (!$row) {
            return false;
        }
        $cur = (string)$row['manager_pc'];
    }
    return false;
}

/**
 * تعیین مدیر مستقیم برای یک یا چند کارمند.
 * $supervisorId = 0 یعنی بدون مدیر مستقیم. سرپرست می‌تواند خودش کارمند دیگری باشد (زنجیره‌ی مدیریت).
 */
function manager_assign(array $ids, int $supervisorId): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return ['ok' => false, 'message' => 'هیچ کارمندی انتخاب نشده است.'];
    }
    $supPc = '';
    $supName = '';
    if ($supervisorId > 0) {
        $sup = one('SELECT id, name, personnel_code FROM employees WHERE id = ?', [$supervisorId]);
        if (!$sup) {
            return ['ok' => false, 'message' => 'سرپرست انتخابی پیدا نشد.'];
        }
        $supPc = (string)$sup['personnel_code'];
        $supName = (string)$sup['name'];
        if ($supPc === '') {
            return ['ok' => false, 'message' => 'کد پرسنلی «' . $supName . '» ثبت نشده است و نمی‌تواند سرپرست باشد.'];
        }
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $emps = all("SELECT id, name, personnel_code FROM employees WHERE id IN ($in)", $ids);
    if (count($emps) !== count($ids)) {
        return ['ok' => false, 'message' => 'برخی از کارمندان انتخاب‌شده پیدا نشدند.'];
    }
    foreach ($emps as $e) {
        $pc = (string)$e['personnel_code'];
        if ($supPc !== '' && $pc !== '' && $pc === $supPc) {
            return ['ok' => false, 'message' => '«' . $e['name'] . '» نمی‌تواند مدیر خودش باشد.'];
        }
        if ($supPc !== '' && manager_creates_cycle($pc, $supPc)) {
            return ['ok' => false, 'message' => 'انتساب «' . $e['name'] . '» به «' . $supName . '» چرخه‌ی مدیریتی ایجاد می‌کند.'];
        }
    }
    q("UPDATE employees SET manager_pc = ? WHERE id IN ($in)", array_merge([$supPc], $ids));
    $msg = count($emps) . ' کارمند ' . ($supPc === '' ? 'بدون مدیر مستقیم شدند.' : 'مدیر مستقیمشان «' . $supName . '» شد.');
    if ($supPc !== '' && !one('SELECT id FROM admins WHERE personnel_code = ? AND active = 1 LIMIT 1', [$supPc])) {
        $msg .= ' توجه: «' . $supName . '» حساب مدیریت ندارد؛ برای تأیید درخواست‌ها باید برایش حساب بسازید.';
    }
    return ['ok' => true, 'message' => $msg];
}

/** آیا مدیر مستقیم با این کد پرسنلی حساب فعال مدیریت دارد؟ (بدون حساب، درخواست هیچ‌وقت جلوی چشمش نمی‌آید) */
function manager_has_account(string $pc): bool
{
    return $pc !== '' && (bool)one("SELECT id FROM admins WHERE personnel_code = ? AND active = 1 AND role IN ('manager','senior') LIMIT 1", [$pc]);
}

/**
 * زنجیره‌ی تأیید کارمند: از مدیر مستقیم ثبت‌شده‌ی او شروع می‌کند و در درخت مدیریت بالا می‌رود
 * (سرپرست ← مدیر ← …). هر سطحی که حساب فعال نقش «مدیر» نداشته باشد رد می‌شود؛
 * مدیر ارشد (senior) جزو این زنجیره نیست و همیشه مرحله‌ی بعد از آن است.
 */
function approval_chain(string $startPc): array
{
    $chain = [];
    $seen = [];
    $cur = $startPc;
    for ($i = 0; $i < 50 && $cur !== '' && !isset($seen[$cur]); $i++) {
        $seen[$cur] = true;
        if (one("SELECT id FROM admins WHERE personnel_code = ? AND role = 'senior' AND active = 1 LIMIT 1", [$cur])) {
            break;
        }
        if (one("SELECT id FROM admins WHERE personnel_code = ? AND role = 'manager' AND active = 1 LIMIT 1", [$cur])) {
            $chain[] = $cur;
        }
        $row = one('SELECT manager_pc FROM employees WHERE personnel_code = ? LIMIT 1', [$cur]);
        if (!$row) {
            break;
        }
        $cur = (string)$row['manager_pc'];
    }
    return $chain;
}

/* ---------- مسیر تأیید هر نوع درخواست (از پنل مدیر قابل تنظیم) ----------
 * زنجیره ثابت است: کارمند ← سوپروایزر (سرپرست مستقیم) ← مدیران بالادستی در دایره‌ی مدیریت ← مدیر ارشد ← منابع انسانی.
 * مدیر تنظیم می‌کند کدام سطح‌ها در هر نوع درخواست شرکت کنند؛ ترتیب قابل تغییر نیست.
 * منابع انسانی همیشه مرحله‌ی آخر و ثبت‌کننده‌ی نهایی است.
 * مسیر واقعی هر درخواست هنگام ثبت در ستون route و chain_pcs ذخیره می‌شود، پس تغییر تنظیم فقط روی درخواست‌های جدید اثر دارد.
 */
const APPROVAL_LEVELS = [
    'direct'  => 'سوپروایزر / سرپرست مستقیم',
    'upper'   => 'مدیران بالادستی در دایره‌ی مدیریت',
    'general' => 'مدیر ارشد',
];

const APPROVAL_KINDS = [
    'leave'     => 'مرخصی',
    'advance'   => 'مساعده',
    'loan'      => 'وام',
    'promotion' => 'ارتقای شغلی',
];

/** پیش‌فرض تنظیمات: همه‌ی سطح‌ها (همان رفتار سلسله‌مراتبی قبلی به‌علاوه‌ی دایره‌ی مدیریت) */
const APPROVAL_DEFAULT = 'direct,upper,general';
/** مسیر درخواست‌های قدیمی (قبل از این تغییر): سرپرست ← مدیر ارشد */
const APPROVAL_LEGACY_ROUTE = 'direct,general';

/** فهرست سطح‌های انتخاب‌شده به ترتیب ثابت زنجیره؛ «none» یعنی هیچ سطحی */
function approval_parse(string $csv): array
{
    $want = $csv === 'none' ? [] : array_map('trim', explode(',', $csv));
    return array_values(array_filter(array_keys(APPROVAL_LEVELS), function ($k) use ($want) {
        return in_array($k, $want, true);
    }));
}

function approval_valid(string $csv): bool
{
    if ($csv === 'none') {
        return true;
    }
    $parts = array_map('trim', explode(',', $csv));
    foreach ($parts as $p) {
        if (!isset(APPROVAL_LEVELS[$p])) {
            return false;
        }
    }
    return count(array_unique($parts)) === count($parts);
}

function approval_route(string $kind): array
{
    return approval_parse((string)setting('flow_' . $kind, APPROVAL_DEFAULT));
}

/** مسیر ذخیره‌شده‌ی یک درخواست؛ ستون خالی (NULL) یعنی درخواست قدیمی */
function route_steps_of(?string $route): array
{
    return approval_parse($route === null ? APPROVAL_LEGACY_ROUTE : $route);
}

function approval_flows(): array
{
    $out = [];
    foreach (array_keys(APPROVAL_KINDS) as $k) {
        $out[$k] = approval_route($k);
    }
    return $out;
}

function approval_flows_save(array $in): array
{
    $saved = [];
    foreach (APPROVAL_KINDS as $k => $label) {
        $v = trim((string)($in['flow_' . $k] ?? ''));
        if ($v === '') {
            $v = 'none';
        }
        if (!approval_valid($v)) {
            return ['ok' => false, 'message' => 'تنظیم «' . $label . '» معتبر نیست.'];
        }
        $saved[$k] = implode(',', approval_parse($v)) ?: 'none';
    }
    foreach ($saved as $k => $v) {
        set_setting('flow_' . $k, $v);
    }
    return ['ok' => true, 'message' => 'مسیر تأیید ذخیره شد. برای درخواست‌های جدید اعمال می‌شود.'];
}

/**
 * مسیر واقعی یک درخواست از روی زنجیره‌ی ثابت و سطح‌های انتخاب‌شده.
 * chain = سرپرستان انتخاب‌شده به ترتیب (سوپروایزر، سپس بالادستان)، route = مراحل فعال (direct / general)،
 * stage = اولین مرحله.
 */
function request_route(array $emp, string $kind): array
{
    $full = approval_chain((string)$emp['manager_pc']);
    $levels = approval_route($kind);
    $chain = [];
    foreach ($full as $i => $pc) {
        if (($i === 0 && in_array('direct', $levels, true)) || ($i > 0 && in_array('upper', $levels, true))) {
            $chain[] = $pc;
        }
    }
    $steps = [];
    if ($chain) {
        $steps[] = 'direct';
    }
    if (in_array('general', $levels, true)) {
        $steps[] = 'general';
    }
    $stage = $steps[0] ?? 'admin';
    return [
        'stage' => $stage,
        'direct' => $stage === 'direct' ? $chain[0] : '',
        'fallback' => false,
        'chain' => implode(',', $chain),
        'route' => implode(',', $steps),
    ];
}

/**
 * مرحله‌ی بعد از مرحله‌ی فعلی روی مسیر ذخیره‌شده‌ی درخواست.
 * برای direct سرپرست بعدی از chain_pcs (بر اساس chain_idx) خوانده می‌شود؛ این کار در تصمیم‌گیری‌ها انجام شده است.
 */
function route_next(array $r): array
{
    $steps = route_steps_of(isset($r['route']) ? (string)$r['route'] : null);
    $idx = array_search((string)$r['stage'], $steps, true);
    $after = array_slice($steps, $idx === false ? 0 : $idx + 1);
    $next = $after[0] ?? 'admin';
    if ($next === 'direct') {
        $list = (string)($r['chain_pcs'] ?? '') === '' ? [] : explode(',', (string)$r['chain_pcs']);
        return ['stage' => 'direct', 'direct_pc' => $list[0] ?? ''];
    }
    return ['stage' => $next, 'direct_pc' => ''];
}

/** سطح بعدی زنجیره‌ی درخواست (null یعنی آخرین سطح مدیریتی است و نوبت مدیر ارشد است) */
function chain_next_pc(array $r): ?string
{
    $s = (string)($r['chain_pcs'] ?? '');
    $list = $s === '' ? [] : explode(',', $s);
    $i = (int)($r['chain_idx'] ?? 0) + 1;
    return $list[$i] ?? null;
}

/** افزودن یک تصمیم به متن تصمیم‌ها (حفظ تاریخچه؛ قدیمی‌ترین بخش در صورت سرریز حذف می‌شود) */
function chain_note_append(string $old, string $new): string
{
    $t = trim($old) === '' ? $new : $old . ' | ' . $new;
    return mb_substr($t, -190);
}

function request_route_label(array $route): string
{
    if ($route['stage'] === 'direct') {
        return 'مدیر مستقیم';
    }
    if ($route['stage'] === 'admin') {
        return 'منابع انسانی';
    }
    return $route['fallback'] ? 'مدیر ارشد (مدیر مستقیم شما هنوز حساب پنل مدیریت ندارد)' : 'مدیر ارشد';
}

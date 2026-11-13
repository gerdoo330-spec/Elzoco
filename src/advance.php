<?php
declare(strict_types=1);
require_once __DIR__ . '/sheet_sync.php';

/* ---------- انواع درخواست مالی: مساعده و وام ----------
 * هر دو یک گردش کار دارند (مدیر مستقیم ← مدیر ارشد)، فقط بازه، سقف و شرط زمان استخدام‌شان فرق دارد.
 * همه‌ی این مقادیر از پنل ادمین قابل تغییرند (جدول settings).
 */
const FIN_KINDS = [
    'advance' => ['label' => 'مساعده', 'prefix' => 'MS', 'win' => 'adv',  'wait_key' => 'adv_wait_months',  'wait_default' => 1],
    'loan'    => ['label' => 'وام',    'prefix' => 'LN', 'win' => 'loan', 'wait_key' => 'loan_wait_months', 'wait_default' => 4],
];

/** کلید معتبر نوع درخواست؛ هر چیز ناشناخته = مساعده */
function fin_key(string $kind): string
{
    return isset(FIN_KINDS[$kind]) ? $kind : 'advance';
}

/** بازه‌ی ثبت هر نوع درخواست که مدیر تعیین می‌کند */
function advance_window(string $kind = 'advance'): array
{
    $k = FIN_KINDS[fin_key($kind)];
    $start = setting($k['win'] . '_start', '') ?? '';
    $end = setting($k['win'] . '_end', '') ?? '';
    $label = $k['label'];
    if ($start === '' || $end === '') {
        return ['open' => false, 'message' => "در حال حاضر بازه‌ی ثبت $label فعال نیست.", 'start' => '', 'end' => ''];
    }
    $today = jalali_num(jalali_today());
    $s = jalali_num(jalali_parse($start) ?? [0, 0, 0]);
    $e = jalali_num(jalali_parse($end) ?? [0, 0, 0]);
    if ($today < $s) {
        return ['open' => false, 'message' => "بازه‌ی ثبت $label هنوز آغاز نشده است.", 'start' => $start, 'end' => $end];
    }
    if ($today > $e) {
        return ['open' => false, 'message' => "تاریخ ثبت $label به پایان رسیده است.", 'start' => $start, 'end' => $end];
    }
    return ['open' => true, 'message' => '', 'start' => $start, 'end' => $end];
}

/** بازه‌ی مشاهده‌ی فیش؛ اگر مدیر بازه‌ای تعیین نکرده باشد، همیشه باز است */
function payslip_window(): array
{
    $start = setting('pay_start', '') ?? '';
    $end = setting('pay_end', '') ?? '';
    if ($start === '' || $end === '') {
        return ['open' => true, 'message' => '', 'start' => '', 'end' => ''];
    }
    $today = jalali_num(jalali_today());
    $s = jalali_num(jalali_parse($start) ?? [0, 0, 0]);
    $e = jalali_num(jalali_parse($end) ?? [0, 0, 0]);
    if ($today < $s) {
        return ['open' => false, 'message' => 'بازه‌ی مشاهده‌ی فیش هنوز آغاز نشده است.', 'start' => $start, 'end' => $end];
    }
    if ($today > $e) {
        return ['open' => false, 'message' => 'بازه‌ی مشاهده‌ی فیش به پایان رسیده است.', 'start' => $start, 'end' => $end];
    }
    return ['open' => true, 'message' => '', 'start' => $start, 'end' => $end];
}

/** سقف مجاز مساعده و وام؛ درصد و مضرب گرد کردن هرکدام مستقل و قابل تنظیم است. */
function advance_max(float $salary, string $kind = 'advance'): int
{
    if ($salary <= 0) {
        return 0;
    }
    $kind = fin_key($kind);
    if ($kind === 'loan') {
        $pct = (float)(setting('loan_ratio_pct', '33') ?? '33');
        $unit = max(1, (int)digits(setting('loan_round', '10000000') ?? '10000000'));
    } else {
        $pct = (float)(setting('adv_ratio_pct', '33.33333333333333') ?? '33.33333333333333');
        // مقدار پیش‌فرض ۳۳٫۳۳۳... دقیقاً به‌صورت یک‌سوم محاسبه شود.
        $ratio = abs($pct - (100 / 3)) < 0.0000001 ? (1 / 3) : ($pct / 100);
        $unit = max(1, (int)digits(setting('adv_round', '10000000') ?? '10000000'));
        return (int)(floor((($salary * $ratio) + 0.000001) / $unit) * $unit);
    }
    return (int)(floor((($salary * $pct / 100) + 0.000001) / $unit) * $unit);
}

/** توضیح سقف برای نمایش به کارمند */
function advance_cap_note(string $kind = 'advance'): string
{
    $kind = fin_key($kind);
    if ($kind === 'loan') {
        $pct = (float)(setting('loan_ratio_pct', '33') ?? '33');
        $unit = max(1, (int)digits(setting('loan_round', '10000000') ?? '10000000'));
    } else {
        $pct = (float)(setting('adv_ratio_pct', '33.33333333333333') ?? '33.33333333333333');
        $unit = max(1, (int)digits(setting('adv_round', '10000000') ?? '10000000'));
    }
    $p = rtrim(rtrim(number_format($pct, 6, '.', ''), '0'), '.');
    $percentLabel = abs($pct - (100 / 3)) < 0.0000001 ? 'یک‌سوم' : ($p . '٪');
    return "حداکثر $percentLabel حقوق ماهانه، گرد به مضرب " . number_format($unit) . ' ریال';
}

/** تاریخ مجاز ثبت درخواست: پس از گذشت $months ماه شمسی از تاریخ شروع به کار */
function hire_eligible_date(string $startCode, int $months = 1): ?array
{
    $h = jalali_from_start_code($startCode);
    if (!$h) {
        return null;
    }
    $total = $h[1] - 1 + max(0, $months);
    $y = $h[0] + intdiv($total, 12);
    $m = $total % 12 + 1;
    $d = min($h[2], jalali_month_length($y, $m));
    return [$y, $m, $d];
}

function advance_hire_check(array $emp, string $kind = 'advance'): array
{
    $k = FIN_KINDS[fin_key($kind)];
    // مدت انتظار هر نوع درخواست از تنظیمات مستقل خودش خوانده می‌شود.
    $months = (int)(setting($k['wait_key'], (string)$k['wait_default']) ?? (string)$k['wait_default']);
    $eligible = hire_eligible_date((string)$emp['start_code'], $months);
    if (!$eligible) {
        return ['ok' => false, 'message' => 'تاریخ استخدام شما ثبت نشده است. با مدیر تماس بگیرید.'];
    }
    if (jalali_num(jalali_today()) < jalali_num($eligible)) {
        return ['ok' => false, 'message' => "{$k['label']} پس از گذشت $months ماه از تاریخ شروع به کار امکان‌پذیر است. "
            . 'از تاریخ ' . jalali_str($eligible) . ' می‌توانید درخواست دهید.'];
    }
    return ['ok' => true];
}

function new_tracking(string $prefix, string $table): string
{
    do {
        $code = $prefix . random_int(10000, 99999);
        $exists = one("SELECT id FROM $table WHERE tracking = ?", [$code]);
    } while ($exists);
    return $code;
}

/** آیا کارمند در این بازه درخواست فعال (غیر رد شده) از همین نوع دارد؟ */
function advance_has_request(int $employeeId, string $start, string $end, string $kind = 'advance'): bool
{
    return (bool)one("SELECT id FROM advance_requests WHERE employee_id = ? AND kind = ? AND window_start = ? AND window_end = ?
        AND status <> 'رد شده' LIMIT 1", [$employeeId, fin_key($kind), $start, $end]);
}

/** وضعیت کامل یک نوع درخواست مالی برای نمایش در پنل کارمند */
function advance_overview(array $emp, string $kind = 'advance'): array
{
    $kind = fin_key($kind);
    $win = advance_window($kind);
    $max = advance_max((float)$emp['salary'], $kind);
    $hire = advance_hire_check($emp, $kind);
    $canRequest = $win['open'] && $hire['ok'] && $max > 0;
    $reason = !$win['open'] ? $win['message'] : (!$hire['ok'] ? $hire['message'] : ($max <= 0 ? 'حقوق شما در اطلاعات پایه ثبت نشده است.' : ''));
    if ($canRequest && advance_has_request((int)$emp['id'], $win['start'], $win['end'], $kind)) {
        $canRequest = false;
        $reason = 'شما در این بازه قبلاً درخواست ' . FIN_KINDS[$kind]['label'] . ' ثبت کرده‌اید.';
    }
    return [
        'kind' => $kind, 'label' => FIN_KINDS[$kind]['label'],
        'window' => $win, 'max' => $max, 'cap_note' => advance_cap_note($kind), 'salary' => (int)$emp['salary'],
        'registered_sheba' => (string)$emp['sheba'], 'can_request' => $canRequest, 'reason' => $reason,
        'requests' => advance_list_for((int)$emp['id'], $kind),
    ];
}

function advance_list_for(int $employeeId, string $kind = 'advance'): array
{
    return all('SELECT tracking, jdate, amount, approved_amount, status, stage, note, window_start, window_end, decided_at
                FROM advance_requests WHERE employee_id = ? AND kind = ? ORDER BY id DESC LIMIT 20', [$employeeId, fin_key($kind)]);
}

/** نام صاحب حساب برای نمایش کنار شبا: نسخه‌ی خود درخواست، وگرنه پرونده‌ی کارمند (فقط وقتی شبا یکی باشد) */
function advance_sheba_owner(array $r, ?array $emp = null): string
{
    $own = trim((string)($r['sheba_owner'] ?? ''));
    if ($own !== '' || !$emp) {
        return $own;
    }
    return digits((string)($emp['sheba'] ?? '')) === digits((string)($r['sheba'] ?? '')) ? trim((string)($emp['sheba_owner'] ?? '')) : '';
}

/** ثبت درخواست مساعده یا وام با همه‌ی کنترل‌ها */
function advance_submit(array $emp, array $data, string $kind = 'advance'): array
{
    fin_schedule_ensure(); // ستون sheba_owner (DDL پیش از هر نوشتن)
    $kind = fin_key($kind);
    $k = FIN_KINDS[$kind];
    $label = $k['label'];
    $win = advance_window($kind);
    if (!$win['open']) {
        return ['ok' => false, 'message' => $win['message']];
    }
    $hire = advance_hire_check($emp, $kind);
    if (!$hire['ok']) {
        return ['ok' => false, 'message' => $hire['message']];
    }
    if (advance_has_request((int)$emp['id'], $win['start'], $win['end'], $kind)) {
        return ['ok' => false, 'message' => "شما در این بازه قبلاً درخواست $label ثبت کرده‌اید."];
    }
    $max = advance_max((float)$emp['salary'], $kind);
    $amount = (int)round((float)digits((string)($data['amount'] ?? '0')));
    if ($amount <= 0 || $amount > $max) {
        return ['ok' => false, 'message' => 'مبلغ وارد شده مجاز نیست. سقف مجاز: ' . money($max) . ' ریال'];
    }
    $shebaOwner = '';
    if (!empty($data['use_registered'])) {
        $sheba = digits((string)$emp['sheba']);
        $shebaOwner = trim((string)($emp['sheba_owner'] ?? ''));
        if ($sheba === '') {
            return ['ok' => false, 'message' => 'شماره شبای ثبت‌شده‌ای برای شما پیدا نشد. شماره شبا را وارد کنید.'];
        }
    } else {
        $sheba = digits((string)($data['sheba'] ?? ''));
        if (strlen($sheba) !== 24) {
            return ['ok' => false, 'message' => 'شماره شبا باید دقیقاً ۲۴ رقم باشد.'];
        }
        // اگر همان شبای ثبت‌شده را دستی وارد کرده باشد، نام صاحب حساب همان ثبت‌شده است.
        if ($sheba === digits((string)$emp['sheba'])) {
            $shebaOwner = trim((string)($emp['sheba_owner'] ?? ''));
        }
    }
    $tracking = new_tracking($k['prefix'], 'advance_requests');
    $today = jalali_today();
    $route = request_route($emp, fin_key($kind));
    $directPc = $route['direct'];
    $stage = $route['stage'];
    q('INSERT INTO advance_requests (tracking, kind, employee_id, personnel_code, national_code, name, jdate, sheba, sheba_owner, amount,
         salary, window_start, window_end, status, note, stage, direct_pc, chain_pcs, chain_idx, route, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$tracking, $kind, $emp['id'], $emp['personnel_code'], $emp['national_code'], $emp['name'], jalali_str($today),
         'IR' . $sheba, $shebaOwner, $amount, (int)$emp['salary'], $win['start'], $win['end'], 'در انتظار بررسی', '',
         $stage, $directPc, $route['chain'], 0, $route['route']]);
    notify_stage_holders($stage, $directPc, $kind, "درخواست $label جدید",
        $emp['name'] . ' درخواست ' . $label . ' به مبلغ ' . money($amount) . ' ریال ثبت کرد.', 'info');
    return ['ok' => true, 'tracking' => $tracking,
        'message' => "درخواست $label برای " . request_route_label($route) . ' ارسال شد. کد پیگیری: ' . $tracking];
}

/**
 * ذخیره‌ی قوانین مستقل مساعده و وام از پنل ادمین.
 * کلیدها: adv_wait_months, adv_ratio_pct, adv_round, loan_wait_months, loan_ratio_pct, loan_round
 */
function fin_rules_save(array $in): array
{
    $advWait = (int)digits((string)($in['adv_wait_months'] ?? ''));
    $advRatio = trim(fa_digits_to_en((string)($in['adv_ratio_pct'] ?? '')));
    $advRound = (int)digits((string)($in['adv_round'] ?? ''));
    $loanWait = (int)digits((string)($in['loan_wait_months'] ?? ''));
    $loanRatio = trim(fa_digits_to_en((string)($in['loan_ratio_pct'] ?? '')));
    $loanRound = (int)digits((string)($in['loan_round'] ?? ''));

    if ($advWait < 0 || $advWait > 36 || $loanWait < 0 || $loanWait > 36) {
        return ['ok' => false, 'message' => 'مدت زمان استخدام برای مساعده و وام باید بین ۰ تا ۳۶ ماه باشد.'];
    }
    $validRatio = static fn(string $v): bool => (bool)preg_match('/^\d{1,3}(\.\d{1,12})?$/', $v) && (float)$v > 0 && (float)$v <= 100;
    if (!$validRatio($advRatio)) {
        return ['ok' => false, 'message' => 'سقف مساعده باید درصدی بیشتر از ۰ و حداکثر ۱۰۰ از حقوق باشد.'];
    }
    if (!$validRatio($loanRatio)) {
        return ['ok' => false, 'message' => 'سقف وام باید درصدی بیشتر از ۰ و حداکثر ۱۰۰ از حقوق باشد.'];
    }
    if ($advRound < 1000 || $advRound > 1000000000) {
        return ['ok' => false, 'message' => 'مضرب گرد کردن سقف مساعده معتبر نیست.'];
    }
    if ($loanRound < 1000 || $loanRound > 1000000000) {
        return ['ok' => false, 'message' => 'مضرب گرد کردن سقف وام معتبر نیست.'];
    }

    set_setting('adv_wait_months', (string)$advWait);
    set_setting('adv_ratio_pct', $advRatio);
    set_setting('adv_round', (string)$advRound);
    set_setting('loan_wait_months', (string)$loanWait);
    set_setting('loan_ratio_pct', $loanRatio);
    set_setting('loan_round', (string)$loanRound);
    return ['ok' => true, 'message' => 'قوانین مساعده و وام با موفقیت ذخیره شد.'];
}

/** مقادیر فعلی قوانین برای فرم پنل ادمین */
function fin_rules(): array
{
    return [
        'adv_wait_months' => (int)(setting('adv_wait_months', '1') ?? 1),
        'adv_ratio_pct' => (string)(setting('adv_ratio_pct', '33.33333333333333') ?? '33.33333333333333'),
        'adv_round' => (int)(setting('adv_round', '10000000') ?? 10000000),
        'loan_wait_months' => (int)(setting('loan_wait_months', '4') ?? 4),
        'loan_ratio_pct' => (string)(setting('loan_ratio_pct', '33') ?? '33'),
        'loan_round' => (int)(setting('loan_round', '10000000') ?? 10000000),
    ];
}

/* ---------- پنل مدیر ---------- */

/** فهرست درخواست‌های مالی که این کاربر می‌بیند؛ can_decide مشخص می‌کند تصمیم در اختیار اوست یا نه */
function advance_admin_list(array $filter, array $sess): array
{
    fin_schedule_ensure(); // ستون sheba_owner باید پیش از SELECT وجود داشته باشد
    $where = '1=1';
    $params = [];
    if (!empty($filter['status'])) {
        $where .= ' AND status = ?';
        $params[] = $filter['status'];
    }
    if (!empty($filter['kind']) && isset(FIN_KINDS[$filter['kind']])) {
        $where .= ' AND kind = ?';
        $params[] = $filter['kind'];
    }
    if (!empty($filter['from'])) {
        $where .= ' AND jdate >= ?';
        $params[] = $filter['from'];
    }
    if (!empty($filter['to'])) {
        $where .= ' AND jdate <= ?';
        $params[] = $filter['to'];
    }
    if (!empty($filter['q'])) {
        $where .= ' AND (tracking LIKE ? OR name LIKE ? OR personnel_code LIKE ? OR national_code LIKE ?)';
        $like = '%' . trim((string)$filter['q']) . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $limit = !empty($filter['report']) ? 10000 : 1000;
    $rows = all("SELECT * FROM advance_requests WHERE $where ORDER BY id DESC LIMIT $limit", $params);
    // درخواستی که کارمندش دیگر در جدول employees نیست («یتیم») با orphan=1 علامت می‌خورد.
    $employeeInfo = [];
    foreach (all('SELECT id, post, sheba, sheba_owner FROM employees') as $e) {
        $employeeInfo[(int)$e['id']] = $e;
    }
    $out = [];
    foreach ($rows as $r) {
        $emp = $employeeInfo[(int)$r['employee_id']] ?? null;
        $r['orphan'] = $emp ? 0 : 1;
        $r['post'] = $emp ? (string)$emp['post'] : '';
        // نام صاحب حساب: نسخه‌ی ثبت‌شده در درخواست؛ برای درخواست‌های قدیمی از پرونده‌ی کارمند (فقط اگر همان شبا باشد)
        if (trim((string)($r['sheba_owner'] ?? '')) === '' && $emp && digits((string)$emp['sheba']) === digits((string)$r['sheba'])) {
            $r['sheba_owner'] = (string)$emp['sheba_owner'];
        }
        if (!empty($filter['orphan']) && !$r['orphan']) {
            continue;
        }
        if (!approval_can_see($sess, $r['stage'], $r['direct_pc'])) {
            continue;
        }
        $r['can_decide'] = approval_can_decide($sess, $r['stage'], $r['direct_pc']);
        $out[] = $r;
    }
    return $out;
}

/**
 * تصمیم روی مساعده / وام — گردش یکسان با ارتقا و مرخصی:
 *   مدیر مستقیم: تأیید ← مدیر ارشد | رد ← منابع انسانی
 *   مدیر ارشد:   تأیید (با مبلغ تأییدی) ← تأیید نهایی؛ اطلاع به منابع انسانی و ثبت در پرونده | رد ← منابع انسانی
 *   منابع انسانی: فقط درخواست‌های قدیمیِ باقی‌مانده در صف را نهایی می‌کند
 */
/** بعد از تصمیم یک مرحله‌ی مساعده/وام: طبق مسیر ذخیره‌شده به مرحله‌ی بعد می‌رود */
function advance_move_on(array $row, int $id, string $kind, string $lb, int $emp, string $who, string $now): array
{
    $n = route_next($row);
    if ($n['stage'] === 'direct') {
        q("UPDATE advance_requests SET stage = 'direct', direct_pc = ?, chain_idx = 0 WHERE id = ?", [$n['direct_pc'], $id]);
        notify_stage_holders('direct', $n['direct_pc'], $kind, "درخواست $lb نزد مدیر مستقیم",
            $row['name'] . ' — تأیید قبلی ثبت شد و منتظر تأیید شماست.', 'info');
        notify_emp($emp, $kind, "درخواست $lb شما پیش رفت", 'تأیید ' . $who . ' ثبت شد و درخواست به مرحله‌ی بعد رفت.', 'info');
        return ['ok' => true, 'stage' => 'direct'];
    }
    if ($n['stage'] === 'general') {
        q("UPDATE advance_requests SET stage = 'general' WHERE id = ?", [$id]);
        notify_stage_holders('general', '', $kind, "درخواست $lb در انتظار مدیر ارشد",
            $row['name'] . ' — تأیید قبلی ثبت شد.', 'info');
        notify_emp($emp, $kind, "درخواست $lb شما پیش رفت", 'تأیید ' . $who . ' ثبت شد و درخواست نزد مدیر ارشد است.', 'info');
        return ['ok' => true, 'stage' => 'general'];
    }
    q("UPDATE advance_requests SET stage = 'admin', to_admin_decision = 'approve' WHERE id = ?", [$id]);
    notify_stage_holders('admin', '', $kind, "درخواست $lb برای ثبت نهایی منابع انسانی",
        $row['name'] . ' — تأیید لازم از مسیر انجام شد.', 'info');
    notify_emp($emp, $kind, "درخواست $lb شما پیش رفت", 'تأیید ' . $who . ' ثبت شد و درخواست نزد منابع انسانی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

/** ثبت تأیید نهایی مساعده/وام؛ تأیید مدیر ارشد خاتمه‌ی گردش است و منابع انسانی مطلع می‌شود. */
function advance_finalize_approved(array $row, int $id, string $kind, string $label, int $employeeId, int $approved,
    string $note, string $who, string $source, string $generalDecision, int $installments = 1): array
{
    $fromStage = $source === 'senior' ? 'general' : 'admin';
    $toAdmin = $source === 'senior' ? 'notified' : 'approve';
    employee_history_ensure();
    fin_schedule_ensure(); // DDL باید پیش از transaction اجرا شود

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = q("UPDATE advance_requests
                  SET stage = 'final', status = 'تایید شده', approved_amount = ?, note = ?, decided_by = ?, decided_at = NOW(),
                      general_decision = ?, to_admin_decision = ?
                  WHERE id = ? AND stage = ?", [
            $approved, $note !== '' ? $note : (string)$row['note'], $who, $generalDecision, $toAdmin, $id, $fromStage,
        ]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'این درخواست قبلاً نهایی شده یا مرحله‌ی آن تغییر کرده است.'];
        }
        $details = 'کد پیگیری: ' . $row['tracking'] . ' | نوع درخواست: ' . $label
            . ' | مبلغ درخواستی: ' . money((int)$row['amount']) . ' ریال'
            . ' | مبلغ تأیید نهایی: ' . money($approved) . ' ریال'
            . ' | بازه: ' . (string)$row['window_start'] . ' تا ' . (string)$row['window_end']
            . ' | تصمیم‌گیرنده نهایی: ' . $who
            . ($source === 'senior' ? ' | منابع انسانی از نتیجه مطلع شد.' : ' | ثبت نهایی توسط منابع انسانی انجام شد.');
        // برنامه‌ی کسر: مساعده یک‌قسطی از فیش ماه بعد؛ وام طبق تعداد اقساط تعیین‌شده
        $ym = jalali_ym(jalali_today());
        $n = $kind === 'loan' ? max(1, min(60, $installments)) : 1;
        q('UPDATE advance_requests SET approved_ym = ?, installments = ? WHERE id = ?', [$ym, $n, $id]);
        fin_schedule_build($id, $employeeId, $approved, $n, $ym);
        employee_history_add($employeeId, $kind, $id, 'final_approval', 'تأیید نهایی درخواست ' . $label, $details, $who);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }

    // ارسال تأیید نهایی به گوگل شیت (خطا مانع ثبت نمی‌شود)
    sheet_sync_push($row, $approved, $kind, (string)($row['personnel_code'] ?? ''));

    if ($source === 'senior') {
        notify_admins_by_role('admin', $kind, 'تأیید نهایی درخواست ' . $label,
            $row['name'] . ' — درخواست ' . $row['tracking'] . ' توسط ' . $who . ' با مبلغ ' . money($approved) . ' ریال تأیید نهایی شد و در پرونده پرسنلی ثبت شد.', 'ok');
        notify_emp($employeeId, $kind, 'درخواست ' . $label . ' شما تأیید نهایی شد',
            'مدیر ارشد درخواست شما را تأیید نهایی کرد؛ منابع انسانی مطلع شد و نتیجه در پرونده پرسنلی ثبت شد. مبلغ تأییدشده: ' . money($approved) . ' ریال.', 'ok');
    } else {
        notify_emp($employeeId, $kind, 'درخواست ' . $label . ' شما تأیید شد',
            'تأیید نهایی منابع انسانی انجام و در پرونده پرسنلی ثبت شد. مبلغ تأییدشده: ' . money($approved) . ' ریال.', 'ok');
    }
    return ['ok' => true, 'stage' => 'final', 'message' => 'درخواست ' . $label . ' تأیید نهایی شد و در پرونده پرسنلی ثبت شد.'];
}

function advance_decide(array $sess, int $id, string $decision, ?int $approved, string $note, string $who, ?int $installments = null): array
{
    $row = one('SELECT * FROM advance_requests WHERE id = ?', [$id]);
    if (!$row) {
        return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    }
    if (!approval_can_decide($sess, $row['stage'], $row['direct_pc'])) {
        return ['ok' => false, 'message' => 'این درخواست در مرحله‌ی فعلی در اختیار شما نیست.'];
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'تصمیم نامعتبر است.'];
    }
    $note = trim($note);
    if ($decision === 'reject' && $note === '') {
        return ['ok' => false, 'message' => 'برای رد درخواست، دلیل را بنویسید.'];
    }
    $now = jalali_str(jalali_today()) . ' ' . date('H:i');
    $kind = (string)$row['kind'];
    $lb = FIN_KINDS[fin_key($kind)]['label'];
    $emp = (int)$row['employee_id'];
    $stage = (string)$row['stage'];
    $requested = (int)$row['amount'];

    /* --- منابع انسانی: ثبت نهایی --- */
    if ($stage === 'admin') {
        if ($decision === 'approve') {
            if ((string)$row['to_admin_decision'] === 'reject') {
                return ['ok' => false, 'message' => 'این درخواست رد شده است و فقط ثبت رد نهایی ممکن است.'];
            }
            $amt = $approved ?? ((int)$row['approved_amount'] ?: $requested);
            if ($amt <= 0 || $amt > $requested) {
                return ['ok' => false, 'message' => 'مبلغ تأییدی باید بین ۱ و مبلغ درخواستی باشد.'];
            }
            return advance_finalize_approved($row, $id, $kind, $lb, $emp, $amt, $note, $who, 'hr', (string)$row['general_decision'], (int)($installments ?? 1));
        }
        if ((string)$row['to_admin_decision'] === 'approve') {
            return ['ok' => false, 'message' => 'این درخواست پس از تأیید مدیر ارشد است؛ منابع انسانی فقط می‌تواند ثبت نهایی کند و امکان رد وجود ندارد.'];
        }
        q("UPDATE advance_requests SET stage = 'rejected', status = 'رد شده', approved_amount = NULL, note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?",
            [$note, $who, $id]);
        notify_emp($emp, $kind, "درخواست $lb شما رد شد", $note, 'bad');
        return ['ok' => true, 'stage' => 'rejected'];
    }

    /* --- مدیر مستقیم --- */
    if ($stage === 'direct') {
        if ($decision === 'approve') {
            $next = chain_next_pc($row);
            if ($next !== null) {
                q("UPDATE advance_requests SET direct_pc = ?, chain_idx = ?, direct_decision = ? WHERE id = ?",
                    [$next, (int)$row['chain_idx'] + 1, chain_note_append((string)$row['direct_decision'], 'تایید: ' . $who . ' (' . $now . ')'), $id]);
                notify_stage_holders('direct', $next, $kind, "درخواست $lb نزد سطح بالاتر", $row['name'] . ' — تأیید ' . $who . ' ثبت شد و منتظر تأیید شماست.', 'info');
                notify_emp($emp, $kind, "درخواست $lb شما پیش رفت", 'تأیید ' . $who . ' ثبت شد و درخواست به سطح بالاتر رفت.', 'info');
                return ['ok' => true, 'stage' => 'direct'];
            }
            q("UPDATE advance_requests SET direct_decision = ? WHERE id = ?", [chain_note_append((string)$row['direct_decision'], 'تایید: ' . $who . ' (' . $now . ')'), $id]);
            return advance_move_on($row, $id, $kind, $lb, $emp, $who, $now);
        }
        q("UPDATE advance_requests SET stage = 'admin', to_admin_decision = 'reject', direct_decision = ?, note = ? WHERE id = ?",
            ['رد: ' . $who . ' (' . $now . ')', $note, $id]);
        notify_stage_holders('admin', '', $kind, "درخواست $lb برای ثبت نهایی منابع انسانی",
            $row['name'] . ' — توسط مدیر مستقیم رد شده؛ ثبت نهایی لازم است.', 'warn');
        notify_emp($emp, $kind, "درخواست $lb شما نزد منابع انسانی است", 'مدیر مستقیم آن را رد کرد و منتظر ثبت نهایی است.', 'info');
        return ['ok' => true, 'stage' => 'admin'];
    }

    /* --- مدیر ارشد --- */
    if ($decision === 'approve') {
        $amt = $approved ?? $requested;
        if ($amt <= 0 || $amt > $requested) {
            return ['ok' => false, 'message' => 'مبلغ تأییدی باید بین ۱ و مبلغ درخواستی باشد.'];
        }
        $generalDecision = chain_note_append((string)$row['general_decision'], 'تأیید نهایی مدیر ارشد: ' . $who . ' (' . $now . ')');
        return advance_finalize_approved($row, $id, $kind, $lb, $emp, $amt, $note, $who, 'senior', $generalDecision, (int)($installments ?? 1));
    }
    q("UPDATE advance_requests SET stage = 'admin', to_admin_decision = 'reject', general_decision = ?, note = ? WHERE id = ?",
        ['رد: ' . $who . ' (' . $now . ')', $note, $id]);
    notify_stage_holders('admin', '', $kind, "درخواست $lb برای ثبت نهایی منابع انسانی",
        $row['name'] . ' — توسط مدیر ارشد رد شده؛ ثبت نهایی لازم است.', 'warn');
    notify_emp($emp, $kind, "درخواست $lb شما نزد منابع انسانی است", 'مدیر ارشد آن را رد کرد و منتظر ثبت نهایی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

/**
 * حذف درخواست توسط ادمین (فقط مدیر با دسترسی کامل).
 * ردیف درخواست و همه‌ی رویدادهای مرتبط آن در پرونده‌ی پرسنلی (employee_history) حذف می‌شوند.
 * ارتقای نهایی‌شده‌ای که حقوق/شغلش در سامانه اعمال شده، برای جلوگیری از ناسازگاری حذف نمی‌شود.
 */
function request_admin_delete(string $type, int $id): array
{
    $tables = ['advance' => 'advance_requests', 'leave' => 'leave_requests', 'promotion' => 'promotion_requests'];
    if (!isset($tables[$type])) {
        return ['ok' => false, 'message' => 'نوع درخواست نامعتبر است.'];
    }
    $table = $tables[$type];
    $row = one("SELECT * FROM $table WHERE id = ?", [$id]);
    if (!$row) {
        return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    }
    if ($type === 'promotion' && (string)$row['stage'] === 'final' && (int)($row['raise_amount'] ?? 0) > 0 && promo_apply_mode() === 'carry') {
        return ['ok' => false, 'message' => 'این ارتقای نهایی‌شده قابل حذف نیست، چون افزایش حقوق و تغییر شغل آن در سامانه اعمال شده است.'];
    }
    // نوع رویداد در پرونده: مساعده/وام با همان kind ثبت شده‌اند (advance یا loan)
    $sourceType = $type === 'advance' ? (string)$row['kind'] : $type;
    employee_history_ensure();
    fin_schedule_ensure();
    if ($type === 'advance' && (int)one('SELECT COUNT(*) AS c FROM loan_installments WHERE advance_id = ? AND deducted_period_id IS NOT NULL', [$id])['c'] > 0) {
        return ['ok' => false, 'message' => 'از این درخواست قسطی از فیش کسر شده؛ حذف آن ممکن نیست.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($type === 'advance') {
            q('DELETE FROM loan_installments WHERE advance_id = ?', [$id]);
        }
        q('DELETE FROM employee_history WHERE source_type = ? AND source_id = ?', [$sourceType, $id]);
        q("DELETE FROM $table WHERE id = ?", [$id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    return ['ok' => true, 'message' => 'درخواست حذف شد و از پرونده‌ی پرسنلی هم پاک شد.'];
}


/* ================= کسر خودکار مساعده و وام از فیش ================= */

function jalali_ym(array $j): int
{
    return ((int)$j[0]) * 12 + (int)$j[1];
}

/** ساخت/به‌روزرسانی جدول ستون‌ها و جدول اقساط (بدون نصب دستی) */
function fin_schedule_ensure(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $cols = [
        'advance_requests' => [
            'approved_ym' => 'INT NULL',
            'installments' => 'INT NOT NULL DEFAULT 1',
            'sheba_owner' => "VARCHAR(190) NOT NULL DEFAULT ''",   // نام صاحب حساب شبا (نسخه‌ی لحظه‌ی ثبت)
        ],
        'employees' => [
            'sheba_owner' => "VARCHAR(190) NOT NULL DEFAULT ''",   // نام صاحب حساب از اکسل
        ],
    ];
    foreach ($cols as $table => $tableCols) {
        foreach ($tableCols as $col => $def) {
            $exists = one('SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
            if ((int)$exists['c'] === 0) {
                db()->exec("ALTER TABLE $table ADD COLUMN $col $def");
            }
        }
    }
    db()->exec("CREATE TABLE IF NOT EXISTS loan_installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        advance_id INT NOT NULL,
        employee_id INT NOT NULL,
        seq INT NOT NULL,
        amount BIGINT NOT NULL,
        due_ym INT NOT NULL,
        deducted_period_id INT NULL,
        INDEX idx_inst_emp (employee_id, deducted_period_id),
        INDEX idx_inst_adv (advance_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

/** منبع کسر: 'excel' (پیش‌فرض، از ستون‌های اکسل) یا 'system' (خودکار از سامانه) */
function fin_source_get(string $kind): string
{
    return setting(fin_key($kind) . '_source', 'excel') === 'system' ? 'system' : 'excel';
}

function fin_sources(): array
{
    return ['advance' => fin_source_get('advance'), 'loan' => fin_source_get('loan')];
}

function fin_source_save(array $in): array
{
    $adv = (string)($in['advance'] ?? '');
    $loan = (string)($in['loan'] ?? '');
    if (!in_array($adv, ['excel', 'system'], true) || !in_array($loan, ['excel', 'system'], true)) {
        return ['ok' => false, 'message' => 'گزینه‌ی انتخاب‌شده معتبر نیست.'];
    }
    set_setting('advance_source', $adv);
    set_setting('loan_source', $loan);
    return ['ok' => true, 'sources' => fin_sources(), 'message' => 'روش کسر مساعده و وام ذخیره شد. از بارگذاری بعدی فایل اکسل اعمال می‌شود.'];
}

/** توزیع مبلغ در اقساط؛ باقیمانده‌ی تقسیم به قسط آخر اضافه می‌شود */
function fin_schedule_build(int $advanceId, int $employeeId, int $amount, int $n, int $approvedYm): void
{
    fin_schedule_ensure();
    $n = max(1, $n);
    q('DELETE FROM loan_installments WHERE advance_id = ?', [$advanceId]);
    $base = intdiv($amount, $n);
    $rest = $amount - $base * $n;
    for ($i = 1; $i <= $n; $i++) {
        $amt = $base + ($i === $n ? $rest : 0);
        // قسط اول در ماه بعد از تأیید کسر می‌شود
        q('INSERT INTO loan_installments (advance_id, employee_id, seq, amount, due_ym) VALUES (?,?,?,?,?)',
            [$advanceId, $employeeId, $i, $amt, $approvedYm + $i]);
    }
}

/**
 * مبالغ کسر این کارمند برای دوره‌ی جاری (فقط برای منبع 'system').
 * خروجی: کلیدهای advance / loan / loan_balance که باید جای ستون‌های اکسل قرار گیرند؛
 * null یعنی هیچ‌کدام از سامانه خوانده نمی‌شوند. اقساط با شناسه‌ی دوره علامت می‌خورند تا بارگذاری مجدد دوباره جمع نزند.
 */
function fin_deductions_for_period(int $empId, int $periodId, int $periodYm): ?array
{
    fin_schedule_ensure();
    $advSys = fin_source_get('advance') === 'system';
    $loanSys = fin_source_get('loan') === 'system';
    $penSys = penalty_source_get() === 'system';
    if (!$advSys && !$loanSys && !$penSys) {
        return null;
    }
    $out = [];
    $dueWhere = 'i.employee_id = ? AND a.kind = ? AND a.stage = ? AND i.due_ym <= ? AND (i.deducted_period_id IS NULL OR i.deducted_period_id = ?)';
    foreach (['advance' => $advSys, 'loan' => $loanSys] as $kind => $on) {
        if (!$on) {
            continue;
        }
        $params = [$empId, $kind, 'final', $periodYm, $periodId];
        q("UPDATE loan_installments i JOIN advance_requests a ON a.id = i.advance_id
           SET i.deducted_period_id = ? WHERE $dueWhere", array_merge([$periodId], $params));
        $sum = one("SELECT COALESCE(SUM(i.amount), 0) AS s FROM loan_installments i JOIN advance_requests a ON a.id = i.advance_id WHERE $dueWhere", $params);
        $out[$kind] = (float)$sum['s'];
    }
    if ($loanSys) {
        $rest = one('SELECT COALESCE(SUM(i.amount), 0) AS s FROM loan_installments i JOIN advance_requests a ON a.id = i.advance_id
                     WHERE i.employee_id = ? AND a.kind = ? AND a.stage = ? AND i.deducted_period_id IS NULL', [$empId, 'loan', 'final']);
        $out['loan_balance'] = (float)$rest['s'];
    }
    if ($penSys) {
        // جریمه‌ی تأییدشده، جای ستون جریمه‌ی اکسل را می‌گیرد (در کنار کسر کار که از اکسل می‌آید)
        $out['penalty'] = penalty_deduct_for_period($empId, $periodId, $periodYm);
    }
    return $out;
}

/**
 * ویرایش درخواست تأییدشده‌ی مساعده/وام توسط ادمین: مبلغ تأییدی، تعداد اقساط و یادداشت.
 * اگر حتی یک قسط از فیش کسر شده باشد، تغییر مبلغ/اقساط مجاز نیست.
 */
function request_edit_advance(int $id, array $in): array
{
    fin_schedule_ensure();
    $row = one('SELECT * FROM advance_requests WHERE id = ?', [$id]);
    if (!$row) {
        return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    }
    if ((string)$row['stage'] !== 'final' || (string)$row['status'] !== 'تایید شده') {
        return ['ok' => false, 'message' => 'فقط درخواست‌های تأییدنهایی‌شده قابل ویرایش‌اند.'];
    }
    $approved = (int)digits((string)($in['approved'] ?? ''));
    if ($approved <= 0 || $approved > (int)$row['amount']) {
        return ['ok' => false, 'message' => 'مبلغ تأییدی باید بین ۱ و مبلغ درخواستی باشد.'];
    }
    $isLoan = (string)$row['kind'] === 'loan';
    $n = $isLoan ? (int)digits((string)($in['installments'] ?? '1')) : 1;
    if ($n < 1 || $n > 60) {
        return ['ok' => false, 'message' => 'تعداد اقساط باید بین ۱ و ۶۰ باشد.'];
    }
    if ((int)one('SELECT COUNT(*) AS c FROM loan_installments WHERE advance_id = ? AND deducted_period_id IS NOT NULL', [$id])['c'] > 0) {
        return ['ok' => false, 'message' => 'بخشی از اقساط از فیش کسر شده؛ مبلغ و تعداد اقساط دیگر قابل تغییر نیست.'];
    }
    // درخواست‌های قبل از این نسخه فاقد ماه تأیید هستند؛ ماه ویرایش به‌عنوان ماه تأیید ثبت می‌شود.
    $ym = (int)($row['approved_ym'] ?: jalali_ym(jalali_today()));
    $note = trim((string)($in['note'] ?? ''));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('UPDATE advance_requests SET approved_amount = ?, installments = ?, approved_ym = ? WHERE id = ?', [$approved, $n, $ym, $id]);
        if ($note !== '') {
            q('UPDATE advance_requests SET note = ? WHERE id = ?', [$note, $id]);
        }
        fin_schedule_build($id, (int)$row['employee_id'], $approved, $n, $ym);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    return ['ok' => true, 'message' => 'تغییرات ذخیره شد و برنامه‌ی کسر به‌روز شد.'];
}

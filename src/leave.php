<?php
declare(strict_types=1);

const LEAVE_TYPES = [
    'hourly'   => ['label' => 'ساعتی', 'unit' => 'hour'],
    'daily'    => ['label' => 'روزانه', 'unit' => 'day'],
    'deserved' => ['label' => 'استحقاقی', 'unit' => 'day'],
    'medical'  => ['label' => 'پزشکی', 'unit' => 'day'],
    'unpaid'   => ['label' => 'بدون حقوق', 'unit' => 'day'],
];

/**
 * محاسبه مانده مرخصی استحقاقی تا پایان سال شمسی جاری.
 * سهم سالانه ۲۶ روز است و برای سال شروع همکاری به نسبت روزهای اشتغال در همان سال محاسبه می‌شود.
 * فقط مرخصی استحقاقیِ تأیید نهایی‌شده از سهمیه کسر می‌شود؛ درخواست‌های در انتظار جداگانه نشان داده می‌شوند.
 */
function leave_annual_balance(array $employee, ?int $jalaliYear = null): array
{
    $year = $jalaliYear ?? (int)jalali_today()[0];
    $yearStart = [$year, 1, 1];
    $yearEnd = [$year, 12, jalali_month_length($year, 12)];
    $yearStartNo = jalali_day_number($yearStart);
    $yearEndNo = jalali_day_number($yearEnd);
    $yearDays = $yearEndNo - $yearStartNo + 1;

    $hire = jalali_parse((string)($employee['hire_date'] ?? ''));
    if (!$hire) {
        $hire = jalali_from_start_code((string)($employee['start_code'] ?? ''));
        if ($hire && !jalali_parse(jalali_str($hire))) $hire = null;
    }
    if (!$hire) {
        return [
            'year' => $year, 'year_label' => (string)$year, 'annual_quota' => 26,
            'hire_date' => '', 'eligible_days' => 0, 'year_days' => $yearDays,
            'entitlement' => 0.0, 'used' => 0.0, 'pending' => 0.0,
            'remaining' => null, 'projected_remaining' => null,
            'has_hire_date' => false,
        ];
    }

    $hireNo = jalali_day_number($hire);
    $eligibleStartNo = max($hireNo, $yearStartNo);
    $eligibleDays = $hireNo > $yearEndNo ? 0 : max(0, $yearEndNo - $eligibleStartNo + 1);
    $entitlement = $yearDays > 0 ? 26 * ($eligibleDays / $yearDays) : 0.0;

    $sumDays = static function (array $rows) use ($yearStartNo, $yearEndNo): float {
        $sum = 0.0;
        foreach ($rows as $row) {
            $from = jalali_parse((string)($row['from_date'] ?? ''));
            $to = jalali_parse((string)($row['to_date'] ?? ''));
            if (!$from || !$to) continue;
            $fromNo = max($yearStartNo, jalali_day_number($from));
            $toNo = min($yearEndNo, jalali_day_number($to));
            if ($toNo >= $fromNo) $sum += ($toNo - $fromNo + 1);
        }
        return $sum;
    };

    $fromText = jalali_str($yearStart);
    $toText = jalali_str($yearEnd);
    $approvedRows = all("SELECT from_date, to_date FROM leave_requests
        WHERE employee_id = ? AND type_key = 'deserved' AND stage = 'final'
          AND to_date >= ? AND from_date <= ?", [(int)$employee['id'], $fromText, $toText]);
    $pendingRows = all("SELECT from_date, to_date FROM leave_requests
        WHERE employee_id = ? AND type_key = 'deserved' AND stage IN ('direct','general','admin')
          AND to_date >= ? AND from_date <= ?", [(int)$employee['id'], $fromText, $toText]);

    $used = $sumDays($approvedRows);
    $pending = $sumDays($pendingRows);
    $remaining = $entitlement - $used;
    $projected = $remaining - $pending;

    return [
        'year' => $year,
        'year_label' => (string)$year,
        'annual_quota' => 26,
        'hire_date' => jalali_str($hire),
        'eligible_days' => $eligibleDays,
        'year_days' => $yearDays,
        'entitlement' => round($entitlement, 2),
        'used' => round($used, 2),
        'pending' => round($pending, 2),
        'remaining' => round($remaining, 2),
        'projected_remaining' => round($projected, 2),
        'has_hire_date' => true,
    ];
}

function leave_time_minutes(string $s): ?int
{
    if (!preg_match('/^\s*(\d{1,2})\s*:\s*(\d{2})\s*$/', fa_digits_to_en($s), $m)) {
        return null;
    }
    $h = (int)$m[1];
    $mi = (int)$m[2];
    return ($h > 23 || $mi > 59) ? null : $h * 60 + $mi;
}

/** آیا بازه‌ی جدید با مرخصی‌های قبلی (غیر رد شده) همپوشانی دارد؟ */
function leave_overlaps(int $employeeId, array $from, array $to): bool
{
    $f = jalali_day_number($from);
    $t = jalali_day_number($to);
    $rows = all("SELECT from_date, to_date FROM leave_requests WHERE employee_id = ? AND stage <> 'rejected'", [$employeeId]);
    foreach ($rows as $r) {
        $a = jalali_parse($r['from_date']);
        $b = jalali_parse($r['to_date']);
        if (!$a || !$b) {
            continue;
        }
        if (jalali_day_number($a) <= $t && jalali_day_number($b) >= $f) {
            return true;
        }
    }
    return false;
}

/** زنجیره‌ی تأیید: مدیر مستقیم (از تنظیمات کارمند)، سپس مدیر ارشد؛ بدون مدیر مستقیم مستقیم به مدیر ارشد */
function leave_chain(array $emp): array
{
    $route = request_route($emp, 'leave');
    return ['stage' => $route['stage'], 'direct' => $route['direct'], 'general' => '', 'fallback' => $route['fallback'], 'chain' => $route['chain'], 'route' => $route['route']];
}

function leave_submit(array $emp, array $data): array
{
    $type = LEAVE_TYPES[(string)($data['type'] ?? '')] ?? null;
    if (!$type) {
        return ['ok' => false, 'message' => 'نوع مرخصی انتخاب نشده است.'];
    }
    $typeKey = (string)$data['type'];
    $reason = trim((string)($data['reason'] ?? ''));
    if ($reason === '') {
        return ['ok' => false, 'message' => 'دلیل یا توضیح درخواست را بنویسید.'];
    }
    $max = (int)cfg('leave_max_hours_per_day', 8);

    if ($typeKey === 'hourly') {
        $from = jalali_parse((string)($data['from'] ?? ''));
        if (!$from) {
            return ['ok' => false, 'message' => 'تاریخ را به شکل شمسی مثل 1405/07/20 وارد کنید.'];
        }
        $to = $from;
        $s = leave_time_minutes((string)($data['start_time'] ?? ''));
        $e = leave_time_minutes((string)($data['end_time'] ?? ''));
        if ($s === null || $e === null) {
            return ['ok' => false, 'message' => 'ساعت شروع و پایان را به شکل HH:MM وارد کنید.'];
        }
        if ($e <= $s) {
            return ['ok' => false, 'message' => 'ساعت پایان باید بعد از ساعت شروع باشد.'];
        }
        if (($e - $s) / 60 > $max) {
            return ['ok' => false, 'message' => "مرخصی ساعتی بیشتر از $max ساعت در روز مجاز نیست."];
        }
        $amount = round(($e - $s) / 60, 2);
        $startTime = sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
        $endTime = sprintf('%02d:%02d', intdiv($e, 60), $e % 60);
    } else {
        $from = jalali_parse((string)($data['from'] ?? ''));
        $to = jalali_parse((string)($data['to'] ?? ''));
        if (!$from || !$to) {
            return ['ok' => false, 'message' => 'تاریخ‌ها را به شکل شمسی مثل 1405/07/20 وارد کنید.'];
        }
        $days = jalali_day_number($to) - jalali_day_number($from) + 1;
        if ($days < 1) {
            return ['ok' => false, 'message' => 'تاریخ پایان نباید قبل از تاریخ شروع باشد.'];
        }
        $amount = $days;
        $startTime = '';
        $endTime = '';
    }
    if (leave_overlaps((int)$emp['id'], $from, $to)) {
        return ['ok' => false, 'message' => 'در این بازه‌ی زمانی قبلاً مرخصی ثبت کرده‌اید.'];
    }
    $chain = leave_chain($emp);
    $tracking = new_tracking('LV', 'leave_requests');
    q('INSERT INTO leave_requests (tracking, employee_id, personnel_code, national_code, name, type_key, type_label,
         from_date, to_date, start_time, end_time, amount, reason, stage, direct_pc, general_pc, chain_pcs, chain_idx, route, created_jdate, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$tracking, $emp['id'], $emp['personnel_code'], $emp['national_code'], $emp['name'], $typeKey, $type['label'],
         jalali_str($from), jalali_str($to), $startTime, $endTime, $amount, $reason, $chain['stage'],
         $chain['direct'], $chain['general'], $chain['chain'], 0, $chain['route'], jalali_str(jalali_today())]);
    $stageLabel = request_route_label(['stage' => $chain['stage'], 'fallback' => $chain['fallback']]);
    notify_stage_holders($chain['stage'], $chain['direct'], 'leave', 'درخواست مرخصی جدید',
        $emp['name'] . ' — ' . $type['label'] . ' (' . jalali_str($from) . ($typeKey === 'hourly' ? '' : ' تا ' . jalali_str($to)) . ')', 'info');
    return ['ok' => true, 'tracking' => $tracking, 'message' => "درخواست ثبت شد (کد پیگیری: $tracking). مرحله‌ی فعلی: $stageLabel"];
}

function leave_list_for(int $employeeId): array
{
    return all('SELECT tracking, created_jdate, type_label, from_date, to_date, start_time, end_time, amount,
                reason, stage, reject_reason, direct_decision, general_decision
                FROM leave_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 30', [$employeeId]);
}

/**
 * فهرست درخواست‌های مرخصی که این کاربر می‌بیند یا باید تصمیم بگیرد
 * $sess: ['role' => admin|senior|manager, 'pc' => کد پرسنلی]
 */
function leave_manager_list(array $sess, ?string $stageFilter = null, array $filter = []): array
{
    $where = '1=1';
    $params = [];
    $stage = (string)($filter['stage'] ?? $stageFilter ?? '');
    $type = (string)($filter['type'] ?? '');
    $from = trim((string)($filter['from'] ?? ''));
    $to = trim((string)($filter['to'] ?? ''));
    $search = trim((string)($filter['q'] ?? ''));

    $allowedStages = ['direct', 'general', 'admin', 'final', 'rejected'];
    if ($stage !== '' && in_array($stage, $allowedStages, true)) {
        $where .= ' AND stage = ?';
        $params[] = $stage;
    }
    if ($type !== '' && isset(LEAVE_TYPES[$type])) {
        $where .= ' AND type_key = ?';
        $params[] = $type;
    }
    // بازه‌ی گزارش بر اساس هم‌پوشانی با بازه‌ی درخواستی محاسبه می‌شود.
    if ($from !== '') {
        $where .= ' AND to_date >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where .= ' AND from_date <= ?';
        $params[] = $to;
    }
    if ($search !== '') {
        $where .= ' AND (tracking LIKE ? OR name LIKE ? OR personnel_code LIKE ? OR national_code LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $limit = !empty($filter['report']) ? 10000 : 1000;
    $rows = all("SELECT leave_requests.*, (SELECT post FROM employees WHERE employees.id = leave_requests.employee_id) AS employee_post FROM leave_requests WHERE $where ORDER BY id DESC LIMIT $limit", $params);
    $out = [];
    foreach ($rows as $r) {
        if (!approval_can_see($sess, (string)$r['stage'], (string)$r['direct_pc'])) {
            continue;
        }
        $r['can_decide'] = leave_can_decide($sess, $r);
        $out[] = $r;
    }
    return $out;
}

function leave_can_decide(array $sess, array $r): bool
{
    return approval_can_decide($sess, $r['stage'], $r['direct_pc']);
}

/** تأیید یا رد مرخصی: مدیر مستقیم ← مدیر ارشد (تأیید مدیر ارشد نهایی است)؛ رد در هر مرحله برای ثبت رد نهایی به منابع انسانی می‌رود. */
/** بعد از تصمیم یک مرحله: درخواست طبق مسیر ذخیره‌شده به مرحله‌ی بعد می‌رود (سرپرست، مدیر ارشد یا منابع انسانی) */
function leave_move_on(array $r, int $id, string $who): array
{
    $n = route_next($r);
    $emp = (int)$r['employee_id'];
    if ($n['stage'] === 'direct') {
        q("UPDATE leave_requests SET stage = 'direct', direct_pc = ?, chain_idx = 0 WHERE id = ?", [$n['direct_pc'], $id]);
        notify_stage_holders('direct', $n['direct_pc'], 'leave', 'درخواست مرخصی نزد مدیر مستقیم',
            $r['name'] . ' — تأیید قبلی ثبت شد و منتظر تأیید شماست.', 'info');
        notify_emp($emp, 'leave', 'مرخصی شما پیش رفت', 'تأیید ' . $who . ' ثبت شد و درخواست به مرحله‌ی بعد رفت.', 'info');
        return ['ok' => true, 'stage' => 'direct'];
    }
    if ($n['stage'] === 'general') {
        q("UPDATE leave_requests SET stage = 'general' WHERE id = ?", [$id]);
        notify_stage_holders('general', '', 'leave', 'مرخصی در انتظار مدیر ارشد',
            $r['name'] . ' — تأیید قبلی ثبت شد.', 'info');
        notify_emp($emp, 'leave', 'مرخصی شما پیش رفت', 'تأیید ' . $who . ' ثبت شد و درخواست نزد مدیر ارشد است.', 'info');
        return ['ok' => true, 'stage' => 'general'];
    }
    q("UPDATE leave_requests SET stage = 'admin', to_admin_decision = 'approve' WHERE id = ?", [$id]);
    notify_stage_holders('admin', '', 'leave', 'مرخصی برای ثبت نهایی منابع انسانی',
        $r['name'] . ' — تأیید لازم از مسیر انجام شد.', 'info');
    notify_emp($emp, 'leave', 'مرخصی شما پیش رفت', 'تأیید ' . $who . ' ثبت شد و درخواست نزد منابع انسانی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

/** ثبت تأیید نهایی مرخصی؛ مدیر ارشد نهایی می‌کند و منابع انسانی فقط مطلع می‌شود. */
function leave_finalize_approved(array $r, int $id, string $who, string $source, string $generalDecision): array
{
    $fromStage = $source === 'senior' ? 'general' : 'admin';
    $toAdmin = $source === 'senior' ? 'notified' : 'approve';
    $empId = (int)$r['employee_id'];
    employee_history_ensure();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = q("UPDATE leave_requests
                  SET stage = 'final', general_decision = ?, to_admin_decision = ?
                  WHERE id = ? AND stage = ?", [$generalDecision, $toAdmin, $id, $fromStage]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'این درخواست قبلاً نهایی شده یا مرحله‌ی آن تغییر کرده است.'];
        }
        $range = (string)$r['from_date'] . ((string)$r['from_date'] !== (string)$r['to_date'] ? ' تا ' . $r['to_date'] : '');
        if ((string)$r['start_time'] !== '') {
            $range .= '، ساعت ' . $r['start_time'] . ' تا ' . $r['end_time'];
        }
        $duration = (string)$r['amount'] . ((string)$r['start_time'] !== '' ? ' ساعت' : ' روز');
        $details = 'کد پیگیری: ' . $r['tracking'] . ' | نوع مرخصی: ' . $r['type_label']
            . ' | بازه: ' . $range . ' | مدت: ' . $duration . ' | دلیل: ' . $r['reason']
            . ' | تصمیم‌گیرنده نهایی: ' . $who
            . ($source === 'senior' ? ' | منابع انسانی از نتیجه مطلع شد.' : ' | ثبت نهایی توسط منابع انسانی انجام شد.');
        employee_history_add($empId, 'leave', $id, 'final_approval', 'تأیید نهایی درخواست مرخصی', $details, $who);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }

    if ($source === 'senior') {
        notify_admins_by_role('admin', 'leave', 'تأیید نهایی درخواست مرخصی',
            $r['name'] . ' — درخواست ' . $r['tracking'] . ' توسط ' . $who . ' تأیید نهایی شد و در پرونده پرسنلی ثبت شد.', 'ok');
        notify_emp($empId, 'leave', 'مرخصی شما تأیید نهایی شد',
            'مدیر ارشد درخواست شما را تأیید نهایی کرد؛ منابع انسانی مطلع شد و نتیجه در پرونده پرسنلی ثبت شد.', 'ok');
    } else {
        notify_emp($empId, 'leave', 'مرخصی شما تأیید شد', 'تأیید نهایی منابع انسانی انجام و در پرونده پرسنلی ثبت شد.', 'ok');
    }
    return ['ok' => true, 'stage' => 'final', 'message' => 'درخواست مرخصی تأیید نهایی شد و در پرونده پرسنلی ثبت شد.'];
}

function leave_decide(array $sess, int $id, string $decision, string $note, string $who): array
{
    $r = one('SELECT * FROM leave_requests WHERE id = ?', [$id]);
    if (!$r) {
        return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    }
    if (!leave_can_decide($sess, $r)) {
        return ['ok' => false, 'message' => 'این درخواست در مرحله‌ی فعلی در اختیار شما نیست.'];
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'تصمیم نامعتبر است.'];
    }
    $note = trim($note);
    if ($decision === 'reject' && $note === '') {
        return ['ok' => false, 'message' => 'برای رد مرخصی، دلیل رد را بنویسید.'];
    }
    $now = jalali_str(jalali_today()) . ' ' . date('H:i');
    $stage = (string)$r['stage'];
    $emp = (int)$r['employee_id'];

    /* --- منابع انسانی: ثبت نهایی --- */
    if ($stage === 'admin') {
        if ($decision === 'approve') {
            if ((string)$r['to_admin_decision'] === 'reject') {
                return ['ok' => false, 'message' => 'این درخواست رد شده است و فقط ثبت رد نهایی ممکن است.'];
            }
            return leave_finalize_approved($r, $id, $who, 'hr', (string)$r['general_decision']);
        }
        if ((string)$r['to_admin_decision'] === 'approve') {
            return ['ok' => false, 'message' => 'این درخواست پس از تأیید مدیر ارشد است؛ منابع انسانی فقط می‌تواند ثبت نهایی کند و امکان رد وجود ندارد.'];
        }
        q("UPDATE leave_requests SET stage = 'rejected', reject_reason = ? WHERE id = ?", [$note, $id]);
        notify_emp($emp, 'leave', 'درخواست مرخصی شما رد شد', $note, 'bad');
        return ['ok' => true, 'stage' => 'rejected'];
    }

    /* --- مدیر مستقیم یا مدیر ارشد --- */
    $field = $stage === 'direct' ? 'direct_decision' : 'general_decision';
    if ($decision === 'approve') {
        $decisionLog = chain_note_append((string)$r[$field], 'تأیید: ' . $who . ' (' . $now . ')');
        if ($stage === 'general') {
            return leave_finalize_approved($r, $id, $who, 'senior', $decisionLog);
        }
        q("UPDATE leave_requests SET $field = ? WHERE id = ?", [$decisionLog, $id]);
        if ($stage === 'direct') {
            $next = chain_next_pc($r);
            if ($next !== null) {
                q("UPDATE leave_requests SET direct_pc = ?, chain_idx = ? WHERE id = ?", [$next, (int)$r['chain_idx'] + 1, $id]);
                notify_stage_holders('direct', $next, 'leave', 'درخواست مرخصی نزد سطح بالاتر', $r['name'] . ' — تأیید ' . $who . ' ثبت شد.', 'info');
                notify_emp($emp, 'leave', 'مرخصی شما پیش رفت', 'تأیید ' . $who . ' ثبت شد و درخواست به سطح بالاتر رفت.', 'info');
                return ['ok' => true, 'stage' => 'direct'];
            }
        }
        return leave_move_on($r, $id, $who);
    }
    q("UPDATE leave_requests SET stage = 'admin', to_admin_decision = 'reject', $field = ?, reject_reason = ? WHERE id = ?",
        ['رد: ' . $who . ' (' . $now . ')', $note, $id]);
    notify_stage_holders('admin', '', 'leave', 'مرخصی برای ثبت نهایی منابع انسانی',
        $r['name'] . ' — توسط ' . $who . ' رد شده؛ ثبت نهایی لازم است.', 'warn');
    notify_emp($emp, 'leave', 'مرخصی شما نزد منابع انسانی است', 'درخواست رد شده و منتظر ثبت نهایی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

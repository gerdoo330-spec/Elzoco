<?php
declare(strict_types=1);

/** شیفت‌های کاری قابل انتخاب در درخواست ارتقا */
const PROMO_SHIFTS = ['صبح (۸ ساعته)', 'شب (۸ ساعته)', 'فول تایم (۱۲ ساعته)', 'گردشی'];
const PROMO_MIN_SENTENCES = 3;
const PROMO_MAX_SENTENCES = 7;

/** تعداد جمله‌های متن دلیل؛ جمله‌ها با نقطه، علامت سؤال/تعجب یا خط جدید جدا می‌شوند */
function promo_sentence_count(string $text): int
{
    $parts = preg_split('/[.!?؟۔\n]+/u', $text) ?: [];
    return count(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

/** مراحل در انتظار: مدیر مستقیم / مدیر ارشد / منابع انسانی (در مسیرهای قدیمی یا ارزیابی) / ارزیابی‌شده */
const PROMO_OPEN_STAGES = "'direct','general','admin','evaluated'";

function promo_pending_for(int $employeeId): bool
{
    return (bool)one("SELECT id FROM promotion_requests WHERE employee_id = ? AND stage IN (" . PROMO_OPEN_STAGES . ") LIMIT 1", [$employeeId]);
}

function promo_list_for(int $employeeId): array
{
    return all('SELECT tracking, created_jdate, job_requested, current_shift, shift, stage, decided_by, decision_note
                FROM promotion_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 20', [$employeeId]);
}

/** وضعیت فرم ارتقا برای پنل کارمند */
function promo_overview(array $emp): array
{
    $canRequest = true;
    $reason = '';
    if (promo_pending_for((int)$emp['id'])) {
        $canRequest = false;
        $reason = 'یک درخواست ارتقای گروه شغلی شما هنوز در انتظار بررسی است.';
    }
    return [
        'post' => (string)$emp['post'], 'shifts' => PROMO_SHIFTS,
        'min_sentences' => PROMO_MIN_SENTENCES, 'max_sentences' => PROMO_MAX_SENTENCES,
        'can_request' => $canRequest, 'reason' => $reason, 'requests' => promo_list_for((int)$emp['id']),
    ];
}

/** ثبت درخواست ارتقا: شغل ← شیفت فعلی و شیفت درخواستی ← دلایل (حدود ۵ جمله) */
function promo_submit(array $emp, array $data): array
{
    if (promo_pending_for((int)$emp['id'])) {
        return ['ok' => false, 'message' => 'یک درخواست ارتقای گروه شغلی شما هنوز در انتظار بررسی است.'];
    }
    $job = trim((string)($data['job'] ?? ''));
    if (mb_strlen($job) < 2 || mb_strlen($job) > 190) {
        return ['ok' => false, 'message' => 'شغل مورد درخواست را وارد کنید (حداکثر ۱۹۰ نویسه).'];
    }
    $currentShift = (string)($data['current_shift'] ?? '');
    if (!in_array($currentShift, PROMO_SHIFTS, true)) {
        return ['ok' => false, 'message' => 'شیفت فعلی خود را از فهرست انتخاب کنید.'];
    }
    $shift = (string)($data['shift'] ?? '');
    if (!in_array($shift, PROMO_SHIFTS, true)) {
        return ['ok' => false, 'message' => 'شیفت درخواستی را از فهرست انتخاب کنید.'];
    }
    $reason = trim((string)($data['reason'] ?? ''));
    if (mb_strlen($reason) < 20) {
        return ['ok' => false, 'message' => 'دلایل ارتقا یا افزایش حقوق را بنویسید.'];
    }
    if (mb_strlen($reason) > 1000) {
        return ['ok' => false, 'message' => 'متن دلایل بیش از حد طولانی است (حداکثر ۱۰۰۰ نویسه).'];
    }
    $n = promo_sentence_count($reason);
    if ($n < PROMO_MIN_SENTENCES || $n > PROMO_MAX_SENTENCES) {
        return ['ok' => false, 'message' => 'دلایل را در حدود ۵ جمله بنویسید (بین ' . PROMO_MIN_SENTENCES . ' تا '
            . PROMO_MAX_SENTENCES . ' جمله، هر جمله با نقطه جدا شود). الان ' . $n . ' جمله تشخیص داده شد.'];
    }
    $route = request_route($emp, 'promotion');
    $tracking = new_tracking('PR', 'promotion_requests');
    q('INSERT INTO promotion_requests (tracking, employee_id, personnel_code, national_code, name, post,
         job_requested, current_shift, shift, reason, stage, review_level, direct_pc, chain_pcs, chain_idx, route, created_jdate, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$tracking, $emp['id'], $emp['personnel_code'], $emp['national_code'], $emp['name'], (string)$emp['post'],
         $job, $currentShift, $shift, $reason, $route['stage'], $route['stage'], $route['direct'], $route['chain'], 0, $route['route'], jalali_str(jalali_today())]);
    notify_stage_holders($route['stage'], $route['direct'], 'promotion', 'درخواست ارتقای جدید',
        $emp['name'] . ' درخواست ارتقا به «' . $job . '» ثبت کرد (شیفت فعلی: ' . $currentShift . '، شیفت درخواستی: ' . $shift . ').', 'info');
    return ['ok' => true, 'tracking' => $tracking,
        'message' => 'درخواست ارتقا برای ' . request_route_label($route) . " ارسال شد. کد پیگیری: $tracking"];
}

/* ---------- پنل مدیر ----------
 * گردش:
 *   مدیر مستقیم ← تأیید: به مدیر ارشد می‌رود (stage = general)
 *               ← رد یا ارزیابی مجدد: به منابع انسانی (ادمین سامانه) می‌رود (stage = admin)
 *   مدیر ارشد   ← تأیید (همراه مبلغ افزایش حقوق پایه): به منابع انسانی می‌رود (stage = admin)
 *               ← رد یا ارزیابی مجدد: به منابع انسانی می‌رود (stage = admin)
 *   منابع انسانی (ادمین سامانه):
 *     - ارزیابی مجدد: نمره را ثبت می‌کند ← درخواست به مدیر برمی‌گردد (stage = evaluated)
 *     - تأیید: مبلغ به «حقوق پایه‌ی» کارمند اضافه می‌شود و درخواست نهایی می‌شود (stage = final)
 *     - رد: رد نهایی (stage = rejected)
 *   پس از ارزیابی، همان مدیری که ارجاع داده تأیید یا رد می‌کند؛ تأیید او به مرحله‌ی بعد می‌رود.
 */

const PROMO_DECISION_LABEL = ['approve' => 'تأیید', 'reject' => 'رد', 'reevaluate' => 'درخواست ارزیابی مجدد'];

/** روش اعمال افزایش حقوق و شغل جدید: carry = ثبت در سامانه، excel = فقط از فایل اکسل */
const PROMO_APPLY_MODES = ['carry' => 'ثبت در سامانه', 'excel' => 'فقط از فایل اکسل'];

function promo_apply_mode(): string
{
    $m = setting('promo_apply_mode', 'carry');
    return isset(PROMO_APPLY_MODES[$m]) ? $m : 'carry';
}

function promo_mode_save(array $in): array
{
    $m = (string)($in['mode'] ?? '');
    if (!isset(PROMO_APPLY_MODES[$m])) {
        return ['ok' => false, 'message' => 'گزینه‌ی انتخاب‌شده معتبر نیست.'];
    }
    set_setting('promo_apply_mode', $m);
    return ['ok' => true, 'mode' => $m, 'message' => 'تنظیم ذخیره شد: ' . PROMO_APPLY_MODES[$m] . '.'];
}

/** سطح تصمیم فعلی: direct (مدیر مستقیم) یا general (مدیر ارشد) */
function promo_level(array $r): string
{
    return $r['stage'] === 'evaluated' ? (string)$r['review_level'] : (string)$r['stage'];
}

function promo_can_see(array $sess, array $r): bool
{
    if (is_full_access((string)$sess['role'])) {
        return true;
    }
    return $sess['role'] === 'manager' && $sess['pc'] !== '' && $sess['pc'] === $r['direct_pc'];
}

function promo_can_decide(array $sess, array $r): bool
{
    $st = (string)$r['stage'];
    if ($st === 'admin') {
        return $sess['role'] === 'admin';
    }
    if (!in_array($st, ['direct', 'general', 'evaluated'], true)) {
        return false;
    }
    if (promo_level($r) === 'general') {
        return $sess['role'] === 'senior';
    }
    return in_array($sess['role'], ['manager', 'senior'], true) && $sess['pc'] !== '' && $sess['pc'] === $r['direct_pc'];
}

/** کارهایی که این کاربر روی این درخواست می‌تواند بکند */
function promo_actions(array $sess, array $r): array
{
    if (!promo_can_decide($sess, $r)) {
        return [];
    }
    if ($r['stage'] === 'admin') {
        $kind = (string)$r['to_admin_decision'];
        if ($kind === 'reevaluate' && $r['eval_score'] === null) {
            return ['evaluate'];
        }
        if ($kind === 'reject') {
            return ['reject'];
        }
        return ['approve'];
    }
    if ($r['eval_score'] !== null) {
        return ['approve', 'reject'];
    }
    return ['approve', 'reject', 'reevaluate'];
}

function promo_admin_list(array $sess, array $filter = []): array
{
    $where = '1=1';
    $params = [];
    $stage = (string)($filter['stage'] ?? '');
    $from = trim((string)($filter['from'] ?? ''));
    $to = trim((string)($filter['to'] ?? ''));
    $search = trim((string)($filter['q'] ?? ''));
    $allowedStages = ['direct', 'general', 'admin', 'evaluated', 'final', 'rejected'];
    if ($stage !== '' && in_array($stage, $allowedStages, true)) {
        $where .= ' AND stage = ?';
        $params[] = $stage;
    }
    if ($from !== '') {
        $where .= ' AND created_jdate >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where .= ' AND created_jdate <= ?';
        $params[] = $to;
    }
    if ($search !== '') {
        $where .= ' AND (tracking LIKE ? OR name LIKE ? OR personnel_code LIKE ? OR national_code LIKE ? OR job_requested LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $limit = !empty($filter['report']) ? 10000 : 1000;
    $rows = all("SELECT * FROM promotion_requests WHERE $where ORDER BY id DESC LIMIT $limit", $params);
    $out = [];
    foreach ($rows as $r) {
        if (!promo_can_see($sess, $r)) {
            continue;
        }
        $r['actions'] = promo_actions($sess, $r);
        $r['can_decide'] = $r['actions'] !== [];
        $out[] = $r;
    }
    return $out;
}

/** ثبت تصمیم در مرحله‌ی فعلی؛ تأیید مدیر ارشد نهایی است و تغییرات حقوق/سمت طبق سیاست سامانه اعمال می‌شود. */
/** تأیید مدیر مستقیم طبق مسیر ادامه می‌یابد؛ تأیید مدیر ارشد نهایی است و منابع انسانی فقط مطلع می‌شود. */
function promo_move_on(array $row, int $id, string $who, int $empId): array
{
    $n = route_next($row);
    if ($n['stage'] === 'direct') {
        q("UPDATE promotion_requests SET stage = 'direct', direct_pc = ?, chain_idx = 0 WHERE id = ?", [$n['direct_pc'], $id]);
        notify_stage_holders('direct', $n['direct_pc'], 'promotion', 'درخواست ارتقا نزد مدیر مستقیم',
            $row['name'] . ' — تأیید قبلی (' . $who . ') ثبت شد و منتظر تأیید شماست.', 'info');
        notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد', 'تأیید ثبت شد و درخواست به مرحله‌ی بعد رفت.', 'info');
        return ['ok' => true, 'stage' => 'direct'];
    }
    if ($n['stage'] === 'general') {
        q("UPDATE promotion_requests SET stage = 'general' WHERE id = ?", [$id]);
        notify_stage_holders('general', '', 'promotion', 'درخواست ارتقا نزد مدیر ارشد',
            $row['name'] . ' — تأیید قبلی (' . $who . ') ثبت شد و منتظر تأیید شماست.', 'info');
        notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد', 'تأیید ثبت شد و درخواست نزد مدیر ارشد است.', 'info');
        return ['ok' => true, 'stage' => 'general'];
    }
    q("UPDATE promotion_requests SET stage = 'admin', to_admin_decision = 'approve' WHERE id = ?", [$id]);
    notify_stage_holders('admin', '', 'promotion', 'درخواست ارتقا برای ثبت نهایی منابع انسانی',
        $row['name'] . ' — تأیید لازم از مسیر انجام شد. لطفاً ثبت نهایی و مبلغ افزایش را وارد کنید.', 'info');
    notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد', 'تأیید ثبت شد و درخواست نزد منابع انسانی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

function promo_decide(array $sess, int $id, string $decision, string $note, string $who, ?string $score = null, ?string $amount = null): array
{
    $r = one('SELECT * FROM promotion_requests WHERE id = ?', [$id]);
    if (!$r) {
        return ['ok' => false, 'message' => 'درخواست پیدا نشد.'];
    }
    if (!promo_can_decide($sess, $r)) {
        return ['ok' => false, 'message' => 'این درخواست در مرحله‌ی فعلی در اختیار شما نیست.'];
    }
    $note = trim($note);
    if (mb_strlen($note) > 500) {
        return ['ok' => false, 'message' => 'توضیح بیش از حد طولانی است (حداکثر ۵۰۰ نویسه).'];
    }
    if (!in_array($decision, promo_actions($sess, $r), true)) {
        return ['ok' => false, 'message' => 'این اقدام برای درخواست در مرحله‌ی فعلی مجاز نیست.'];
    }
    $empId = (int)$r['employee_id'];
    $who = trim($who);

    /* --- منابع انسانی: ثبت نمره‌ی ارزیابی --- */
    if ($decision === 'evaluate') {
        $sc = trim(fa_digits_to_en((string)$score));
        $sc = str_replace(['٫', '،', ','], '.', $sc);
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $sc) || (float)$sc > 100) {
            return ['ok' => false, 'message' => 'نمره‌ی ارزیابی را بین ۰ تا ۱۰۰ وارد کنید.'];
        }
        q("UPDATE promotion_requests SET stage = 'evaluated', eval_score = ?, eval_note = ?, eval_by = ?, eval_at = NOW() WHERE id = ?",
            [$sc, $note, $who, $id]);
        $stage = $r['review_level'] === 'general' ? 'general' : 'direct';
        notify_stage_holders($stage, (string)$r['direct_pc'], 'promotion', 'نمره‌ی ارزیابی ثبت شد',
            $r['name'] . ' — نمره: ' . $sc . ' از ۱۰۰. لطفاً تصمیم نهایی خود را ثبت کنید.', 'ok');
        notify_emp($empId, 'promotion', 'ارزیابی درخواست ارتقای شما انجام شد', 'درخواست شما نزد مدیر برای تصمیم نهایی است.', 'info');
        return ['ok' => true, 'stage' => 'evaluated'];
    }

    /* --- منابع انسانی: ثبت نهایی --- */
    if ($r['stage'] === 'admin') {
        if ($decision === 'reject' && (string)$r['to_admin_decision'] === 'approve') {
            return ['ok' => false, 'message' => 'این درخواست پس از تأیید مدیر ارشد است؛ منابع انسانی فقط می‌تواند ثبت نهایی کند و امکان رد وجود ندارد.'];
        }
        if ($decision === 'reject') {
            if ($note === '') {
                return ['ok' => false, 'message' => 'برای رد درخواست، دلیل را بنویسید.'];
            }
            q("UPDATE promotion_requests SET stage = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?",
                [$who, $note, $id]);
            notify_emp($empId, 'promotion', 'درخواست ارتقای شما رد شد', $note, 'bad');
            notify_admin_pc((string)$r['direct_pc'], 'promotion', 'نتیجه‌ی درخواست ارتقا', $r['name'] . ' — رد شد.', 'bad');
            return ['ok' => true, 'stage' => 'rejected'];
        }

        $raise = (int)($r['raise_amount'] ?? 0);
        if ($raise <= 0) {
            $raise = (int)digits((string)$amount);
        }
        if ($raise <= 0) {
            return ['ok' => false, 'message' => 'مبلغ افزایش حقوق پایه را وارد کنید.'];
        }
        employee_history_ensure();
        $applyMode = promo_apply_mode();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $upd = q("UPDATE promotion_requests SET stage = 'final', to_admin_decision = 'approve', decided_by = ?, decided_at = NOW(), decision_note = ?, raise_amount = ?
                      WHERE id = ? AND stage = 'admin'", [$who, $note, $raise, $id]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'این درخواست قبلاً نهایی شده است.'];
            }
            if ($applyMode === 'carry') {
                q('UPDATE employees SET salary = salary + ?, promo_salary_add = promo_salary_add + ? WHERE id = ?', [$raise, $raise, $empId]);
                if (trim((string)$r['job_requested']) !== '') {
                    q('UPDATE employees SET post = ?, promo_post = ? WHERE id = ?', [$r['job_requested'], $r['job_requested'], $empId]);
                }
            }
            $historyDetails = 'کد پیگیری: ' . $r['tracking'] . ' | شغل فعلی: ' . $r['post']
                . ' | شغل درخواستی: ' . $r['job_requested'] . ' | شیفت درخواستی: ' . $r['shift']
                . ' | افزایش حقوق: ' . money($raise) . ' ریال | تأیید نهایی توسط: ' . $who
                . ($applyMode === 'carry' ? ' | حقوق و شغل در سامانه اعمال شد.' : ' | به‌روزرسانی حقوق و شغل باید در فایل اکسل دوره بعد انجام شود.');
            employee_history_add($empId, 'promotion', $id, 'final_approval', 'تأیید نهایی ارتقای گروه شغلی', $historyDetails, $who);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        notify_emp($empId, 'promotion', 'درخواست ارتقای شما تأیید شد',
            $applyMode === 'carry'
                ? 'تبریک! درخواست ارتقای گروه شغلی شما تأیید شد و حقوق پایه‌ی شما ' . money($raise) . ' ریال افزایش یافت.'
                : 'درخواست ارتقای شما تأیید شد. منابع انسانی باید حقوق و شغل جدید را در فایل اکسل دوره بعد اعمال کند.', 'ok');
        notify_admin_pc((string)$r['direct_pc'], 'promotion', 'نتیجه‌ی درخواست ارتقا', $r['name'] . ' — تأیید نهایی و ثبت در پرونده پرسنلی انجام شد.', 'ok');
        return ['ok' => true, 'stage' => 'final', 'salary_increase' => $raise];
    }

    /* --- مدیر مستقیم یا مدیر ارشد --- */
    $level = promo_level($r);
    if ($note === '') {
        return ['ok' => false, 'message' => $decision === 'reject' ? 'برای رد درخواست، دلیل را بنویسید.' : 'دلیل تصمیم را بنویسید.'];
    }

    /* تأیید مدیر مستقیم یا مدیر ارشد: مرحله‌ی بعد از روی مسیر ذخیره‌شده‌ی درخواست تعیین می‌شود */
    $lvl = promo_level($r);
    $lvlRow = array_merge($r, ['stage' => $lvl]);

    /* تأیید مدیر ارشد، پایان گردش است؛ منابع انسانی فقط اعلان می‌گیرد و رویداد در پرونده ثبت می‌شود. */
    if ($decision === 'approve' && $lvl === 'general' && route_next($lvlRow)['stage'] === 'admin') {
        $raise = (int)digits((string)$amount);
        if ($raise <= 0) {
            return ['ok' => false, 'message' => 'مبلغ افزایش حقوق پایه‌ی کارمند را وارد کنید.'];
        }
        employee_history_ensure();
        $applyMode = promo_apply_mode();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $upd = q("UPDATE promotion_requests
                      SET stage = 'final', to_admin_decision = 'notified', senior_decision = 'approve', senior_note = ?,
                          senior_by = ?, senior_at = NOW(), decided_by = ?, decided_at = NOW(), decision_note = ?, raise_amount = ?
                      WHERE id = ? AND stage IN ('general','evaluated')", [$note, $who, $who, $note, $raise, $id]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'این درخواست قبلاً نهایی شده یا مرحله‌ی آن تغییر کرده است.'];
            }
            if ($applyMode === 'carry') {
                q('UPDATE employees SET salary = salary + ?, promo_salary_add = promo_salary_add + ? WHERE id = ?', [$raise, $raise, $empId]);
                if (trim((string)$r['job_requested']) !== '') {
                    q('UPDATE employees SET post = ?, promo_post = ? WHERE id = ?', [$r['job_requested'], $r['job_requested'], $empId]);
                }
            }
            $historyDetails = 'کد پیگیری: ' . $r['tracking'] . ' | شغل فعلی: ' . $r['post']
                . ' | شغل درخواستی: ' . $r['job_requested'] . ' | شیفت فعلی: ' . $r['current_shift']
                . ' | شیفت درخواستی: ' . $r['shift'] . ' | افزایش حقوق مصوب: ' . money($raise) . ' ریال'
                . ' | تأیید نهایی مدیر ارشد: ' . $who
                . ($applyMode === 'carry' ? ' | حقوق پایه و شغل در سامانه اعمال شد.' : ' | حقوق و شغل جدید باید در فایل اکسل دوره بعد اعمال شود.');
            employee_history_add($empId, 'promotion', $id, 'final_approval', 'تأیید نهایی ارتقای گروه شغلی', $historyDetails, $who);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
        $hrBody = $r['name'] . ' — درخواست ' . $r['tracking'] . ' برای ارتقا به «' . $r['job_requested']
            . '» با افزایش حقوق ' . money($raise) . ' ریال توسط ' . $who . ' تأیید نهایی شد و در پرونده پرسنلی ثبت شد.'
            . ($applyMode === 'carry' ? ' تغییرات حقوق و شغل در سامانه اعمال شده است.' : ' لطفاً فایل اکسل دوره بعد را به‌روزرسانی کنید.');
        notify_admins_by_role('admin', 'promotion', 'تأیید نهایی درخواست ارتقای شغلی', $hrBody, 'ok');
        notify_emp($empId, 'promotion', 'درخواست ارتقای شما تأیید نهایی شد',
            $applyMode === 'carry'
                ? 'مدیر ارشد درخواست شما را تأیید نهایی کرد؛ افزایش حقوق و تغییر شغل در سامانه اعمال شد و منابع انسانی مطلع شد.'
                : 'مدیر ارشد درخواست شما را تأیید نهایی کرد؛ منابع انسانی مطلع شد و باید تغییرات را در فایل اکسل دوره بعد اعمال کند.', 'ok');
        notify_admin_pc((string)$r['direct_pc'], 'promotion', 'نتیجه‌ی درخواست ارتقا', $r['name'] . ' — تأیید نهایی مدیر ارشد ثبت شد.', 'ok');
        return ['ok' => true, 'stage' => 'final', 'salary_increase' => $raise,
            'message' => 'درخواست توسط مدیر ارشد تأیید نهایی و در پرونده پرسنلی ثبت شد.'];
    }

    if ($decision === 'approve' && $lvl === 'direct') {
        $next = chain_next_pc($r);
        if ($next !== null) {
            q("UPDATE promotion_requests SET direct_pc = ?, chain_idx = ?, direct_decision = 'approve', direct_note = ?, direct_by = ?, direct_at = NOW() WHERE id = ?",
                [$next, (int)$r['chain_idx'] + 1, $note, $who, $id]);
            notify_stage_holders('direct', $next, 'promotion', 'درخواست ارتقا نزد سطح بالاتر', $r['name'] . ' — تأیید ' . $who . ' ثبت شد و منتظر تأیید شماست.', 'info');
            notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد', 'تأیید مدیر شما ثبت شد و درخواست به سطح بالاتر رفت.', 'info');
            return ['ok' => true, 'stage' => 'direct'];
        }
        q("UPDATE promotion_requests SET direct_decision = 'approve', direct_note = ?, direct_by = ?, direct_at = NOW() WHERE id = ?",
            [$note, $who, $id]);
        return promo_move_on($lvlRow, $id, $who, $empId);
    }
    if ($decision === 'approve' && $lvl === 'general' && route_next($lvlRow)['stage'] !== 'admin') {
        q("UPDATE promotion_requests SET senior_decision = 'approve', senior_note = ?, senior_by = ?, senior_at = NOW() WHERE id = ?",
            [$note, $who, $id]);
        return promo_move_on($lvlRow, $id, $who, $empId);
    }

    /* تأیید مدیر ارشد (با مبلغ افزایش حقوق پایه) ← منابع انسانی */
    $pfx = $level === 'general' ? 'senior' : 'direct';
    $raise = null;
    if ($decision === 'approve') {
        $raise = (int)digits((string)$amount);
        if ($raise <= 0) {
            return ['ok' => false, 'message' => 'مبلغ افزایش حقوق پایه‌ی کارمند را وارد کنید.'];
        }
    }
    $params = [$decision, $decision, $note, $who];
    $extra = '';
    if ($raise !== null) {
        $extra = ', raise_amount = ?';
        $params[] = $raise;
    }
    $params[] = $id;
    q("UPDATE promotion_requests SET stage = 'admin', to_admin_decision = ?, {$pfx}_decision = ?, {$pfx}_note = ?, {$pfx}_by = ?, {$pfx}_at = NOW()$extra WHERE id = ?",
        $params);

    if ($decision === 'approve') {
        notify_admins_by_role('admin', 'promotion', 'درخواست ارتقا برای ثبت نهایی منابع انسانی',
            $r['name'] . ' — تأیید مدیر ارشد (' . $who . ')، افزایش حقوق پایه: ' . money($raise) . ' ریال. لطفاً ثبت نهایی کنید.', 'info');
        notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد', 'مدیر ارشد تأیید کرد و درخواست نزد منابع انسانی است.', 'info');
        return ['ok' => true, 'stage' => 'admin'];
    }

    $isRe = $decision === 'reevaluate';
    notify_admins_by_role('admin', 'promotion',
        $isRe ? 'درخواست ارزیابی مجدد ارتقا' : 'درخواست ارتقا برای ثبت نهایی منابع انسانی',
        $r['name'] . ' — تصمیم ' . $who . ': ' . PROMO_DECISION_LABEL[$decision] . ($isRe ? '. لطفاً نمره‌ی ارزیابی را ثبت کنید.' : '.'),
        $isRe ? 'warn' : 'info');
    notify_emp($empId, 'promotion', 'درخواست ارتقای شما بررسی شد',
        'مدیر شما نظر خود را ثبت کرد و درخواست نزد منابع انسانی است.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

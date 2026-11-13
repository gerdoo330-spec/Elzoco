<?php
declare(strict_types=1);

/* ================= جریمه‌ی پرسنلی =================
 * ثبت توسط ادمین، مدیر ارشد یا مدیر (فقط برای زیرمجموعه‌ی خود). مبلغ از «انواع جریمه» گرفته می‌شود.
 * گردش تأیید همان مساعده و وام است و سطح‌های آن از «مدیران و تنظیمات» › «روش تأیید درخواست‌ها» خوانده می‌شود (کلید penalty).
 * پس از تأیید نهایی منابع انسانی، جریمه از فیش ماه بعد کسر می‌شود (اگر روش کسر «خودکار از سامانه» باشد).
 */

const PEN_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

/** ساخت جداول جریمه (بدون نیاز به اجرای دستی؛ نسخه‌ی SQL هم در db/upgrade-penalty.sql است) */
function penalty_ensure(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS penalty_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ) DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS penalty_amounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amount BIGINT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_penalty_amount (amount)
    ) DEFAULT CHARSET=utf8mb4");
    penalty_migrate_amounts();
    db()->exec("CREATE TABLE IF NOT EXISTS penalties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tracking VARCHAR(20) NOT NULL UNIQUE,
        employee_id INT NOT NULL,
        personnel_code VARCHAR(30) NOT NULL DEFAULT '',
        national_code VARCHAR(10) NOT NULL DEFAULT '',
        name VARCHAR(190) NOT NULL DEFAULT '',
        type_id INT NULL,
        type_title VARCHAR(190) NOT NULL DEFAULT '',
        amount BIGINT NOT NULL DEFAULT 0,
        jdate VARCHAR(12) NOT NULL DEFAULT '',
        note VARCHAR(500) NOT NULL DEFAULT '',
        created_by_pc VARCHAR(30) NOT NULL DEFAULT '',
        created_by VARCHAR(120) NOT NULL DEFAULT '',
        stage VARCHAR(20) NOT NULL DEFAULT 'direct',
        direct_pc VARCHAR(30) NOT NULL DEFAULT '',
        chain_pcs VARCHAR(500) NOT NULL DEFAULT '',
        chain_idx TINYINT NOT NULL DEFAULT 0,
        route VARCHAR(100) NOT NULL DEFAULT '',
        direct_decision VARCHAR(190) NOT NULL DEFAULT '',
        general_decision VARCHAR(190) NOT NULL DEFAULT '',
        to_admin_decision VARCHAR(20) NOT NULL DEFAULT '',
        decision_note VARCHAR(500) NOT NULL DEFAULT '',
        decided_by VARCHAR(120) NULL,
        decided_at DATETIME NULL,
        deduct_due_ym INT NULL,
        deducted_period_id INT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_pen_emp (employee_id, stage, deduct_due_ym),
        INDEX idx_pen_creator (created_by_pc)
    ) DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

/** نسخه‌ی قبلی، مبلغ را روی هر نوع جریمه ذخیره می‌کرد؛ مبلغ‌ها به فهرست جدا منتقل و ستون قدیمی حذف می‌شود */
function penalty_migrate_amounts(): void
{
    $col = one("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE()
                AND table_name = 'penalty_types' AND column_name = 'amount'");
    if ((int)$col['c'] === 0) {
        return;
    }
    db()->exec("INSERT IGNORE INTO penalty_amounts (amount, active, sort_order, created_at)
                SELECT DISTINCT amount, 1, 0, NOW() FROM penalty_types WHERE amount > 0");
    db()->exec('ALTER TABLE penalty_types DROP COLUMN amount');
}

/* ---------- نوع جریمه (فقط عنوان) ---------- */

function penalty_types_all(bool $activeOnly = false): array
{
    penalty_ensure();
    $where = $activeOnly ? 'WHERE active = 1' : '';
    return all("SELECT id, title, active, sort_order FROM penalty_types $where ORDER BY sort_order ASC, id ASC");
}

function penalty_type_save(array $in): array
{
    penalty_ensure();
    $id = (int)($in['id'] ?? 0);
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 190) {
        return ['ok' => false, 'message' => 'عنوان نوع جریمه را وارد کنید (حداکثر ۱۹۰ نویسه).'];
    }
    if ($id > 0) {
        if (!one('SELECT id FROM penalty_types WHERE id = ?', [$id])) {
            return ['ok' => false, 'message' => 'نوع جریمه پیدا نشد.'];
        }
        q('UPDATE penalty_types SET title = ? WHERE id = ?', [$title, $id]);
        return ['ok' => true, 'message' => 'نوع جریمه ویرایش شد.'];
    }
    $order = (int)(one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM penalty_types')['n'] ?? 1);
    q('INSERT INTO penalty_types (title, active, sort_order, created_at) VALUES (?,1,?,NOW())', [$title, $order]);
    return ['ok' => true, 'message' => 'نوع جریمه اضافه شد.'];
}

function penalty_type_toggle(int $id): array
{
    penalty_ensure();
    if (!one('SELECT id FROM penalty_types WHERE id = ?', [$id])) {
        return ['ok' => false, 'message' => 'نوع جریمه پیدا نشد.'];
    }
    q('UPDATE penalty_types SET active = 1 - active WHERE id = ?', [$id]);
    return ['ok' => true, 'message' => 'وضعیت نوع جریمه تغییر کرد.'];
}

/* ---------- فهرست مبلغ‌های جریمه (جدا از نوع جریمه) ---------- */

function penalty_amounts_all(bool $activeOnly = false): array
{
    penalty_ensure();
    $where = $activeOnly ? 'WHERE active = 1' : '';
    return all("SELECT id, amount, active, sort_order FROM penalty_amounts $where ORDER BY amount ASC, id ASC");
}

function penalty_amount_save(array $in): array
{
    penalty_ensure();
    $id = (int)($in['id'] ?? 0);
    $amount = (int)digits((string)($in['amount'] ?? '0'));
    if ($amount <= 0 || $amount > 1000000000000) {
        return ['ok' => false, 'message' => 'مبلغ جریمه باید بزرگ‌تر از صفر باشد.'];
    }
    $dup = one('SELECT id FROM penalty_amounts WHERE amount = ? AND id <> ?', [$amount, $id]);
    if ($dup) {
        return ['ok' => false, 'message' => 'این مبلغ قبلاً در فهرست ثبت شده است.'];
    }
    if ($id > 0) {
        if (!one('SELECT id FROM penalty_amounts WHERE id = ?', [$id])) {
            return ['ok' => false, 'message' => 'مبلغ پیدا نشد.'];
        }
        q('UPDATE penalty_amounts SET amount = ? WHERE id = ?', [$amount, $id]);
        return ['ok' => true, 'message' => 'مبلغ ویرایش شد. جریمه‌های قبلی با مبلغ ثبت‌شده‌ی خودشان باقی می‌مانند.'];
    }
    $order = (int)(one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM penalty_amounts')['n'] ?? 1);
    q('INSERT INTO penalty_amounts (amount, active, sort_order, created_at) VALUES (?,1,?,NOW())', [$amount, $order]);
    return ['ok' => true, 'message' => 'مبلغ به فهرست اضافه شد.'];
}

function penalty_amount_toggle(int $id): array
{
    penalty_ensure();
    if (!one('SELECT id FROM penalty_amounts WHERE id = ?', [$id])) {
        return ['ok' => false, 'message' => 'مبلغ پیدا نشد.'];
    }
    q('UPDATE penalty_amounts SET active = 1 - active WHERE id = ?', [$id]);
    return ['ok' => true, 'message' => 'وضعیت مبلغ تغییر کرد.'];
}

function penalty_source_get(): string
{
    // پیش‌فرض: خودکار از سامانه، چون جریمه‌ها قرار است از فیش کسر شوند
    return setting('penalty_source', 'system') === 'excel' ? 'excel' : 'system';
}

function penalty_settings(): array
{
    return ['types' => penalty_types_all(), 'amounts' => penalty_amounts_all(), 'source' => penalty_source_get()];
}

function penalty_source_save(array $in): array
{
    $v = (string)($in['source'] ?? '');
    if (!in_array($v, ['excel', 'system'], true)) {
        return ['ok' => false, 'message' => 'روش کسر جریمه معتبر نیست.'];
    }
    set_setting('penalty_source', $v);
    return ['ok' => true, 'message' => 'روش کسر جریمه ذخیره شد. از بارگذاری بعدی فایل اکسل اعمال می‌شود.'];
}

/* ---------- گردش تأیید ----------
 * اگر ثبت‌کننده خودش یکی از سطح‌های زنجیره‌ی مدیریت کارمند باشد، تأیید او همان لحظه‌ی ثبت حساب می‌شود
 * و گردش از سطح بالاتر از او ادامه پیدا می‌کند. مدیر ارشد ثبت‌کننده، مرحله‌ی ارشد را هم می‌گذراند.
 * منابع انسانی (ادمین) ثبت‌کننده، مستقیم به مرحله‌ی ثبت نهایی می‌رود.
 */
function penalty_plan(array $emp, array $admin): array
{
    $role = (string)$admin['role'];
    $pc = (string)($admin['pc'] ?? '');
    $name = (string)$admin['name'];
    if ($role === 'admin') {
        return ['stage' => 'admin', 'direct_pc' => '', 'chain' => '', 'route' => '', 'to_admin' => '',
            'direct_note' => '', 'general_note' => 'ثبت توسط منابع انسانی: ' . $name];
    }
    if ($role === 'senior') {
        return ['stage' => 'admin', 'direct_pc' => '', 'chain' => '', 'route' => '', 'to_admin' => 'approve',
            'direct_note' => '', 'general_note' => 'ثبت و تأیید توسط مدیر ارشد: ' . $name];
    }

    $levels = approval_route('penalty');
    $full = approval_chain((string)$emp['manager_pc']);
    $idx = $pc === '' ? false : array_search($pc, $full, true);
    $chain = [];
    $directNote = '';
    if ($idx === false) {
        foreach ($full as $j => $p) {
            if (($j === 0 && in_array('direct', $levels, true)) || ($j > 0 && in_array('upper', $levels, true))) {
                $chain[] = $p;
            }
        }
    } else {
        $directNote = 'ثبت و تأیید توسط: ' . $name;
        if (in_array('upper', $levels, true)) {
            $chain = array_slice($full, $idx + 1);
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
        'direct_pc' => $stage === 'direct' ? $chain[0] : '',
        'chain' => implode(',', $chain),
        'route' => implode(',', $steps),
        'to_admin' => '',
        'direct_note' => $directNote,
        'general_note' => '',
    ];
}

/** ثبت جریمه برای یک کارمند: نوع جریمه، مبلغ (از فهرست مبلغ‌ها)، تاریخ از تقویم و توضیح الزامی */
function penalty_create(array $admin, array $in): array
{
    penalty_ensure();
    $empId = (int)($in['employee_id'] ?? 0);
    $typeId = (int)($in['type_id'] ?? 0);
    $amountId = (int)($in['amount_id'] ?? 0);
    if (!admin_can_access_employee($admin, $empId)) {
        return ['ok' => false, 'message' => 'این کارمند در زیرمجموعه‌ی شما نیست.'];
    }
    $emp = one('SELECT * FROM employees WHERE id = ?', [$empId]);
    if (!$emp) {
        return ['ok' => false, 'message' => 'کارمند پیدا نشد.'];
    }
    $type = one('SELECT * FROM penalty_types WHERE id = ? AND active = 1', [$typeId]);
    if (!$type) {
        return ['ok' => false, 'message' => 'نوع جریمه‌ی انتخاب‌شده معتبر نیست.'];
    }
    $amountRow = one('SELECT * FROM penalty_amounts WHERE id = ? AND active = 1', [$amountId]);
    if (!$amountRow) {
        return ['ok' => false, 'message' => 'مبلغ انتخاب‌شده معتبر نیست.'];
    }
    $jdate = trim(fa_digits_to_en((string)($in['jdate'] ?? '')));
    $parsed = $jdate === '' ? null : jalali_parse($jdate);
    if (!$parsed) {
        return ['ok' => false, 'message' => 'تاریخ جریمه را از تقویم انتخاب کنید.'];
    }
    if (jalali_num($parsed) > jalali_num(jalali_today())) {
        return ['ok' => false, 'message' => 'تاریخ جریمه نمی‌تواند بعد از امروز باشد.'];
    }
    $note = trim((string)($in['note'] ?? ''));
    if ($note === '') {
        return ['ok' => false, 'message' => 'توضیح جریمه را بنویسید.'];
    }
    $note = mb_substr($note, 0, 500);
    $plan = penalty_plan($emp, $admin);
    $tracking = new_tracking('PN', 'penalties');
    $now = jalali_str(jalali_today()) . ' ' . date('H:i');
    $directDecision = $plan['direct_note'] !== '' ? $plan['direct_note'] . ' (' . $now . ')' : '';

    q('INSERT INTO penalties (tracking, employee_id, personnel_code, national_code, name, type_id, type_title, amount,
            jdate, note, created_by_pc, created_by, stage, direct_pc, chain_pcs, chain_idx, route,
            direct_decision, general_decision, to_admin_decision, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,NOW())', [
        $tracking, $empId, (string)$emp['personnel_code'], (string)$emp['national_code'], (string)$emp['name'],
        (int)$type['id'], (string)$type['title'], (int)$amountRow['amount'], jalali_str($parsed), $note,
        (string)($admin['pc'] ?? ''), (string)$admin['name'], $plan['stage'], $plan['direct_pc'], $plan['chain'],
        $plan['route'], $directDecision, $plan['general_note'], $plan['to_admin'],
    ]);
    notify_stage_holders($plan['stage'], $plan['direct_pc'], 'penalty', 'جریمه‌ی جدید برای بررسی',
        (string)$emp['name'] . ' — جریمه «' . $type['title'] . '» به مبلغ ' . money((int)$amountRow['amount']) . ' ریال ثبت شد.', 'info');
    return ['ok' => true, 'tracking' => $tracking,
        'message' => 'جریمه ثبت شد و در گردش تأیید قرار گرفت. کد پیگیری: ' . $tracking];
}

/* ---------- تصمیم‌گیری (همان قواعد مساعده/وام) ---------- */

function penalty_move_on(array $row, int $id, string $who): array
{
    $n = route_next($row);
    $emp = (int)$row['employee_id'];
    $title = 'جریمه «' . $row['type_title'] . '»';
    if ($n['stage'] === 'direct') {
        q("UPDATE penalties SET stage = 'direct', direct_pc = ?, chain_idx = 0 WHERE id = ?", [$n['direct_pc'], $id]);
        notify_stage_holders('direct', $n['direct_pc'], 'penalty', 'جریمه نزد مدیر مستقیم', $row['name'] . ' — ' . $title . ' منتظر تأیید شماست.', 'info');
        return ['ok' => true, 'stage' => 'direct'];
    }
    if ($n['stage'] === 'general') {
        q("UPDATE penalties SET stage = 'general' WHERE id = ?", [$id]);
        notify_stage_holders('general', '', 'penalty', 'جریمه نزد مدیر ارشد', $row['name'] . ' — ' . $title . ' منتظر تأیید مدیر ارشد است.', 'info');
        return ['ok' => true, 'stage' => 'general'];
    }
    q("UPDATE penalties SET stage = 'admin', to_admin_decision = 'approve' WHERE id = ?", [$id]);
    notify_stage_holders('admin', '', 'penalty', 'جریمه برای ثبت نهایی منابع انسانی', $row['name'] . ' — ' . $title . ' از مسیر تأیید گذشت.', 'info');
    return ['ok' => true, 'stage' => 'admin'];
}

/** تأیید نهایی: جریمه در پرونده ثبت و برای کسر از فیش ماه بعد زمان‌بندی می‌شود */
function penalty_finalize(array $row, string $who, string $source, string $note = ''): array
{
    $id = (int)$row['id'];
    $emp = (int)$row['employee_id'];
    $now = jalali_str(jalali_today()) . ' ' . date('H:i');
    $fromStage = $source === 'senior' ? 'general' : 'admin';
    $generalDecision = $source === 'senior'
        ? chain_note_append((string)$row['general_decision'], 'تأیید نهایی مدیر ارشد: ' . $who . ' (' . $now . ')')
        : (string)$row['general_decision'];
    $dueYm = jalali_ym(jalali_today()) + 1; // کسر از فیش ماه بعد
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = q("UPDATE penalties SET stage = 'final', decided_by = ?, decided_at = NOW(), decision_note = ?,
                    general_decision = ?, to_admin_decision = ?, deduct_due_ym = ?
                  WHERE id = ? AND stage = ?", [
            $who, $note !== '' ? $note : (string)$row['decision_note'], $generalDecision,
            $source === 'senior' ? 'notified' : 'approve', $dueYm, $id, $fromStage,
        ]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'این جریمه قبلاً نهایی شده یا مرحله‌ی آن تغییر کرده است.'];
        }
        employee_history_add($emp, 'penalty', $id, 'final_approval', 'ثبت جریمه «' . $row['type_title'] . '»',
            'کد پیگیری: ' . $row['tracking'] . ' | مبلغ: ' . money((int)$row['amount']) . ' ریال | تاریخ: ' . (string)$row['jdate']
            . ' | ثبت‌کننده: ' . (string)$row['created_by'] . ' | تأیید نهایی: ' . $who
            . ($row['note'] !== '' ? ' | توضیح: ' . (string)$row['note'] : ''), $who);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    notify_emp($emp, 'penalty', 'جریمه‌ی جدید در پرونده‌ی شما ثبت شد',
        '«' . $row['type_title'] . '» به مبلغ ' . money((int)$row['amount']) . ' ریال. کسر آن از فیش ماه بعد انجام می‌شود.', 'warn');
    if ($source === 'senior') {
        notify_admins_by_role('admin', 'penalty', 'تأیید نهایی جریمه', $row['name'] . ' — ' . $row['tracking'] . ' توسط ' . $who . ' تأیید نهایی شد.', 'ok');
    }
    return ['ok' => true, 'stage' => 'final', 'message' => 'جریمه تأیید نهایی شد و در پرونده‌ی پرسنلی ثبت شد.'];
}

function penalty_decide(array $sess, int $id, string $decision, string $note, string $who): array
{
    penalty_ensure();
    $row = one('SELECT * FROM penalties WHERE id = ?', [$id]);
    if (!$row) {
        return ['ok' => false, 'message' => 'جریمه پیدا نشد.'];
    }
    if (!approval_can_decide($sess, (string)$row['stage'], (string)$row['direct_pc'])) {
        return ['ok' => false, 'message' => 'این جریمه در مرحله‌ی فعلی در اختیار شما نیست.'];
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'تصمیم نامعتبر است.'];
    }
    $note = trim($note);
    if ($decision === 'reject' && $note === '') {
        return ['ok' => false, 'message' => 'برای رد جریمه، دلیل را بنویسید.'];
    }
    $now = jalali_str(jalali_today()) . ' ' . date('H:i');
    $stage = (string)$row['stage'];
    $emp = (int)$row['employee_id'];
    $title = 'جریمه «' . $row['type_title'] . '»';

    if ($stage === 'admin') {
        if ($decision === 'approve') {
            if ((string)$row['to_admin_decision'] === 'reject') {
                return ['ok' => false, 'message' => 'این جریمه رد شده است و فقط ثبت رد نهایی ممکن است.'];
            }
            return penalty_finalize($row, $who, 'hr', $note);
        }
        if ((string)$row['to_admin_decision'] === 'approve') {
            return ['ok' => false, 'message' => 'این جریمه پس از تأیید مدیر ارشد است؛ فقط ثبت نهایی ممکن است.'];
        }
        q("UPDATE penalties SET stage = 'rejected', to_admin_decision = 'rejected', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?",
            [$who, $note, $id]);
        notify_admin_pc((string)$row['created_by_pc'], 'penalty', $title . ' رد نهایی شد', $row['name'] . ' — ' . $note, 'bad');
        return ['ok' => true, 'stage' => 'rejected'];
    }

    if ($stage === 'direct') {
        if ($decision === 'approve') {
            $dec = chain_note_append((string)$row['direct_decision'], 'تأیید: ' . $who . ' (' . $now . ')');
            $next = chain_next_pc($row);
            if ($next !== null) {
                q('UPDATE penalties SET direct_pc = ?, chain_idx = ?, direct_decision = ? WHERE id = ?',
                    [$next, (int)$row['chain_idx'] + 1, $dec, $id]);
                notify_stage_holders('direct', $next, 'penalty', 'جریمه نزد سطح بالاتر', $row['name'] . ' — ' . $title . ' منتظر تأیید شماست.', 'info');
                return ['ok' => true, 'stage' => 'direct'];
            }
            q('UPDATE penalties SET direct_decision = ? WHERE id = ?', [$dec, $id]);
            return penalty_move_on($row, $id, $who);
        }
        q("UPDATE penalties SET stage = 'admin', to_admin_decision = 'reject', direct_decision = ?, decision_note = ? WHERE id = ?",
            ['رد: ' . $who . ' (' . $now . ')', $note, $id]);
        notify_stage_holders('admin', '', 'penalty', 'جریمه برای ثبت نهایی منابع انسانی', $row['name'] . ' — ' . $title . ' توسط مدیر رد شده است.', 'warn');
        return ['ok' => true, 'stage' => 'admin'];
    }

    if ($stage === 'general') {
        if ($decision === 'approve') {
            return penalty_finalize($row, $who, 'senior', $note);
        }
        q("UPDATE penalties SET stage = 'admin', to_admin_decision = 'reject', general_decision = ?, decision_note = ? WHERE id = ?",
            ['رد: ' . $who . ' (' . $now . ')', $note, $id]);
        notify_stage_holders('admin', '', 'penalty', 'جریمه برای ثبت نهایی منابع انسانی', $row['name'] . ' — ' . $title . ' توسط مدیر ارشد رد شده است.', 'warn');
        return ['ok' => true, 'stage' => 'admin'];
    }
    return ['ok' => false, 'message' => 'این جریمه در مرحله‌ی فعلی قابل تصمیم نیست.'];
}

/** لغو جریمه‌ی تأییدشده توسط ادمین؛ فقط تا پیش از کسر از فیش */
function penalty_cancel(array $admin, int $id, string $note): array
{
    penalty_ensure();
    $row = one('SELECT * FROM penalties WHERE id = ?', [$id]);
    if (!$row) {
        return ['ok' => false, 'message' => 'جریمه پیدا نشد.'];
    }
    if ((string)$row['stage'] !== 'final') {
        return ['ok' => false, 'message' => 'فقط جریمه‌های تأییدنهایی‌شده لغو می‌شوند؛ برای درخواست در جریان از رد استفاده کنید.'];
    }
    if (!empty($row['deducted_period_id'])) {
        return ['ok' => false, 'message' => 'این جریمه از فیش کسر شده و دیگر قابل لغو نیست.'];
    }
    $note = trim($note);
    if ($note === '') {
        return ['ok' => false, 'message' => 'دلیل لغو جریمه را بنویسید.'];
    }
    q("UPDATE penalties SET stage = 'rejected', to_admin_decision = 'cancelled', decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?",
        [$note, (string)$admin['name'], $id]);
    employee_history_add((int)$row['employee_id'], 'penalty', $id, 'cancelled', 'لغو جریمه «' . $row['type_title'] . '»',
        'کد پیگیری: ' . $row['tracking'] . ' | دلیل لغو: ' . $note, (string)$admin['name']);
    return ['ok' => true, 'message' => 'جریمه لغو شد و از کسر فیش خارج شد.'];
}

/* ---------- فهرست‌ها ---------- */

function penalty_admin_list(array $sess, array $filter = []): array
{
    penalty_ensure();
    $where = '1=1';
    $params = [];
    if (!empty($filter['stage'])) {
        $where .= ' AND stage = ?';
        $params[] = (string)$filter['stage'];
    }
    if (!empty($filter['q'])) {
        $where .= ' AND (tracking LIKE ? OR name LIKE ? OR personnel_code LIKE ? OR national_code LIKE ?)';
        $like = '%' . trim((string)$filter['q']) . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $rows = all("SELECT * FROM penalties WHERE $where ORDER BY id DESC LIMIT 1000", $params);
    $pc = (string)($sess['pc'] ?? '');
    $out = [];
    foreach ($rows as $r) {
        $mine = $pc !== '' && (string)$r['created_by_pc'] === $pc;
        if (!$mine && !approval_can_see($sess, (string)$r['stage'], (string)$r['direct_pc'])) {
            continue;
        }
        $r['can_decide'] = approval_can_decide($sess, (string)$r['stage'], (string)$r['direct_pc']);
        $r['can_cancel'] = $sess['role'] === 'admin' && $r['stage'] === 'final' && empty($r['deducted_period_id']);
        $r['deduct_label'] = penalty_ym_label($r['deduct_due_ym'] !== null ? (int)$r['deduct_due_ym'] : null);
        $out[] = $r;
    }
    return $out;
}

function penalty_pending_count(array $sess): int
{
    return count(array_filter(penalty_admin_list($sess), fn($x) => $x['can_decide']));
}

/** جریمه‌های تأییدنهایی‌شده‌ی کارمند در پنل خودش */
function penalty_list_for_employee(int $employeeId): array
{
    penalty_ensure();
    $rows = all("SELECT tracking, type_title, amount, jdate, note, deduct_due_ym, deducted_period_id, decided_at
                 FROM penalties WHERE employee_id = ? AND stage = 'final' ORDER BY id DESC LIMIT 100", [$employeeId]);
    foreach ($rows as $i => $r) {
        $rows[$i]['deduct_label'] = penalty_ym_label($r['deduct_due_ym'] !== null ? (int)$r['deduct_due_ym'] : null);
        $rows[$i]['deducted'] = !empty($r['deducted_period_id']);
    }
    return $rows;
}

function penalty_ym_label(?int $ym): string
{
    if ($ym === null || $ym <= 0) {
        return '';
    }
    $y = intdiv($ym - 1, 12);
    $m = ($ym - 1) % 12;
    return PEN_MONTHS[$m] . ' ' . $y;
}

/**
 * مبلغ جریمه‌ی این کارمند برای فیش دوره‌ی جاری (فقط وقتی روش کسر «خودکار از سامانه» باشد).
 * جریمه‌ها با شناسه‌ی دوره علامت می‌خورند تا بارگذاری مجدد همان ماه دوباره جمع نزند.
 */
function penalty_deduct_for_period(int $empId, int $periodId, int $periodYm): float
{
    penalty_ensure();
    $where = "employee_id = ? AND stage = 'final' AND deduct_due_ym <= ? AND (deducted_period_id IS NULL OR deducted_period_id = ?)";
    q("UPDATE penalties SET deducted_period_id = ? WHERE $where", [$periodId, $empId, $periodYm, $periodId]);
    $sum = one("SELECT COALESCE(SUM(amount), 0) AS s FROM penalties WHERE employee_id = ? AND deducted_period_id = ?", [$empId, $periodId]);
    return (float)$sum['s'];
}

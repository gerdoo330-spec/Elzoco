<?php
declare(strict_types=1);

/* ---------- اعلان‌ها (پاپ‌آپ + شمارنده‌ی سایدبار) ----------
 * گیرنده: «admin:ID» برای حساب‌های مدیریت و «emp:ID» برای کارمند.
 * اگر جدول notifications هنوز ساخته نشده باشد، ثبت درخواست‌ها بدون خطا ادامه پیدا می‌کند.
 */

const NOTIFY_KIND_TAB = ['advance' => 'advances', 'loan' => 'advances', 'leave' => 'leaves', 'promotion' => 'promotions'];

function notify_add(string $recipient, string $kind, string $title, string $body = '', string $tone = 'info', bool $sendPush = true): void
{
    try {
        q('INSERT INTO notifications (recipient, kind, tone, title, body, tab, shown, created_at) VALUES (?,?,?,?,?,?,0,NOW())',
            [$recipient, $kind, $tone, mb_substr($title, 0, 190), mb_substr($body, 0, 500),
             str_starts_with($recipient, 'admin:') ? (NOTIFY_KIND_TAB[$kind] ?? '') : $kind]);
        if ($sendPush && function_exists('push_send_recipient')) {
            try {
                push_send_recipient($recipient, $title, $body, $kind, $tone);
            } catch (Throwable $pushError) {
                error_log('[notify push] ' . $pushError->getMessage());
            }
        }
    } catch (Throwable $ex) {
        error_log('[notify] ' . $ex->getMessage());
    }
}

function notify_admins_by_role(string $role, string $kind, string $title, string $body = '', string $tone = 'info'): int
{
    $n = 0;
    foreach (all('SELECT id FROM admins WHERE role = ? AND active = 1', [$role]) as $a) {
        notify_add('admin:' . $a['id'], $kind, $title, $body, $tone);
        $n++;
    }
    return $n;
}

function notify_admin_pc(string $pc, string $kind, string $title, string $body = '', string $tone = 'info'): int
{
    if ($pc === '') {
        return 0;
    }
    $n = 0;
    foreach (all("SELECT id FROM admins WHERE personnel_code = ? AND active = 1 AND role IN ('manager','senior')", [$pc]) as $a) {
        notify_add('admin:' . $a['id'], $kind, $title, $body, $tone);
        $n++;
    }
    return $n;
}

function notify_emp(int $employeeId, string $kind, string $title, string $body = '', string $tone = 'info', bool $sendPush = true): void
{
    notify_add('emp:' . $employeeId, $kind, $title, $body, $tone, $sendPush);
}

/** اعلان برای کسی که الان باید درخواست را بررسی کند: direct = مدیر مستقیم، general = مدیر ارشد (در نبودش ادمین)، admin = ادمین */
function notify_stage_holders(string $stage, string $directPc, string $kind, string $title, string $body = '', string $tone = 'info'): void
{
    if ($stage === 'direct') {
        if (notify_admin_pc($directPc, $kind, $title, $body, $tone) > 0) {
            return;
        }
        $stage = 'general';
    }
    if ($stage === 'general') {
        if (notify_admins_by_role('senior', $kind, $title, $body, $tone) > 0) {
            return;
        }
    }
    notify_admins_by_role('admin', $kind, $title, $body, $tone);
}

function notify_poll(string $recipient): array
{
    try {
        return all('SELECT id, kind, tone, title, body, tab, created_at FROM notifications
                    WHERE recipient = ? AND shown = 0 ORDER BY id ASC LIMIT 6', [$recipient]);
    } catch (Throwable $ex) {
        return [];
    }
}

function notify_ack(string $recipient, array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        q("UPDATE notifications SET shown = 1 WHERE recipient = ? AND id IN ($in)", array_merge([$recipient], $ids));
        q('DELETE FROM notifications WHERE shown = 1 AND created_at < (NOW() - INTERVAL 30 DAY)');
    } catch (Throwable $ex) {
        error_log('[notify ack] ' . $ex->getMessage());
    }
}

/** تعداد درخواست‌هایی که در انتظار تصمیم همین کاربر است (شمارنده‌ی سایدبار) */
function admin_pending_counts(array $a): array
{
    return [
        'adv' => count(array_filter(advance_admin_list([], $a), fn($x) => $x['can_decide'])),
        'lv'  => count(array_filter(leave_manager_list($a), fn($x) => $x['can_decide'])),
        'pr'  => count(array_filter(promo_admin_list($a), fn($x) => $x['can_decide'])),
    ];
}

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();
$in = request_data();

try {
    $admin = require_admin_json();
    if (!in_array((string)($admin['role'] ?? ''), ['admin', 'senior'], true)) {
        json_out(['ok' => false, 'message' => 'فقط ادمین و مدیر ارشد مجاز به ارسال اطلاعیه هستند.'], 403);
    }

    $action = (string)($in['action'] ?? 'list');

    if ($action === 'list') {
        $rows = all(
            'SELECT id,title,body,kind,audience,audience_value,created_at,active,recipient_count
             FROM announcements ORDER BY id DESC LIMIT 100'
        );
        json_out(['ok' => true, 'announcements' => $rows]);
    }

    if ($action === 'deactivate') {
        $id = (int)($in['id'] ?? 0);
        if ($id < 1) {
            json_out(['ok' => false, 'message' => 'شناسه معتبر نیست.'], 422);
        }
        q('UPDATE announcements SET active=0 WHERE id=?', [$id]);
        json_out(['ok' => true, 'message' => 'اطلاعیه غیرفعال شد.']);
    }

    if ($action !== 'create') {
        json_out(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 400);
    }

    $title = trim((string)($in['title'] ?? ''));
    $body = trim((string)($in['body'] ?? ''));
    $kind = (string)($in['kind'] ?? 'general');

    if ($title === '' || $body === '') {
        json_out(['ok' => false, 'message' => 'عنوان و متن اطلاعیه الزامی است.'], 422);
    }
    if (mb_strlen($title) > 190 || mb_strlen($body) > 5000) {
        json_out(['ok' => false, 'message' => 'طول عنوان یا متن بیش از حد مجاز است.'], 422);
    }
    if (!in_array($kind, ['general', 'urgent', 'reminder', 'news'], true)) {
        $kind = 'general';
    }

    // این نسخه فقط از ستون‌ها و جدول‌هایی استفاده می‌کند که در schema اصلی پروژه وجود دارند.
    // بخش‌بندی بر اساس سکشن/شعبه تا زمان وجود منبع قطعی آن در دیتابیس فعال نمی‌شود.
    $employees = all(
        'SELECT id FROM employees WHERE in_latest=1 AND inactive=0 ORDER BY id'
    );
    if (!$employees) {
        json_out(['ok' => false, 'message' => 'هیچ کارمند فعالی برای دریافت اطلاعیه پیدا نشد.'], 422);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO announcements
             (title,body,kind,audience,audience_value,created_by,created_at,active,recipient_count)
             VALUES (?,?,?,?,?,?,NOW(),1,?)',
            [mb_substr($title, 0, 190), $body, $kind, 'all', '', (int)$admin['id'], count($employees)]
        );
        $announcementId = (int)$pdo->lastInsertId();

        $notification = $pdo->prepare(
            'INSERT INTO notifications (recipient,kind,tone,title,body,tab,shown,created_at)
             VALUES (?,?,?,?,?,?,0,NOW())'
        );
        $tone = $kind === 'urgent' ? 'danger' : 'info';
        foreach ($employees as $employee) {
            $notification->execute([
                'emp:' . (int)$employee['id'],
                'announcement',
                $tone,
                mb_substr($title, 0, 190),
                mb_substr($body, 0, 500),
                'announcement',
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    try {
        push_send_employees(array_map(static fn($row) => (int)$row['id'], $employees), $title, $body, $kind, $tone);
    } catch (Throwable $pushError) {
        error_log('[announcements push] ' . $pushError->getMessage());
    }

    json_out([
        'ok' => true,
        'id' => $announcementId,
        'recipient_count' => count($employees),
        'message' => 'اطلاعیه در صندوق اعلان داخلی ' . count($employees) . ' کارمند فعال ثبت شد.',
    ]);
} catch (Throwable $e) {
    error_log('[announcements] ' . $e->getMessage());
    json_out(['ok' => false, 'message' => 'خطا در ثبت اطلاعیه. ساختار دیتابیس را بررسی کنید.'], 500);
}

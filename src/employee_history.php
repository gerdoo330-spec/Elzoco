<?php
declare(strict_types=1);

/**
 * پرونده‌ی پرسنلی: ثبت رویدادهای قطعی درخواست‌ها.
 * جدول به‌صورت تنبل و خودکار ساخته می‌شود تا ارتقای دیتابیس فعلی نیازمند نصب دستی نباشد.
 */
function employee_history_ensure(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS employee_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        source_type VARCHAR(20) NOT NULL,
        source_id INT NOT NULL,
        event_type VARCHAR(40) NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NOT NULL,
        actor_name VARCHAR(120) NOT NULL DEFAULT '',
        event_jdate VARCHAR(12) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_employee_history_source (source_type, source_id, event_type),
        INDEX idx_employee_history_employee (employee_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

/**
 * ثبت idempotent رویداد در پرونده‌ی کارمند؛ اجرای دوباره‌ی همان مرحله رکورد تکراری نمی‌سازد.
 * پیش از شروع transaction باید employee_history_ensure() فراخوانی شده باشد.
 */
function employee_history_add(
    int $employeeId,
    string $sourceType,
    int $sourceId,
    string $eventType,
    string $title,
    string $description,
    string $actorName
): void {
    employee_history_ensure();
    q('INSERT IGNORE INTO employee_history
        (employee_id, source_type, source_id, event_type, title, description, actor_name, event_jdate, created_at)
        VALUES (?,?,?,?,?,?,?,?,NOW())', [
            $employeeId,
            mb_substr($sourceType, 0, 20),
            $sourceId,
            mb_substr($eventType, 0, 40),
            mb_substr($title, 0, 190),
            $description,
            mb_substr($actorName, 0, 120),
            jalali_str(jalali_today()),
        ]);
}

function employee_history_list(int $employeeId): array
{
    employee_history_ensure();
    return all('SELECT id, source_type, source_id, event_type, title, description, actor_name, event_jdate, created_at
                FROM employee_history WHERE employee_id = ? ORDER BY id DESC LIMIT 200', [$employeeId]);
}

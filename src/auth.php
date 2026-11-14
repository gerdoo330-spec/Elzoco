<?php
declare(strict_types=1);

function emp_session(): ?array
{
    start_session_secure();
    $id = $_SESSION['emp_id'] ?? null;
    if (!$id) {
        return null;
    }
    $emp = one('SELECT * FROM employees WHERE id = ?', [(int)$id]);
    // اگر وضعیت حساب عوض شده یا در فایل جدید نیست، نشست بی‌اعتبار می‌شود
    if (!$emp || !(bool)$emp['in_latest'] || (bool)$emp['inactive']) {
        unset($_SESSION['emp_id']);
        return null;
    }
    return $emp;
}

function require_emp_json(): array
{
    $emp = emp_session();
    if (!$emp) {
        json_out(['ok' => false, 'message' => 'نشست شما پایان یافته است. دوباره وارد شوید.', 'logout' => true], 401);
    }
    return $emp;
}

function require_emp_page(): array
{
    $emp = emp_session();
    if (!$emp) {
        header('Location: index.php');
        exit;
    }
    return $emp;
}

function admin_session(): ?array
{
    start_session_secure();
    $s = $_SESSION['admin'] ?? null;
    if (!$s) {
        return null;
    }
    // نقش و کد پرسنلی هر بار از دیتابیس خوانده می‌شود تا تغییر حساب بدون ورود دوباره اعمال شود
    $row = one('SELECT id, name, role, personnel_code, active FROM admins WHERE id = ?', [(int)$s['id']]);
    if (!$row || !(int)$row['active']) {
        unset($_SESSION['admin']);
        return null;
    }
    return ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'role' => (string)$row['role'],
            'pc' => (string)($row['personnel_code'] ?? '')];
}

function require_admin_json(string $role = 'manager'): array
{
    $a = admin_session();
    if (!$a) {
        json_out(['ok' => false, 'message' => 'نشست مدیر پایان یافته است. دوباره وارد شوید.', 'logout' => true], 401);
    }
    if ($role === 'admin' && !is_full_access($a['role'])) {
        json_out(['ok' => false, 'message' => 'این بخش فقط برای مدیران با دسترسی کامل است.'], 403);
    }
    return $a;
}

function require_admin_page(): array
{
    $a = admin_session();
    if (!$a) {
        header('Location: index.php');
        exit;
    }
    return $a;
}

/* ---------- دسترسی و زنجیره‌ی تأیید (مرخصی و مساعده) ----------
 * نقش‌ها:
 *   admin   : دسترسی کامل (ادمین سیستم)
 *   senior  : مدیر ارشد؛ دسترسی کامل به پنل و تأیید مرحله‌ی ارشد
 *   manager : مدیر مستقیم؛ فقط درخواست‌های کارمندانی که مدیر مستقیمشان است
 * مسیر درخواست: مدیر مستقیم کارمند (stage = direct) ← اگر کارمند مدیر مستقیم ندارد، مستقیم به مدیر ارشد (stage = general)
 * سپس مدیر ارشد تأیید نهایی می‌کند (stage = final)
 */
function is_full_access(string $role): bool
{
    return in_array($role, ['admin', 'senior'], true);
}

/**
 * فقط شناسه‌ی کارکنانی که مدیر مستقیمشان همین حساب است.
 * خروجی null برای دسترسی کامل و آرایه برای نقش مدیر مستقیم است.
 * دسترسی به کارکنانِ زیرمجموعه‌ی مدیران دیگر به‌صورت بازگشتی مجاز نیست.
 */
function employee_scope_collect_ids(string $rootPc, array $rows): array
{
    $rootPc = trim($rootPc);
    if ($rootPc === '') return [];

    $ids = [];
    foreach ($rows as $row) {
        if (trim((string)($row['manager_pc'] ?? '')) !== $rootPc) continue;
        $employeeId = (int)($row['id'] ?? 0);
        if ($employeeId > 0) $ids[$employeeId] = true;
    }
    return array_map('intval', array_keys($ids));
}

function admin_employee_scope_ids(array $admin): ?array
{
    static $cache = [];
    if (is_full_access((string)($admin['role'] ?? ''))) return null;
    if (($admin['role'] ?? '') !== 'manager') return [];

    $rootPc = trim((string)($admin['pc'] ?? ''));
    if ($rootPc === '') return [];
    if (array_key_exists($rootPc, $cache)) return $cache[$rootPc];

    // فقط کدهای مدیر مستقیم خوانده می‌شوند؛ زیرمجموعه‌های غیرمستقیم مجاز نیستند.
    $rows = all("SELECT id, personnel_code, manager_pc FROM employees
                 WHERE manager_pc <> '' OR personnel_code <> ''");
    $cache[$rootPc] = employee_scope_collect_ids($rootPc, $rows);
    return $cache[$rootPc];
}

/** جلوگیری از دسترسی مستقیم به فیش یا اطلاعات یک کارمند خارج از زیرمجموعه‌ی مدیر. */
function admin_can_access_employee(array $admin, int $employeeId): bool
{
    if ($employeeId <= 0) return false;
    $scope = admin_employee_scope_ids($admin);
    return $scope === null || in_array($employeeId, $scope, true);
}

function require_employee_scope_access(array $admin, int $employeeId): void
{
    if (!admin_can_access_employee($admin, $employeeId)) {
        json_out(['ok' => false, 'message' => 'شما فقط به اطلاعات کارکنان زیرمجموعه‌ی خود دسترسی دارید.'], 403);
    }
}

/** آیا این کاربر می‌تواند درخواستی را در این مرحله ببیند؟ */
function approval_can_see(array $sess, string $stage, string $directPc): bool
{
    if (is_full_access((string)$sess['role'])) {
        return true;
    }
    return $sess['role'] === 'manager' && $stage === 'direct' && $sess['pc'] !== '' && $sess['pc'] === $directPc;
}

/** آیا این کاربر می‌تواند در این مرحله تصمیم بگیرد؟ */
function approval_can_decide(array $sess, string $stage, string $directPc): bool
{
    if (in_array($stage, ['final', 'rejected'], true)) {
        return false;
    }
    /* منابع انسانی (ادمین) فقط در مرحله‌ی نهایی تصمیم می‌گیرد؛ در مراحل مدیر مستقیم و مدیر ارشد نمی‌تواند جای آن‌ها تصمیم بگیرد */
    if ($stage === 'admin') {
        return $sess['role'] === 'admin';
    }
    if ($stage === 'direct') {
        return in_array($sess['role'], ['manager', 'senior'], true) && $sess['pc'] !== '' && $sess['pc'] === $directPc;
    }
    if ($stage === 'general') {
        return $sess['role'] === 'senior';
    }
    return false;
}

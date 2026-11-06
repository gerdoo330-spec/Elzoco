<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();
$in = request_data();
$action = (string)($in['action'] ?? '');

try {
    switch ($action) {
        case 'login': {
            $u = trim((string)($in['username'] ?? ''));
            $row = one('SELECT * FROM admins WHERE username = ? AND active = 1', [$u]);
            if (!$row || !password_verify((string)($in['password'] ?? ''), $row['password_hash'])) {
                usleep(400000);
                json_out(['ok' => false, 'message' => 'نام کاربری یا رمز عبور صحیح نیست.']);
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = [
                'id' => (int)$row['id'], 'name' => $row['name'], 'role' => $row['role'],
                'pc' => (string)($row['personnel_code'] ?? ''),
            ];
            json_out(['ok' => true]);
        }

        case 'logout': {
            unset($_SESSION['admin']);
            json_out(['ok' => true]);
        }

        case 'dashboard': {
            $a = require_admin_json();
            $scopeIds = admin_employee_scope_ids($a);
            if ($scopeIds === null) {
                $counts = one("SELECT COUNT(*) AS total,
                    COALESCE(SUM(in_latest = 1 AND inactive = 0),0) AS active,
                    COALESCE(SUM(in_latest = 0 OR inactive = 1),0) AS inactive,
                    COALESCE(SUM(personnel_ok = 0),0) AS incomplete FROM employees");
            } elseif (!$scopeIds) {
                $counts = ['total' => 0, 'active' => 0, 'inactive' => 0, 'incomplete' => 0];
            } else {
                $scopeMarks = implode(',', array_fill(0, count($scopeIds), '?'));
                $counts = one("SELECT COUNT(*) AS total,
                    COALESCE(SUM(in_latest = 1 AND inactive = 0),0) AS active,
                    COALESCE(SUM(in_latest = 0 OR inactive = 1),0) AS inactive,
                    COALESCE(SUM(personnel_ok = 0),0) AS incomplete FROM employees WHERE id IN ($scopeMarks)", $scopeIds);
            }
            $pending = admin_pending_counts($a);
            $period = one('SELECT * FROM periods ORDER BY year DESC, month DESC LIMIT 1');
            $fullAccess = $scopeIds === null;
            json_out(['ok' => true, 'admin' => $a, 'counts' => $counts, 'pending' => $pending,
                // گزارش آخرین بارگذاری شامل نام کارکنان کل سازمان است؛ فقط دسترسی کامل آن را می‌بیند.
                'latest_period' => $period ? ['label' => period_label($period), 'file' => $period['file_name'],
                    'uploaded_at' => $period['uploaded_at'], 'report' => $fullAccess ? json_decode((string)$period['report'], true) : null] : null,
                'periods' => $fullAccess ? array_map(fn($p) => ['id' => (int)$p['id'], 'label' => period_label($p),
                    'uploaded_at' => $p['uploaded_at']], all('SELECT * FROM periods ORDER BY year DESC, month DESC LIMIT 24')) : [],
                'windows' => ['advance' => advance_window('advance'), 'loan' => advance_window('loan'), 'payslip' => payslip_window()],
                'rules' => fin_rules(), 'promo_mode' => $fullAccess ? promo_apply_mode() : null, 'approval_flows' => $fullAccess ? approval_flows() : []]);
        }

        case 'promo_mode_save': {
            require_admin_json('admin');
            json_out(promo_mode_save($in));
        }

        case 'approval_flows_save': {
            require_admin_json('admin');
            json_out(approval_flows_save($in));
        }

        case 'upload': {
            $a = require_admin_json('admin');
            $year = (int)($_POST['year'] ?? 0);
            $month = (int)($_POST['month'] ?? 0);
            if ($year < 1390 || $year > 1500 || $month < 1 || $month > 12) {
                json_out(['ok' => false, 'message' => 'سال و ماه دوره را درست انتخاب کنید.']);
            }
            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                json_out(['ok' => false, 'message' => 'فایل اکسل دریافت نشد (حجم یا خطای آپلود).']);
            }
            $name = (string)$_FILES['file']['name'];
            if (!preg_match('/\.xlsx$/i', $name)) {
                json_out(['ok' => false, 'message' => 'فقط فایل xlsx پذیرفته می‌شود.']);
            }
            if ($_FILES['file']['size'] > cfg('upload_max_mb', 40) * 1024 * 1024) {
                json_out(['ok' => false, 'message' => 'حجم فایل بیش از حد مجاز است.']);
            }
            $report = payroll_import($_FILES['file']['tmp_name'], $year, $month, $name, $a['name']);
            json_out(['ok' => true, 'report' => $report, 'message' => 'فایل با موفقیت بارگذاری و همه‌ی اطلاعات به‌روز شد.']);
        }

        case 'employees': {
            $a = require_admin_json();
            employee_profiles_ensure();
            $q = trim((string)($in['q'] ?? ''));
            $filter = (string)($in['filter'] ?? 'all');
            $selectedSection = trim((string)($in['section'] ?? ''));
            // سکشن از اطلاعات تکمیلی پروفایل خوانده می‌شود؛ شعبه و عنوان شغلی با سکشن یکی نیستند.
            $safeExtra = "CASE WHEN JSON_VALID(ep.extra_json) THEN ep.extra_json ELSE '{}' END";
            $sectionExpr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT($safeExtra, '$.section')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT($safeExtra, '$.department')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT($safeExtra, '$.unit')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT($safeExtra, '$.work_section')), ''), '')";

            $scopeIds = admin_employee_scope_ids($a);
            $scopeWhere = '1=1';
            $scopeParams = [];
            if ($scopeIds !== null) {
                if (!$scopeIds) {
                    $scopeWhere = '1=0';
                } else {
                    $scopeWhere = 'e.id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')';
                    $scopeParams = $scopeIds;
                }
            }

            $sections = all("SELECT DISTINCT TRIM($sectionExpr) AS section_name
                FROM employees e LEFT JOIN employee_profiles ep ON ep.employee_id = e.id
                WHERE $scopeWhere AND TRIM($sectionExpr) <> '' ORDER BY section_name", $scopeParams);
            $sectionNames = array_values(array_filter(array_map(static fn($r) => trim((string)($r['section_name'] ?? '')), $sections), static fn($v) => $v !== ''));

            $where = $scopeWhere;
            $params = $scopeParams;
            if ($q !== '') {
                $where .= " AND (e.name LIKE ? OR e.personnel_code LIKE ? OR e.national_code LIKE ? OR e.phone LIKE ? OR e.post LIKE ? OR $sectionExpr LIKE ?)";
                array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
            }
            if ($filter === 'active') { $where .= ' AND e.in_latest = 1 AND e.inactive = 0'; }
            if ($filter === 'inactive') { $where .= ' AND (e.in_latest = 0 OR e.inactive = 1)'; }
            if ($filter === 'incomplete') { $where .= ' AND e.personnel_ok = 0'; }
            if ($filter === 'zero') { $where .= " AND e.national_code = '" . ZERO_NATIONAL . "'"; }
            if ($filter === 'nomanager') { $where .= " AND e.in_latest = 1 AND e.inactive = 0 AND (e.manager_pc = '' OR e.manager_pc IS NULL)"; }
            if ($selectedSection === '__none__') {
                $where .= " AND TRIM($sectionExpr) = ''";
            } elseif ($selectedSection !== '') {
                $where .= " AND TRIM($sectionExpr) = ?";
                $params[] = $selectedSection;
            }
            $rows = all("SELECT e.id, e.name, e.personnel_code, e.national_code, e.phone, e.post, $sectionExpr AS section_name,
                e.start_code, e.color, e.inactive, e.in_latest, e.manager_pc, e.personnel_ok,
                (SELECT m.name FROM employees m WHERE m.personnel_code = e.manager_pc
                    ORDER BY m.in_latest DESC, m.inactive ASC, m.id DESC LIMIT 1) AS manager_name,
                (e.personnel_code <> '' AND EXISTS (SELECT 1 FROM admins a WHERE a.personnel_code = e.personnel_code AND a.active = 1)) AS has_account
                FROM employees e LEFT JOIN employee_profiles ep ON ep.employee_id = e.id
                WHERE $where ORDER BY e.name LIMIT 500", $params);
            json_out(['ok' => true, 'employees' => $rows, 'sections' => $sectionNames,
                'scope' => $scopeIds === null ? 'all' : 'subordinates']);
        }

        case 'employee_history': {
            require_admin_json('admin');
            $id = (int)($in['employee_id'] ?? 0);
            $emp = one('SELECT id, name, personnel_code, national_code, phone, post, hire_date, inactive, in_latest
                       FROM employees WHERE id = ?', [$id]);
            if (!$emp) {
                json_out(['ok' => false, 'message' => 'کارمند پیدا نشد.']);
            }
            json_out(['ok' => true, 'employee' => $emp, 'records' => employee_history_list($id)]);
        }

        case 'employee_dossier': {
            require_admin_json('admin');
            $id = (int)($in['employee_id'] ?? 0);
            if ($id <= 0) json_out(['ok' => false, 'message' => 'شناسه‌ی کارمند معتبر نیست.'], 422);
            $dossier = employee_dossier_data($id);
            if (!$dossier) json_out(['ok' => false, 'message' => 'کارمند پیدا نشد.'], 404);
            json_out(['ok' => true, 'dossier' => $dossier]);
        }

        case 'employee_create': {
            $a = require_admin_json('admin');
            $created = employee_create_manual($in, (string)$a['name']);
            json_out(['ok' => true] + $created);
        }

        case 'employee_profile_save': {
            $a = require_admin_json('admin'); // اطلاعات هویتی، بانکی و حقوقی فقط با دسترسی کامل قابل ویرایش است.
            $id = (int)($in['employee_id'] ?? 0);
            if ($id <= 0) json_out(['ok' => false, 'message' => 'شناسه‌ی کارمند معتبر نیست.'], 422);
            employee_profiles_ensure(); // ایجاد/ارتقای جدول‌ها پیش از شروع تراکنش MySQL
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $result = employee_profile_save_manual($id,
                    is_array($in['fields'] ?? null) ? $in['fields'] : [],
                    is_array($in['use_excel'] ?? null) ? $in['use_excel'] : [],
                    (string)$a['name']);
                $pdo->commit();
            } catch (Throwable $saveError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $saveError;
            }
            $dossier = employee_dossier_data($id);
            json_out(['ok' => true, 'message' => $result['message'], 'dossier' => $dossier,
                'manual_overrides' => $result['manual_overrides'], 'removed_excel_locks' => $result['removed_excel_locks']]);
        }

        case 'employee_periods': {
            $a = require_admin_json();
            $id = (int)($in['employee_id'] ?? 0);
            require_employee_scope_access($a, $id);
            $emp = one('SELECT id, name, personnel_code FROM employees WHERE id = ?', [$id]);
            if (!$emp) {
                json_out(['ok' => false, 'message' => 'کارمند پیدا نشد.']);
            }
            json_out(['ok' => true, 'employee' => $emp, 'periods' => array_map(
                fn($p) => ['id' => (int)$p['id'], 'label' => period_label($p), 'has_work' => (bool)$p['has_work']],
                employee_periods($id))]);
        }

        case 'payslip': {
            $a = require_admin_json();
            $employeeId = (int)($in['employee_id'] ?? 0);
            require_employee_scope_access($a, $employeeId);
            $slip = payslip_for($employeeId, (int)($in['period_id'] ?? 0));
            if (!$slip) {
                json_out(['ok' => false, 'message' => 'فیش این دوره برای این کارمند ثبت نشده است.']);
            }
            json_out(['ok' => true, 'slip' => $slip]);
        }

        case 'set_manager': {
            require_admin_json('admin');
            json_out(manager_assign([(int)($in['id'] ?? 0)], (int)($in['supervisor_id'] ?? 0)));
        }

        case 'set_manager_bulk': {
            require_admin_json('admin');
            json_out(manager_assign((array)($in['ids'] ?? []), (int)($in['supervisor_id'] ?? 0)));
        }

        case 'windows': {
            require_admin_json('admin');
            $which = (string)($in['which'] ?? '');
            $prefix = $which === 'payslip' ? 'pay' : ($which === 'advance' ? 'adv' : ($which === 'loan' ? 'loan' : ''));
            if ($prefix === '') {
                json_out(['ok' => false, 'message' => 'بازه‌ی نامعتبر است.']);
            }
            if (!empty($in['clear'])) {
                set_setting("{$prefix}_start", null);
                set_setting("{$prefix}_end", null);
                json_out(['ok' => true, 'message' => 'بازه حذف شد.']);
            }
            $s = jalali_parse((string)($in['start'] ?? ''));
            $e = jalali_parse((string)($in['end'] ?? ''));
            if (!$s || !$e) {
                json_out(['ok' => false, 'message' => 'تاریخ‌ها را به شکل شمسی مثل 1405/07/20 وارد کنید.']);
            }
            if (jalali_num($s) > jalali_num($e)) {
                json_out(['ok' => false, 'message' => 'تاریخ شروع نباید بعد از پایان باشد.']);
            }
            set_setting("{$prefix}_start", jalali_str($s));
            set_setting("{$prefix}_end", jalali_str($e));
            json_out(['ok' => true, 'message' => 'بازه ذخیره شد.']);
        }

        case 'advances': {
            $a = require_admin_json();
            json_out(['ok' => true, 'requests' => advance_admin_list((array)($in['filter'] ?? []), $a)]);
        }

        case 'advance_decide': {
            $a = require_admin_json();
            $approved = isset($in['approved']) && $in['approved'] !== '' ? (int)digits((string)$in['approved']) : null;
            json_out(advance_decide($a, (int)$in['id'], (string)$in['decision'], $approved,
                (string)($in['note'] ?? ''), $a['name']));
        }

        case 'advance_delete': {
            require_admin_json('admin');
            $id = (int)($in['id'] ?? 0);
            if (!one('SELECT id FROM advance_requests WHERE id = ?', [$id])) {
                json_out(['ok' => false, 'message' => 'درخواست پیدا نشد.']);
            }
            q('DELETE FROM advance_requests WHERE id = ?', [$id]);
            json_out(['ok' => true, 'message' => 'درخواست مساعده حذف شد.']);
        }

        case 'rules_save': {
            require_admin_json('admin');
            json_out(fin_rules_save($in));
        }

        case 'promotions': {
            $a = require_admin_json();
            json_out(['ok' => true, 'requests' => promo_admin_list($a, (array)($in['filter'] ?? []))]);
        }

        case 'promo_decide': {
            $a = require_admin_json();
            json_out(promo_decide($a, (int)($in['id'] ?? 0), (string)($in['decision'] ?? ''),
                (string)($in['note'] ?? ''), $a['name'], isset($in['score']) ? (string)$in['score'] : null,
                isset($in['amount']) ? (string)$in['amount'] : null));
        }

        case 'notif_poll': {
            $a = require_admin_json();
            json_out(['ok' => true, 'items' => notify_poll('admin:' . $a['id']), 'pending' => admin_pending_counts($a)]);
        }

        case 'notif_ack': {
            $a = require_admin_json();
            notify_ack('admin:' . $a['id'], (array)($in['ids'] ?? []));
            json_out(['ok' => true]);
        }

        case 'leaves': {
            $a = require_admin_json();
            json_out(['ok' => true, 'requests' => leave_manager_list($a, null, (array)($in['filter'] ?? []))]);
        }

        case 'leave_decide': {
            $a = require_admin_json();
            json_out(leave_decide($a, (int)$in['id'],
                (string)$in['decision'], (string)($in['note'] ?? ''), $a['name']));
        }

        case 'admins': {
            require_admin_json('admin');
            json_out(['ok' => true, 'admins' => all('SELECT id, username, name, role, personnel_code, active FROM admins ORDER BY id')]);
        }

        case 'admin_save': {
            require_admin_json('admin');
            $id = (int)($in['id'] ?? 0);
            $username = trim((string)($in['username'] ?? ''));
            $name = trim((string)($in['name'] ?? ''));
            $role = in_array($in['role'] ?? '', ['admin', 'senior'], true) ? $in['role'] : 'manager';
            $pc = digits((string)($in['personnel'] ?? ''));
            if ($pc !== '' && !one('SELECT id FROM employees WHERE personnel_code = ? LIMIT 1', [$pc])) {
                json_out(['ok' => false, 'message' => 'کد پرسنلی در فهرست کارمندان پیدا نشد. ابتدا کارمند را از فهرست انتخاب کنید.']);
            }
            $pass = (string)($in['password'] ?? '');
            if ($username === '' || $name === '') {
                json_out(['ok' => false, 'message' => 'نام کاربری و نام را وارد کنید.']);
            }
            if ($id) {
                q('UPDATE admins SET username = ?, name = ?, role = ?, personnel_code = ? WHERE id = ?',
                    [$username, $name, $role, $pc, $id]);
                if ($pass !== '') {
                    if (strlen($pass) < 8) {
                        json_out(['ok' => false, 'message' => 'رمز باید حداقل ۸ کاراکتر باشد.']);
                    }
                    q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
            } else {
                if (strlen($pass) < 8) {
                    json_out(['ok' => false, 'message' => 'رمز باید حداقل ۸ کاراکتر باشد.']);
                }
                q('INSERT INTO admins (username, password_hash, name, role, personnel_code, active, created_at)
                   VALUES (?,?,?,?,?,1,NOW())', [$username, password_hash($pass, PASSWORD_DEFAULT), $name, $role, $pc]);
            }
            json_out(['ok' => true, 'message' => 'ذخیره شد.']);
        }

        case 'admin_toggle': {
            $a = require_admin_json('admin');
            $id = (int)($in['id'] ?? 0);
            if ($id === $a['id']) {
                json_out(['ok' => false, 'message' => 'نمی‌توانید حساب خودتان را غیرفعال کنید.']);
            }
            q('UPDATE admins SET active = 1 - active WHERE id = ?', [$id]);
            json_out(['ok' => true, 'message' => 'وضعیت حساب تغییر کرد.']);
        }

        default:
            json_out(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 400);
    }
} catch (Throwable $ex) {
    error_log('[admin api] ' . $ex->getMessage());
    json_out(['ok' => false, 'message' => 'خطا: ' . $ex->getMessage()], 500);
}

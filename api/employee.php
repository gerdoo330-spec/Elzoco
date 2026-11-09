<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
}
require_csrf();
$in = request_data();
$action = (string)($in['action'] ?? '');

try {
    switch ($action) {
        case 'login': {
            $r = payroll_login((string)($in['national'] ?? ''), (string)($in['personnel'] ?? ''), (string)($in['phone'] ?? ''));
            if (!$r['ok']) {
                json_out($r + ['ok' => false], 200);
            }
            session_regenerate_id(true);
            $_SESSION['emp_id'] = (int)$r['employee']['id'];
            json_out(['ok' => true]);
        }

        case 'logout': {
            unset($_SESSION['emp_id']);
            json_out(['ok' => true]);
        }

        case 'me': {
            $emp = require_emp_json();
            $periods = employee_periods((int)$emp['id']);
            json_out([
                'ok' => true,
                'employee' => [
                    'name' => $emp['name'], 'national' => $emp['national_code'], 'personnel' => $emp['personnel_code'],
                    'post' => $emp['post'], 'hire_date' => hire_display($emp),
                ],
                'periods' => array_map(fn($p) => ['id' => (int)$p['id'], 'label' => period_label($p),
                    'has_work' => (bool)$p['has_work']], $periods),
                'payslip_window' => payslip_window(),
                'advance' => advance_overview($emp),
                'loan' => advance_overview($emp, 'loan'),
                'promotion' => promo_overview($emp),
                'leave' => ['types' => array_map(fn($k, $t) => ['key' => $k, 'label' => $t['label'], 'unit' => $t['unit']],
                    array_keys(LEAVE_TYPES), LEAVE_TYPES), 'requests' => leave_list_for((int)$emp['id']),
                    'balance' => leave_annual_balance($emp),
                    'max_hours' => (int)cfg('leave_max_hours_per_day', 8)],
            ]);
        }

        case 'payslip': {
            $emp = require_emp_json();
            $win = payslip_window();
            if (!$win['open']) {
                json_out(['ok' => false, 'message' => $win['message']]);
            }
            $periodId = (int)($in['period_id'] ?? 0);
            $slip = $periodId ? payslip_for((int)$emp['id'], $periodId) : null;
            if (!$slip) {
                json_out(['ok' => false, 'message' => 'فیش این دوره برای شما ثبت نشده است.']);
            }
            json_out(['ok' => true, 'slip' => $slip]);
        }

        case 'advance_submit': {
            $emp = require_emp_json();
            json_out(advance_submit($emp, $in, 'advance') + ['advance' => advance_overview($emp, 'advance')]);
        }

        case 'loan_submit': {
            $emp = require_emp_json();
            json_out(advance_submit($emp, $in, 'loan') + ['loan' => advance_overview($emp, 'loan')]);
        }

        case 'promo_submit': {
            $emp = require_emp_json();
            json_out(promo_submit($emp, $in) + ['promotion' => promo_overview($emp)]);
        }

        case 'leave_submit': {
            $emp = require_emp_json();
            $r = leave_submit($emp, $in);
            json_out($r + ['requests' => leave_list_for((int)$emp['id']), 'balance' => leave_annual_balance($emp)]);
        }

        case 'notif_poll': {
            $emp = require_emp_json();
            json_out(['ok' => true, 'items' => notify_poll('emp:' . $emp['id'])]);
        }

        case 'notif_history': {
            $emp = require_emp_json();
            $items = all(
                'SELECT id,kind,tone,title,body,tab,shown,created_at
                 FROM notifications
                 WHERE recipient = ? AND created_at >= (NOW() - INTERVAL 30 DAY)
                 ORDER BY id DESC LIMIT 100',
                ['emp:' . $emp['id']]
            );
            json_out(['ok' => true, 'items' => $items]);
        }

        case 'notif_ack': {
            $emp = require_emp_json();
            notify_ack('emp:' . $emp['id'], (array)($in['ids'] ?? []));
            json_out(['ok' => true]);
        }

        default:
            json_out(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 400);
    }
} catch (Throwable $ex) {
    error_log('[employee api] ' . $ex->getMessage());
    json_out(['ok' => false, 'message' => 'خطای داخلی سرور. دوباره تلاش کنید.'], 500);
}

function hire_display(array $emp): string
{
    $src = jalali_from_start_code((string)$emp['start_code']);
    return $src ? jalali_str($src) : '';
}

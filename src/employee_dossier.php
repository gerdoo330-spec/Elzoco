<?php
declare(strict_types=1);

/**
 * گردآوری نمای پرونده‌ی جامع پرسنلی از داده‌های موجود سامانه.
 * این تابع فقط اطلاعات همان employee_id را برمی‌گرداند و هیچ داده‌ای را تغییر نمی‌دهد.
 */
function employee_dossier_data(int $employeeId): ?array
{
    $emp = one('SELECT e.* FROM employees e WHERE e.id = ?', [$employeeId]);
    if (!$emp) {
        return null;
    }

    $manager = null;
    if (trim((string)$emp['manager_pc']) !== '') {
        $manager = one('SELECT name, personnel_code FROM employees
                        WHERE personnel_code = ? ORDER BY in_latest DESC, id DESC LIMIT 1',
            [(string)$emp['manager_pc']]);
    }

    $hireDate = trim((string)($emp['hire_date'] ?? ''));
    if ($hireDate === '') {
        $start = jalali_from_start_code((string)($emp['start_code'] ?? ''));
        if ($start) {
            $hireDate = jalali_str($start);
        }
    }
    $profile = employee_profile_get($employeeId);
    $extra = $profile['extra'] ?? [];
    $extraLabels = employee_profile_field_labels();
    $profileFields = [];
    foreach ($extra as $key => $value) {
        if (!isset($extraLabels[$key]) || trim((string)$value) === '') continue;
        $profileFields[] = ['key' => $key, 'label' => $extraLabels[$key], 'value' => (string)$value];
    }
    $leaveBalance = leave_annual_balance($emp);
    $importSnapshots = employee_import_snapshots_list($employeeId);

    $periodRows = all('SELECT p.id AS period_id, p.year, p.month, s.has_work, s.net, s.data
        FROM payslips s JOIN periods p ON p.id = s.period_id
        WHERE s.employee_id = ? ORDER BY p.year DESC, p.month DESC LIMIT 120', [$employeeId]);
    $periods = [];
    $latestBreakdown = null;
    foreach ($periodRows as $index => $row) {
        $data = json_decode((string)$row['data'], true) ?: [];
        $values = is_array($data['values'] ?? null) ? $data['values'] : [];
        $hasWork = (bool)$row['has_work'];
        $gross = $hasWork ? (float)($values['G19'] ?? 0) : 0.0;
        $deductions = $hasWork ? (float)($values['M19'] ?? 0) : 0.0;
        $net = $hasWork ? (float)($row['net'] ?? ($values['E20'] ?? 0)) : 0.0;
        $period = [
            'id' => (int)$row['period_id'],
            'label' => period_label($row),
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
            'has_work' => $hasWork,
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $net,
        ];
        $periods[] = $period;

        if ($index === 0 && $hasWork) {
            $pay = [];
            foreach (SLIP_PAY as $code => $label) {
                $pay[] = ['label' => $label, 'amount' => (float)($values[$code] ?? 0)];
            }
            $ded = [];
            foreach (SLIP_DED as $code => $label) {
                $ded[] = ['label' => $label, 'amount' => (float)($values[$code] ?? 0)];
            }
            $latestBreakdown = ['period' => $period['label'], 'pay' => $pay, 'deductions' => $ded];
        }
    }

    $advances = all('SELECT id, tracking, kind, jdate, amount, approved_amount, salary,
            window_start, window_end, status, stage, note, decided_by, decided_at,
            direct_decision, general_decision, to_admin_decision, created_at
        FROM advance_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 200', [$employeeId]);

    $leaves = all('SELECT id, tracking, type_key, type_label, from_date, to_date, start_time, end_time,
            amount, reason, stage, reject_reason, direct_decision, general_decision,
            to_admin_decision, created_jdate, created_at
        FROM leave_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 200', [$employeeId]);

    $promotions = all('SELECT id, tracking, post, job_requested, current_shift, shift, reason, stage,
            direct_decision, direct_note, direct_by, direct_at, senior_decision, senior_note,
            senior_by, senior_at, to_admin_decision, raise_amount, eval_score, eval_note,
            eval_by, eval_at, decided_by, decided_at, decision_note, created_jdate, created_at
        FROM promotion_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 200', [$employeeId]);

    return [
        'employee' => [
            'id' => (int)$emp['id'],
            'name' => (string)$emp['name'],
            'personnel_code' => (string)$emp['personnel_code'],
            'national_code' => (string)$emp['national_code'],
            'phone' => (string)$emp['phone'],
            'post' => (string)$emp['post'],
            'promo_post' => (string)$emp['promo_post'],
            'start_code' => (string)$emp['start_code'],
            'hire_date' => $hireDate,
            'personnel_ok' => (bool)$emp['personnel_ok'],
            'inactive' => (bool)$emp['inactive'],
            'in_latest' => (bool)$emp['in_latest'],
            'manager_pc' => (string)$emp['manager_pc'],
            'manager_name' => (string)($manager['name'] ?? ''),
        ],
        'profile_extra' => $extra,
        'profile_fields' => $profileFields,
        'payroll_base' => $profile['payroll_base'] ?? [],
        'manual_overrides' => $profile['manual'] ?? [],
        'manual_updated_by' => $profile['manual_updated_by'] ?? '',
        'manual_updated_at' => $profile['manual_updated_at'] ?? null,
        'profile_edit_history' => employee_profile_edit_history_list($employeeId),
        'bank' => [
            'sheba' => (string)$emp['sheba'],
            'bank_name' => (string)($extra['bank_name'] ?? ''),
            'bank_account' => (string)($extra['bank_account'] ?? ''),
            'bank_card' => (string)($extra['bank_card'] ?? ''),
        ],
        'payroll' => [
            'current_salary' => (int)$emp['salary'],
            'promotion_increase_total' => (int)$emp['promo_salary_add'],
            'current_post' => (string)$emp['post'],
            'promotion_post' => (string)$emp['promo_post'],
            'latest_breakdown' => $latestBreakdown,
        ],
        'payslips' => $periods,
        'advances' => $advances,
        'leaves' => $leaves,
        'leave_balance' => $leaveBalance,
        'promotions' => $promotions,
        'history' => employee_history_list($employeeId),
        'import_snapshots' => $importSnapshots,
        'limits' => ['payslips' => 120, 'requests_per_type' => 200, 'import_snapshots' => 120],
    ];
}

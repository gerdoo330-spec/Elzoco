<?php
declare(strict_types=1);

require __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/jalali.php';
require_once __DIR__ . '/../src/employee_profile.php';

$rows = [
    ['id' => 101, 'personnel_code' => 'EMP-101', 'manager_pc' => 'MGR-001'],
    ['id' => 102, 'personnel_code' => 'EMP-102', 'manager_pc' => 'MGR-001'],
    ['id' => 201, 'personnel_code' => 'EMP-201', 'manager_pc' => 'EMP-101'],
    ['id' => 202, 'personnel_code' => 'EMP-202', 'manager_pc' => 'EMP-102'],
    ['id' => 301, 'personnel_code' => 'EMP-301', 'manager_pc' => 'MGR-OTHER'],
    ['id' => 0, 'personnel_code' => 'EMP-000', 'manager_pc' => 'MGR-001'],
];

$actual = employee_scope_collect_ids(' MGR-001 ', $rows);
sort($actual);
$expected = [101, 102];
if ($actual !== $expected) {
    fwrite(STDERR, 'Expected direct reports [101, 102], got [' . implode(', ', $actual) . ']' . PHP_EOL);
    exit(1);
}

if (employee_scope_collect_ids('', $rows) !== []) {
    fwrite(STDERR, 'An empty manager personnel code must return an empty scope.' . PHP_EOL);
    exit(1);
}

echo "Direct-report scope tests passed (2 assertions)." . PHP_EOL;

$directRoles = ['manager', 'supervisor', 'supervisor_manager'];
foreach ($directRoles as $role) {
    if (!admin_role_has_direct_scope($role)) {
        fwrite(STDERR, 'Expected ' . $role . ' to use direct-report scope.' . PHP_EOL);
        exit(1);
    }
}
foreach (['admin', 'senior', 'employee', 'unknown'] as $role) {
    if (admin_role_has_direct_scope($role)) {
        fwrite(STDERR, 'Unexpected direct-report role: ' . $role . PHP_EOL);
        exit(1);
    }
}

$supervisor = ['role' => 'supervisor', 'pc' => 'MGR-001'];
if (!approval_can_see($supervisor, 'direct', 'MGR-001')
    || !approval_can_decide($supervisor, 'direct', 'MGR-001')
    || approval_can_see($supervisor, 'direct', 'MGR-OTHER')) {
    fwrite(STDERR, 'Supervisor direct-approval scope is incorrect.' . PHP_EOL);
    exit(1);
}

$hire = [1405, 7, 1];
$shortCode = employee_manual_personnel_code($hire, '', '7');
if ($shortCode['start_code'] !== '050701' || $shortCode['personnel_code'] !== '050701007') {
    fwrite(STDERR, 'Default N+F personnel code is incorrect.' . PHP_EOL);
    exit(1);
}
$longCode = employee_manual_personnel_code($hire, '۱۴۰۵۰۷۰۱', '۰۰۷');
if ($longCode['start_code'] !== '14050701' || $longCode['personnel_code'] !== '14050701007') {
    fwrite(STDERR, 'Eight-digit N+F personnel code is incorrect.' . PHP_EOL);
    exit(1);
}
$mismatchRejected = false;
try {
    employee_manual_personnel_code($hire, '14050702', '007');
} catch (RuntimeException $error) {
    $mismatchRejected = true;
}
if (!$mismatchRejected) {
    fwrite(STDERR, 'A personnel N component that differs from the hire date must be rejected.' . PHP_EOL);
    exit(1);
}

echo "Supervisor scope and manual N+F personnel-code tests passed." . PHP_EOL;

<?php
declare(strict_types=1);

require __DIR__ . '/../src/auth.php';

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

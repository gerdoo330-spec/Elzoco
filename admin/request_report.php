<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/xlsx_export.php';
start_session_secure();
$admin = require_admin_page();

$type = (string)($_GET['type'] ?? 'leave');
$mode = (string)($_GET['mode'] ?? 'print');
if (!in_array($type, ['leave', 'advance', 'promotion'], true) || !in_array($mode, ['print', 'excel', 'form'], true)) {
    http_response_code(400);
    exit('درخواست گزارش معتبر نیست.');
}

function report_clean_date(string $value): string
{
    $value = trim(fa_digits_to_en($value));
    $parsed = $value !== '' ? jalali_parse($value) : null;
    return $parsed ? jalali_str($parsed) : '';
}

function report_stage_label(string $stage): string
{
    return [
        'direct' => 'نزد مدیر مستقیم', 'general' => 'نزد مدیر ارشد', 'admin' => 'نزد منابع انسانی',
        'evaluated' => 'ارزیابی شده؛ منتظر مدیر', 'final' => 'تأیید نهایی', 'rejected' => 'رد نهایی',
    ][$stage] ?? ($stage !== '' ? $stage : '—');
}

function report_value($value): string
{
    if ($value === null || $value === '') return '—';
    return (string)$value;
}

/** برچسب فارسی تصمیم‌ها در خروجی‌های مرخصی؛ مقدارهای انگلیسی فقط در دیتابیس/API باقی می‌مانند. */
function leave_decision_label($value, bool $isFinal = false): string
{
    $decision = strtolower(trim((string)$value));
    if ($decision === 'approve') return $isFinal ? 'تأیید نهایی' : 'تأیید شد';
    if ($decision === 'reject') return $isFinal ? 'رد نهایی' : 'رد شد';
    if ($decision === 'notified') return 'منابع انسانی از تأیید نهایی مطلع شد';
    return report_value($value);
}

function report_admin_result($value): string
{
    $decision = strtolower(trim((string)$value));
    if ($decision === 'approve') return 'تأیید نهایی منابع انسانی';
    if ($decision === 'reject') return 'رد نهایی توسط منابع انسانی';
    if ($decision === 'notified') return 'اطلاع‌رسانی به منابع انسانی انجام شد';
    return report_value($value);
}

$filters = [
    'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80),
    'from' => report_clean_date((string)($_GET['from'] ?? '')),
    'to' => report_clean_date((string)($_GET['to'] ?? '')),
];
$requests = [];
$single = null;
$today = jalali_str(jalali_today());

if ($mode === 'form') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) {
        http_response_code(400);
        exit('شناسه‌ی درخواست معتبر نیست.');
    }
    if ($type === 'leave') {
        $single = one('SELECT leave_requests.*, (SELECT post FROM employees WHERE employees.id = leave_requests.employee_id) AS employee_post FROM leave_requests WHERE leave_requests.id = ?', [$id]);
        if (!$single || !approval_can_see($admin, (string)$single['stage'], (string)$single['direct_pc'])) $single = null;
    } elseif ($type === 'promotion') {
        $single = one('SELECT * FROM promotion_requests WHERE id = ?', [$id]);
        if (!$single || !promo_can_see($admin, $single)) $single = null;
    } else {
        $single = one('SELECT * FROM advance_requests WHERE id = ?', [$id]);
        if (!$single || !approval_can_see($admin, (string)$single['stage'], (string)$single['direct_pc'])) $single = null;
    }
    if (!$single) {
        http_response_code(404);
        exit('درخواست پیدا نشد یا دسترسی مشاهده‌ی آن را ندارید.');
    }
} else {
    // گزارش‌ها سقف بالاتری از فهرست تعاملی دارند تا خروجی مدیر ناقص نشود.
    $filters['report'] = true;
    if ($type === 'leave') {
        $filters['type'] = (string)($_GET['leave_type'] ?? '');
        $filters['stage'] = (string)($_GET['stage'] ?? '');
        $requests = leave_manager_list($admin, null, $filters);
    } elseif ($type === 'advance') {
        $filters['kind'] = (string)($_GET['kind'] ?? '');
        $filters['status'] = (string)($_GET['status'] ?? '');
        $requests = advance_admin_list($filters, $admin);
    } else {
        $filters['stage'] = (string)($_GET['stage'] ?? '');
        $requests = promo_admin_list($admin, $filters);
    }
}

$names = [
    'leave' => 'مرخصی‌ها', 'advance' => 'مساعده و وام', 'promotion' => 'ارتقای گروه شغلی',
];
$title = $names[$type];
$logoFile = dirname(__DIR__) . '/assets/logo-elzoco.png';
$logo = is_file($logoFile) ? 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoFile)) : '';

if ($mode === 'excel') {
    $headers = [];
    $rows = [];
    if ($type === 'leave') {
        $headers = ['ردیف', 'کد پیگیری', 'تاریخ ثبت', 'نام کارمند', 'سمت', 'کد پرسنلی', 'کد ملی', 'نوع مرخصی', 'از تاریخ', 'تا تاریخ', 'ساعت شروع', 'ساعت پایان', 'مدت', 'واحد', 'دلیل درخواست', 'مرحله فعلی', 'تصمیم مدیر مستقیم', 'تصمیم مدیر ارشد', 'وضعیت منابع انسانی', 'دلیل رد'];
        foreach ($requests as $i => $r) $rows[] = [$i + 1, $r['tracking'], $r['created_jdate'], $r['name'], $r['employee_post'] ?? '', $r['personnel_code'], $r['national_code'], $r['type_label'], $r['from_date'], $r['to_date'], $r['start_time'], $r['end_time'], (float)$r['amount'], $r['start_time'] !== '' ? 'ساعت' : 'روز', $r['reason'], report_stage_label((string)$r['stage']), leave_decision_label($r['direct_decision']), leave_decision_label($r['general_decision']), report_admin_result($r['to_admin_decision']), $r['reject_reason']];
    } elseif ($type === 'advance') {
        $headers = ['ردیف', 'کد پیگیری', 'تاریخ ثبت', 'نوع درخواست', 'نام کارمند', 'کد پرسنلی', 'کد ملی', 'شماره شبا', 'حقوق مبنا (ریال)', 'مبلغ درخواستی (ریال)', 'مبلغ تأییدشده (ریال)', 'وضعیت', 'مرحله فعلی', 'ملاحظات', 'تصمیم‌گیرنده نهایی', 'تصمیم مدیر مستقیم', 'تصمیم مدیر ارشد', 'نتیجه منابع انسانی', 'شروع بازه', 'پایان بازه'];
        foreach ($requests as $i => $r) $rows[] = [$i + 1, $r['tracking'], $r['jdate'], fin_key((string)$r['kind']) === 'loan' ? 'وام' : 'مساعده', $r['name'], $r['personnel_code'], $r['national_code'], $r['sheba'], (int)$r['salary'], (int)$r['amount'], $r['approved_amount'] === null ? '' : (int)$r['approved_amount'], $r['status'], report_stage_label((string)$r['stage']), $r['note'], $r['decided_by'], $r['direct_decision'], $r['general_decision'], report_admin_result($r['to_admin_decision']), $r['window_start'], $r['window_end']];
    } else {
        $headers = ['ردیف', 'کد پیگیری', 'تاریخ ثبت', 'نام کارمند', 'کد پرسنلی', 'کد ملی', 'شغل فعلی', 'شغل درخواستی', 'شیفت فعلی', 'شیفت درخواستی', 'دلایل درخواست', 'مرحله فعلی', 'سطح بازبینی', 'تصمیم مدیر مستقیم', 'توضیح مدیر مستقیم', 'تصمیم مدیر ارشد', 'توضیح مدیر ارشد', 'نمره ارزیابی', 'مبلغ افزایش حقوق (ریال)', 'نتیجه نهایی', 'توضیح نهایی', 'وضعیت منابع انسانی'];
        foreach ($requests as $i => $r) $rows[] = [$i + 1, $r['tracking'], $r['created_jdate'], $r['name'], $r['personnel_code'], $r['national_code'], $r['post'], $r['job_requested'], $r['current_shift'], $r['shift'], $r['reason'], report_stage_label((string)$r['stage']), $r['review_level'], $r['direct_decision'], $r['direct_note'], $r['senior_decision'], $r['senior_note'], $r['eval_score'] === null ? '' : (float)$r['eval_score'], $r['raise_amount'] === null ? '' : (int)$r['raise_amount'], $r['decided_by'], $r['decision_note'], report_admin_result($r['to_admin_decision'])];
    }
    $file = $type . '-requests-' . str_replace('/', '-', $today) . '.xlsx';
    xlsx_export_download($file, $title, $headers, $rows);
}

$filterText = [];
if ($filters['q'] !== '') $filterText[] = 'جستجو: ' . $filters['q'];
if ($filters['from'] !== '') $filterText[] = 'از: ' . $filters['from'];
if ($filters['to'] !== '') $filterText[] = 'تا: ' . $filters['to'];
if (!empty($filters['type'])) $filterText[] = 'نوع مرخصی: ' . (LEAVE_TYPES[$filters['type']]['label'] ?? $filters['type']);
if (!empty($filters['kind'])) $filterText[] = 'نوع: ' . ($type === 'advance' ? (FIN_KINDS[$filters['kind']]['label'] ?? 'همه') : $filters['kind']);
if (!empty($filters['status'])) $filterText[] = 'وضعیت: ' . $filters['status'];
if (!empty($filters['stage'])) $filterText[] = 'مرحله: ' . report_stage_label($filters['stage']);
$filterLabel = $filterText ? implode(' | ', $filterText) : 'بدون محدودیت اضافی';

if ($mode === 'print' || $mode === 'form') {
    $isForm = $mode === 'form';
    $row = $single;
    $formName = $type === 'leave' ? 'فرم درخواست مرخصی' : ($type === 'promotion' ? 'فرم درخواست ارتقای گروه شغلی' : 'فرم درخواست مساعده / وام');
    $stage = $row ? report_stage_label((string)$row['stage']) : '';
    $num = static fn($v) => ($v === null || $v === '') ? '—' : number_format((float)$v, ($v !== null && is_numeric($v) && floor((float)$v) !== (float)$v) ? 2 : 0);
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($isForm ? $formName : 'گزارش ' . $title) ?> | <?= e(cfg('app_name')) ?></title>
<link rel="stylesheet" href="../assets/fonts/fonts.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#152238;font-family:Vazirmatn,Tahoma,sans-serif;font-size:10pt}.toolbar{max-width:297mm;margin:14px auto;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}.toolbar button,.toolbar a{font:inherit;border:0;border-radius:9px;padding:9px 16px;background:#f97316;color:#fff;text-decoration:none;cursor:pointer}.toolbar a{background:#17385f}.sheet{max-width:297mm;margin:0 auto 24px;background:#fff;padding:10mm;border:1px solid #d5deea;box-shadow:0 16px 40px -24px #17385f55}.sheet.portrait{max-width:190mm;page:portraitPage}.report-head{display:grid;grid-template-columns:38mm minmax(0,1fr) 55mm;align-items:stretch;border:1.5px solid #24364d}.report-logo{display:flex;align-items:center;justify-content:center;padding:7px;border-left:1px solid #24364d}.report-logo img{max-width:100%;max-height:22mm}.report-title{display:grid;align-content:center;justify-items:center;text-align:center;padding:8px}.report-title h1{font-size:15pt;margin:0 0 5px}.report-meta{border-right:1px solid #24364d;padding:8px;display:grid;align-content:center;gap:5px;font-size:9pt}.report-meta b{direction:ltr;display:inline-block}.filterline{margin:8px 0 12px;padding:8px 10px;background:#f6f8fb;border:1px solid #dce4ef;border-radius:7px;font-size:9pt}.summary{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0}.summary span{padding:5px 9px;border-radius:7px;background:#fff2e8;color:#9a4310;border:1px solid #fed7aa;font-weight:700}table{width:100%;border-collapse:collapse;table-layout:auto}th,td{border:1px solid #8793a4;padding:5px 6px;vertical-align:top;font-size:8.2pt;line-height:1.6;overflow-wrap:anywhere}th{background:#e9eff6;color:#172b45;text-align:center;font-weight:800}td.center{text-align:center}td.ltr{direction:ltr;text-align:center;white-space:nowrap;font-variant-numeric:tabular-nums}.empty{text-align:center;padding:22px}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}.form-section{margin-top:14px}.form-section h2{margin:0 0 0;padding:7px 10px;background:#edf2f8;border:1px solid #718096;border-bottom:0;font-size:10.5pt}.detail-table th{width:21%;text-align:right}.detail-table td{font-size:9.5pt;min-height:28px}.reason{min-height:28mm;white-space:pre-wrap}.signatures{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}.signature{min-height:30mm;border:1px solid #7b8797;padding:9px;text-align:center;display:flex;flex-direction:column;justify-content:space-between}.signature b{font-size:9pt}.signature span{border-top:1px dashed #8793a4;padding-top:5px;color:#596579}.note{font-size:8.5pt;color:#55657a;margin-top:10px}.ltr{direction:ltr;unicode-bidi:embed}@page{size:A4 landscape;margin:9mm}@page portraitPage{size:A4 portrait;margin:12mm}@media print{body{background:#fff}.toolbar{display:none}.sheet,.sheet.portrait{max-width:none;margin:0;padding:0;border:0;box-shadow:none}.sheet.portrait{page-break-after:always}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
@media(max-width:700px){.sheet{margin:0;padding:10px;overflow:auto}.report-head{grid-template-columns:26mm minmax(0,1fr) 42mm}.report-title h1{font-size:12pt}.toolbar{padding:0 10px}}
</style>
<link rel="stylesheet" href="../assets/experience-ui.css?v=1">
</head>
<body class="print-document">
<div class="toolbar"><button type="button" onclick="window.print()">چاپ / ذخیره PDF</button><a href="panel.php">بازگشت به پنل مدیریت</a></div>
<div class="sheet <?= $isForm ? 'portrait' : '' ?>">
  <header class="report-head">
    <div class="report-logo"><?php if ($logo): ?><img src="<?= $logo ?>" alt="EL ZOCO"><?php else: ?><b>EL ZOCO</b><?php endif; ?></div>
    <div class="report-title"><h1><?= e($isForm ? $formName : 'گزارش ' . $title) ?></h1><div>سامانه مدیریت منابع انسانی الزوکو</div></div>
    <div class="report-meta"><div>تاریخ تهیه: <b><?= e($today) ?></b></div><div><?= $isForm ? 'کد پیگیری: ' : 'تعداد رکورد: ' ?><b><?= e($isForm ? (string)$row['tracking'] : (string)count($requests)) ?></b></div><?php if ($isForm): ?><div>مرحله: <strong><?= e($stage) ?></strong></div><?php endif; ?></div>
  </header>
  <?php if (!$isForm): ?>
    <div class="filterline">فیلتر گزارش: <?= e($filterLabel) ?></div>
    <div class="summary"><span>تعداد درخواست‌ها: <?= number_format(count($requests)) ?></span><?php if ($type === 'leave'): ?><span>ساعتی: <?= number_format(count(array_filter($requests, fn($r) => $r['type_key'] === 'hourly'))) ?></span><span>روزانه: <?= number_format(count(array_filter($requests, fn($r) => $r['type_key'] !== 'hourly'))) ?></span><?php endif; ?></div>
    <table>
    <?php if ($type === 'leave'): ?>
      <thead><tr><th>ردیف</th><th>کد پیگیری</th><th>تاریخ ثبت</th><th>نام و کد پرسنلی</th><th>نوع</th><th>از</th><th>تا</th><th>مدت</th><th>دلیل درخواست</th><th>مرحله</th><th>تصمیم مدیران</th><th>امضا</th></tr></thead><tbody>
      <?php foreach ($requests as $i => $r): ?><tr><td class="center"><?= $i + 1 ?></td><td class="ltr"><?= e($r['tracking']) ?></td><td class="center"><?= e($r['created_jdate']) ?></td><td><?= e($r['name']) ?><br><span class="ltr"><?= e($r['personnel_code']) ?></span></td><td class="center"><?= e($r['type_label']) ?></td><td class="center ltr"><?= e($r['from_date']) ?> <?= e($r['start_time']) ?></td><td class="center ltr"><?= e($r['to_date']) ?> <?= e($r['end_time']) ?></td><td class="center"><?= e((string)$r['amount']) ?> <?= $r['start_time'] !== '' ? 'ساعت' : 'روز' ?></td><td><?= e($r['reason']) ?></td><td class="center"><?= e(report_stage_label((string)$r['stage'])) ?></td><td><?= e(leave_decision_label($r['direct_decision'])) ?><br><?= e(leave_decision_label($r['general_decision'])) ?><?php if ($r['reject_reason']): ?><br>رد: <?= e($r['reject_reason']) ?><?php endif; ?></td><td></td></tr><?php endforeach; ?>
      <?php if (!$requests): ?><tr><td colspan="12" class="empty">درخواستی برای نمایش وجود ندارد.</td></tr><?php endif; ?></tbody>
    <?php elseif ($type === 'advance'): ?>
      <thead><tr><th>ردیف</th><th>پیگیری</th><th>تاریخ</th><th>نوع</th><th>نام</th><th>کد پرسنلی</th><th>شبا</th><th>درخواستی</th><th>تأییدی</th><th>وضعیت</th><th>مرحله</th><th>امضا</th></tr></thead><tbody>
      <?php foreach ($requests as $i => $r): ?><tr><td class="center"><?= $i + 1 ?></td><td class="ltr"><?= e($r['tracking']) ?></td><td class="center"><?= e($r['jdate']) ?></td><td class="center"><?= e(FIN_KINDS[fin_key((string)$r['kind'])]['label']) ?></td><td><?= e($r['name']) ?></td><td class="ltr"><?= e($r['personnel_code']) ?></td><td class="ltr"><?= e($r['sheba']) ?></td><td class="ltr"><?= $num($r['amount']) ?></td><td class="ltr"><?= $num($r['approved_amount']) ?></td><td class="center"><?= e($r['status']) ?></td><td class="center"><?= e(report_stage_label((string)$r['stage'])) ?></td><td></td></tr><?php endforeach; ?>
      <?php if (!$requests): ?><tr><td colspan="12" class="empty">درخواستی برای نمایش وجود ندارد.</td></tr><?php endif; ?></tbody>
    <?php else: ?>
      <thead><tr><th>ردیف</th><th>پیگیری</th><th>تاریخ ثبت</th><th>نام / کد</th><th>شغل فعلی</th><th>شغل درخواستی</th><th>شیفت درخواستی</th><th>مرحله</th><th>ارزیابی</th><th>افزایش حقوق</th><th>خلاصه دلیل</th><th>امضا</th></tr></thead><tbody>
      <?php foreach ($requests as $i => $r): ?><tr><td class="center"><?= $i + 1 ?></td><td class="ltr"><?= e($r['tracking']) ?></td><td class="center"><?= e($r['created_jdate']) ?></td><td><?= e($r['name']) ?><br><span class="ltr"><?= e($r['personnel_code']) ?></span></td><td><?= e($r['post']) ?></td><td><?= e($r['job_requested']) ?></td><td><?= e($r['shift']) ?></td><td class="center"><?= e(report_stage_label((string)$r['stage'])) ?></td><td class="center"><?= e((string)($r['eval_score'] ?? '—')) ?></td><td class="ltr"><?= $num($r['raise_amount']) ?></td><td><?= e(mb_substr((string)$r['reason'], 0, 180)) ?></td><td></td></tr><?php endforeach; ?>
      <?php if (!$requests): ?><tr><td colspan="12" class="empty">درخواستی برای نمایش وجود ندارد.</td></tr><?php endif; ?></tbody>
    <?php endif; ?>
    </table>
    <p class="note">این گزارش بر اساس درخواست‌هایی تهیه شده که حساب مدیریتی فعلی مجاز به مشاهده‌ی آن‌هاست. برای مشاهده‌ی جزئیات کامل هر درخواست، فرم چاپی همان درخواست را از پنل باز کنید.</p>
  <?php else: ?>
    <?php if ($type === 'leave'): ?>
      <section class="form-section"><h2>مشخصات کارمند و درخواست</h2><table class="detail-table"><tr><th>نام و نام خانوادگی</th><td><?= e($row['name']) ?></td><th>سمت</th><td><?= e($row['employee_post'] ?? '—') ?></td></tr><tr><th>کد پرسنلی</th><td class="ltr"><?= e($row['personnel_code']) ?></td><th>کد ملی</th><td class="ltr"><?= e($row['national_code']) ?></td></tr><tr><th>تاریخ ثبت</th><td><?= e($row['created_jdate']) ?></td><th>نوع مرخصی</th><td><?= e($row['type_label']) ?></td></tr><tr><th>مدت</th><td><?= e((string)$row['amount']) ?> <?= $row['start_time'] !== '' ? 'ساعت' : 'روز' ?></td><th>کد پیگیری</th><td class="ltr"><?= e($row['tracking']) ?></td></tr><tr><th>تاریخ شروع</th><td><?= e($row['from_date']) ?> <?= e($row['start_time']) ?></td><th>تاریخ پایان</th><td><?= e($row['to_date']) ?> <?= e($row['end_time']) ?></td></tr><tr><th>مرحله فعلی</th><td colspan="3"><?= e($stage) ?></td></tr></table></section>
      <section class="form-section"><h2>دلیل و توضیحات کارمند</h2><table class="detail-table"><tr><td colspan="4" class="reason"><?= nl2br(e($row['reason'])) ?></td></tr></table></section>
      <section class="form-section"><h2>سوابق بررسی و تصمیم</h2><table class="detail-table"><tr><th>تصمیم مدیر مستقیم</th><td colspan="3"><?= nl2br(e(leave_decision_label($row['direct_decision']))) ?></td></tr><tr><th>تصمیم مدیر ارشد / نتیجه نهایی</th><td colspan="3"><?= nl2br(e(leave_decision_label($row['general_decision'], (string)$row['stage'] === 'final'))) ?></td></tr><tr><th>وضعیت منابع انسانی</th><td colspan="3"><?= e(report_admin_result($row['to_admin_decision'])) ?></td></tr><tr><th>دلیل رد</th><td colspan="3"><?= nl2br(e(report_value($row['reject_reason']))) ?></td></tr></table></section>
    <?php elseif ($type === 'promotion'): ?>
      <section class="form-section"><h2>مشخصات کارمند و درخواست ارتقا</h2><table class="detail-table"><tr><th>نام و نام خانوادگی</th><td><?= e($row['name']) ?></td><th>کد پرسنلی</th><td class="ltr"><?= e($row['personnel_code']) ?></td></tr><tr><th>کد ملی</th><td class="ltr"><?= e($row['national_code']) ?></td><th>تاریخ ثبت</th><td><?= e($row['created_jdate']) ?></td></tr><tr><th>کد پیگیری</th><td class="ltr"><?= e($row['tracking']) ?></td><th>مرحله فعلی</th><td><?= e($stage) ?></td></tr><tr><th>شغل فعلی</th><td><?= e($row['post']) ?></td><th>شغل درخواستی</th><td><?= e($row['job_requested']) ?></td></tr><tr><th>شیفت فعلی</th><td><?= e($row['current_shift']) ?></td><th>شیفت درخواستی</th><td><?= e($row['shift']) ?></td></tr></table></section>
      <section class="form-section"><h2>دلایل و مستندات متنی کارمند</h2><table class="detail-table"><tr><td colspan="4" class="reason"><?= nl2br(e($row['reason'])) ?></td></tr></table></section>
      <section class="form-section"><h2>سوابق ارزیابی و تصمیم</h2><table class="detail-table"><tr><th>مدیر مستقیم</th><td><?= e(report_value($row['direct_by'])) ?></td><th>تصمیم</th><td><?= e(report_value($row['direct_decision'])) ?> — <?= e(report_value($row['direct_note'])) ?></td></tr><tr><th>مدیر ارشد</th><td><?= e(report_value($row['senior_by'])) ?></td><th>تصمیم</th><td><?= e(report_value($row['senior_decision'])) ?> — <?= e(report_value($row['senior_note'])) ?></td></tr><tr><th>نمره ارزیابی</th><td><?= e(report_value($row['eval_score'])) ?>/100</td><th>مبلغ افزایش حقوق</th><td><?= $num($row['raise_amount']) ?> ریال</td></tr><tr><th>نتیجه نهایی</th><td><?= e(report_value($row['decided_by'])) ?></td><th>توضیح نهایی</th><td><?= e(report_value($row['decision_note'])) ?></td></tr></table></section>
    <?php else: ?>
      <section class="form-section"><h2>مشخصات کارمند و درخواست مالی</h2><table class="detail-table"><tr><th>نام و نام خانوادگی</th><td><?= e($row['name']) ?></td><th>کد پرسنلی</th><td class="ltr"><?= e($row['personnel_code']) ?></td></tr><tr><th>کد ملی</th><td class="ltr"><?= e($row['national_code']) ?></td><th>تاریخ ثبت</th><td><?= e($row['jdate']) ?></td></tr><tr><th>نوع درخواست</th><td><?= e(FIN_KINDS[fin_key((string)$row['kind'])]['label']) ?></td><th>کد پیگیری</th><td class="ltr"><?= e($row['tracking']) ?></td></tr><tr><th>شماره شبا</th><td class="ltr"><?= e($row['sheba']) ?></td><th>حقوق مبنا</th><td><?= $num($row['salary']) ?> ریال</td></tr><tr><th>مبلغ درخواستی</th><td><?= $num($row['amount']) ?> ریال</td><th>مبلغ تأییدشده</th><td><?= $num($row['approved_amount']) ?> ریال</td></tr><tr><th>وضعیت</th><td><?= e($row['status']) ?></td><th>مرحله</th><td><?= e($stage) ?></td></tr></table></section>
      <section class="form-section"><h2>ملاحظات درخواست</h2><table class="detail-table"><tr><td class="reason" colspan="4"><?= nl2br(e(report_value($row['note']))) ?></td></tr></table></section><section class="form-section"><h2>سوابق بررسی و تصمیم</h2><table class="detail-table"><tr><th>تصمیم مدیر مستقیم</th><td colspan="3"><?= e(report_value($row['direct_decision'])) ?></td></tr><tr><th>تصمیم مدیر ارشد</th><td colspan="3"><?= e(report_value($row['general_decision'])) ?></td></tr><tr><th>وضعیت منابع انسانی</th><td colspan="3"><?= e(report_admin_result($row['to_admin_decision'])) ?></td></tr><tr><th>تصمیم‌گیرنده نهایی</th><td><?= e(report_value($row['decided_by'])) ?></td><th>تاریخ تصمیم</th><td><?= e(report_value($row['decided_at'])) ?></td></tr></table></section>
    <?php endif; ?>
    <div class="signatures"><div class="signature"><b>امضای مدیر مستقیم</b><span>نام، تاریخ و امضا</span></div><div class="signature"><b>امضای مدیر ارشد</b><span>نام، تاریخ و امضا</span></div><div class="signature"><b><?= (string)($row['to_admin_decision'] ?? '') === 'notified' ? 'اطلاع منابع انسانی' : 'ثبت نهایی منابع انسانی' ?></b><span>نام، تاریخ و امضا</span></div></div>
  <?php endif; ?>
</div>
</body></html>

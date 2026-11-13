<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
$admin = require_admin_page();

const ADV_STATUSES = ['', 'در انتظار بررسی', 'تایید شده', 'رد شده'];

function advance_print_date(string $value): string
{
    $parsed = jalali_parse(trim(fa_digits_to_en($value)));
    return $parsed ? jalali_str($parsed) : '';
}
$status = (string)($_GET['status'] ?? '');
if (!in_array($status, ADV_STATUSES, true)) {
    $status = '';
}
$kind = (string)($_GET['kind'] ?? '');
if ($kind !== '' && !isset(FIN_KINDS[$kind])) {
    $kind = '';
}

$filter = [
    'status' => $status,
    'kind' => $kind,
    'q' => trim((string)($_GET['q'] ?? '')),
    'from' => advance_print_date((string)($_GET['from'] ?? '')),
    'to' => advance_print_date((string)($_GET['to'] ?? '')),
];
$rows = advance_admin_list($filter, $admin);

$sumAmount = 0;
$sumApproved = 0;
foreach ($rows as $r) {
    $sumAmount += (int)$r['amount'];
    $sumApproved += (int)($r['approved_amount'] ?? 0);
}
$num = fn($v) => ($v === null || $v === '') ? '—' : number_format((int)$v);
$today = jalali_today();
$todayStr = jalali_str($today);
$label = $status === '' ? 'همه‌ی وضعیت‌ها' : $status;
$kindTitle = $kind === '' ? 'مساعده و وام' : FIN_KINDS[$kind]['label'];

$logoFile = dirname(__DIR__) . '/assets/logo-elzoco.png';
$logo = is_file($logoFile) ? 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoFile)) : '';

if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="list-' . ($kind ?: 'all') . '.html"');
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/fonts/fonts.css">
<title>لیست درخواست‌های مساعده</title>
<style>
  @page{size:A4 landscape;margin:10mm 9mm 14mm}
  *{box-sizing:border-box}
  body{margin:0;background:#eef1f5;color:#000;font-family:"Vazirmatn",Tahoma,sans-serif;font-size:9.5pt}
  .sheet{background:#fff;margin:12px auto;padding:10mm;max-width:297mm;border:1px solid #bbb}
  .head{display:flex;align-items:stretch;border:1.5px solid #000}
  .head .logo{width:48mm;display:flex;align-items:center;justify-content:center;padding:4px;border-left:1px solid #000}
  .head .logo img{width:100%;height:auto}
  .head .ttl{flex:1;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:6px;text-align:center}
  .head .ttl h1{margin:0 0 4px;font-size:14pt}
  .head .meta{width:62mm;border-right:1px solid #000;padding:6px 8px;font-size:9pt;display:flex;flex-direction:column;justify-content:center;gap:4px}
  .info{display:flex;justify-content:space-between;margin:6px 2px;font-size:9pt}
  table{width:100%;border-collapse:collapse;table-layout:fixed}
  thead{display:table-header-group}
  tr{break-inside:avoid;page-break-inside:avoid}
  th,td{border:1px solid #000;padding:3px 4px;vertical-align:middle;font-size:8.5pt;overflow-wrap:anywhere}
  th{background:#e7ebf1;font-weight:700;text-align:center}
  td.c{text-align:center}
  td.n{text-align:left;direction:ltr;font-variant-numeric:tabular-nums;white-space:nowrap}
  td.ltr{direction:ltr;text-align:left;font-variant-numeric:tabular-nums}
  tr.sum td{font-weight:700;background:#f2f2f2}
  td.sign{height:9mm}
  .empty{text-align:center;padding:14px}
  .bar{max-width:297mm;margin:0 auto 12px;text-align:left}
  .bar button{font-family:"Vazirmatn",Tahoma,sans-serif;font:inherit;padding:8px 18px;border:1px solid #1d4e89;background:#1d4e89;color:#fff;border-radius:6px;cursor:pointer}
  @media print{
    body{background:#fff}
    .sheet{margin:0;border:0;padding:0;max-width:none}
    .bar{display:none}
    *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
</style>
<link rel="stylesheet" href="../assets/experience-ui.css?v=1">
</head>
<body class="print-document print-list">
<div class="bar"><button type="button" onclick="window.print()">چاپ / ذخیره به‌صورت PDF</button></div>
<div class="sheet">
  <div class="head">
    <div class="logo"><?php if ($logo): ?><img src="<?= $logo ?>" alt="EL ZOCO"><?php endif; ?></div>
    <div class="ttl">
      <h1>لیست درخواست‌های <?= e($kindTitle) ?></h1>
      <div>وضعیت: <b><?= e($label) ?></b></div>
    </div>
    <div class="meta">
      <div>تاریخ چاپ: <b><?= e($todayStr) ?></b></div>
      <div>تعداد درخواست: <b><?= count($rows) ?></b></div>
    </div>
  </div>

  <table>
    <colgroup>
      <col style="width:4%"><col style="width:8%"><col style="width:8%"><col style="width:13%">
      <col style="width:8%"><col style="width:8%"><col style="width:15%"><col style="width:9%">
      <col style="width:9%"><col style="width:9%"><col style="width:9%">
    </colgroup>
    <thead>
      <tr>
        <th>ردیف</th><th>کد پیگیری</th><th>تاریخ</th><th>نام و نام خانوادگی</th>
        <th>کد ملی</th><th>کد پرسنلی</th><th>شماره شبا</th><th>مبلغ درخواستی (ریال)</th>
        <th>مبلغ تأییدی (ریال)</th><th>وضعیت</th><th>امضا</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="11" class="empty">درخواستی برای این وضعیت ثبت نشده است.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td class="c"><?= $i + 1 ?></td>
        <td class="ltr"><?= e($r['tracking']) ?></td>
        <td class="c"><?= e($r['jdate']) ?></td>
        <td><?= e($r['name']) ?><?php if (!empty($r['post'])): ?><br><small><?= e($r['post']) ?></small><?php endif; ?></td>
        <td class="ltr"><?= e($r['national_code']) ?></td>
        <td class="ltr"><?= e($r['personnel_code']) ?></td>
        <td style="font-size:7.5pt"><span class="ltr"><?= e($r['sheba']) ?></span><?php if (!empty($r['sheba_owner'])): ?><br><?= e($r['sheba_owner']) ?><?php endif; ?></td>
        <td class="n"><?= $num($r['amount']) ?></td>
        <td class="n"><?= $num($r['approved_amount']) ?></td>
        <td class="c"><?= e($r['status']) ?></td>
        <td class="sign"></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows): ?>
      <tr class="sum">
        <td colspan="7" class="c">جمع کل</td>
        <td class="n"><?= $num($sumAmount) ?></td>
        <td class="n"><?= $num($sumApproved) ?></td>
        <td colspan="2"></td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>

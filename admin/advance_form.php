<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
$admin = require_admin_page();

$id = (int)($_GET['id'] ?? 0);
$r = one('SELECT r.*, e.post FROM advance_requests r LEFT JOIN employees e ON e.id = r.employee_id WHERE r.id = ?', [$id]);
if (!$r || !approval_can_see($admin, (string)$r['stage'], (string)$r['direct_pc'])) {
    http_response_code(404);
    exit('درخواست مساعده پیدا نشد.');
}

$num = fn($v) => ($v === null || $v === '') ? '—' : number_format((int)$v);
$txt = fn($v) => ($v === null || trim((string)$v) === '') ? '—' : (string)$v;
$jalaliFromDb = function (?string $dt): string {
    if (!$dt) {
        return '—';
    }
    $d = new DateTimeImmutable($dt, new DateTimeZone(APP_TZ));
    return jalali_str(jalali_from_gregorian((int)$d->format('Y'), (int)$d->format('n'), (int)$d->format('j')))
        . ' ' . $d->format('H:i');
};
$kind = fin_key((string)($r['kind'] ?? 'advance'));
$kindLabel = FIN_KINDS[$kind]['label'];
$maxAllowed = advance_max((float)$r['salary'], $kind);
$logoFile = dirname(__DIR__) . '/assets/logo-elzoco.png';
$logo = is_file($logoFile) ? 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoFile)) : '';
$approved = $r['approved_amount'] !== null ? $num($r['approved_amount']) : '—';
$download = isset($_GET['download']);

if ($download) {
    header('Content-Disposition: attachment; filename="' . $kind . '-' . preg_replace('/[^A-Za-z0-9]/', '', (string)$r['tracking']) . '.html"');
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/fonts/fonts.css">
<title>فرم <?= e($kindLabel) ?> <?= e($r['tracking']) ?></title>
<style>
  @page{size:A4 portrait;margin:12mm}
  *{box-sizing:border-box}
  body{margin:0;background:#eef1f5;color:#000;font-family:"Vazirmatn",Tahoma,sans-serif;font-size:11pt}
  .sheet{max-width:190mm;margin:14px auto;background:#fff;padding:14mm 12mm;border:1px solid #bbb}
  .top{display:flex;align-items:stretch;border:1.5px solid #000}
  .top .logo{width:42mm;display:flex;align-items:center;justify-content:center;padding:6px;border-left:1px solid #000}
  .top .logo img{width:100%;height:auto}
  .top .ttl{flex:1;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:8px;text-align:center}
  .top .ttl h1{margin:0 0 6px;font-size:16pt}
  .top .ttl div{font-size:10pt}
  .top .meta{width:52mm;border-right:1px solid #000;padding:8px 10px;font-size:10pt;display:flex;flex-direction:column;justify-content:center;gap:6px}
  h2{font-size:11pt;margin:14px 0 6px;padding:4px 8px;background:#e7ebf1;border:1px solid #000;border-bottom:0}
  table{width:100%;border-collapse:collapse;table-layout:fixed}
  col.k{width:20%} col.v{width:30%}
  td,th{border:1px solid #000;padding:6px 8px;vertical-align:middle;font-size:10pt;overflow-wrap:anywhere}
  th{background:#f5f7fa;text-align:right;font-weight:700}
  td.n{text-align:left;direction:ltr;font-variant-numeric:tabular-nums;font-weight:700;white-space:nowrap}
  td.ltr{direction:ltr;text-align:left;font-variant-numeric:tabular-nums}
  .sign td{height:26mm;vertical-align:top;text-align:center;font-weight:700;font-size:10pt;padding:6px 6px 4px}
  .sign .l{display:block;margin-top:18mm;border-top:1px dashed #555;padding-top:3px;font-weight:400;color:#444}
  .foot{margin-top:10px;font-size:9.5pt;text-align:center;color:#333}
  .bar{max-width:190mm;margin:0 auto 14px;text-align:left}
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
<body class="print-document print-form">
<div class="sheet">
  <div class="top">
    <div class="logo"><?php if ($logo): ?><img src="<?= $logo ?>" alt="EL ZOCO"><?php endif; ?></div>
    <div class="ttl">
      <h1>فرم درخواست و پرداخت <?= e($kindLabel) ?></h1>
      <div>کد پیگیری: <b class="ltr"><?= e($r['tracking']) ?></b></div>
    </div>
    <div class="meta">
      <div>تاریخ ثبت: <b><?= e($r['jdate']) ?></b></div>
      <div>وضعیت: <b><?= e($r['status']) ?></b></div>
    </div>
  </div>

  <h2>۱. اطلاعات متقاضی</h2>
  <table>
    <colgroup><col class="k"><col class="v"><col class="k"><col class="v"></colgroup>
    <tr><th>نام و نام خانوادگی</th><td><?= e($r['name']) ?></td><th>کد پرسنلی</th><td class="ltr"><?= e($r['personnel_code']) ?></td></tr>
    <tr><th>کد ملی</th><td class="ltr"><?= e($r['national_code']) ?></td><th>عنوان پست</th><td><?= e($txt($r['post'])) ?></td></tr>
    <tr><th>حقوق ماهانه (ریال)</th><td class="n"><?= $num($r['salary']) ?></td><th>سقف مجاز مساعده (ریال)</th><td class="n"><?= $num($maxAllowed) ?></td></tr>
    <tr><th>بازه‌ی ثبت</th><td colspan="3" class="ltr"><?= e($txt($r['window_start'])) ?> تا <?= e($txt($r['window_end'])) ?></td></tr>
  </table>

  <h2>۲. مشخصات <?= e($kindLabel) ?></h2>
  <table>
    <colgroup><col class="k"><col class="v"><col class="k"><col class="v"></colgroup>
    <tr><th>مبلغ درخواستی (ریال)</th><td class="n"><?= $num($r['amount']) ?></td><th>مبلغ تأییدی (ریال)</th><td class="n"><?= $approved ?></td></tr>
    <tr><th>شماره شبا</th><td colspan="3" class="ltr"><?= e($r['sheba']) ?></td></tr>
    <tr><th>تاریخ ثبت درخواست</th><td><?= $jalaliFromDb($r['created_at']) ?></td><th>وضعیت</th><td><?= e($r['status']) ?></td></tr>
  </table>

  <h2>۳. تصمیم مدیر</h2>
  <table>
    <colgroup><col class="k"><col class="v"><col class="k"><col class="v"></colgroup>
    <tr><th>تصمیم‌گیرنده</th><td><?= e($txt($r['decided_by'])) ?></td><th>تاریخ تصمیم</th><td><?= $jalaliFromDb($r['decided_at']) ?></td></tr>
    <tr><th>ملاحظات</th><td colspan="3"><?= e($txt($r['note'])) ?></td></tr>
  </table>

  <h2>۴. تأیید و امضا</h2>
  <table class="sign">
    <tr>
      <td>درخواست‌کننده<span class="l">امضا و تاریخ</span></td>
      <td>مدیر مستقیم<span class="l">امضا و تاریخ</span></td>
      <td>مدیر کل<span class="l">امضا و تاریخ</span></td>
      <td>واحد مالی و پرداخت<span class="l">امضا و تاریخ</span></td>
    </tr>
  </table>
  <div class="foot">این فرم پس از تکمیل و امضا، مبنای پرداخت <?= e($kindLabel) ?> است.</div>
</div>
<div class="bar"><button type="button" onclick="window.print()">چاپ / ذخیره به‌صورت PDF</button></div>
</body>
</html>

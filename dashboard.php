<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
start_session_secure();
require_emp_page();
$appName = cfg('app_name');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>پنل کارمند | <?= e($appName) ?></title>
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="assets/refresh-ui.css?v=2">
<link rel="stylesheet" href="assets/shift-cards.css?v=1">
<link rel="stylesheet" href="assets/notify-ui.css?v=1">
<link rel="stylesheet" href="assets/experience-ui.css?v=3">
<link rel="stylesheet" href="assets/login-logo.css?v=2">
</head>
<body class="employee-app">
<header class="topbar">
  <div class="in">
    <div class="brand"><img class="brand-logo" src="assets/logo-login.png" alt="EL ZOCO"><span><?= e($appName) ?></span></div>
    <div class="row"><span class="user" id="who"></span><a class="out" href="#" onclick="logout();return false;">خروج</a></div>
  </div>
</header>
<main class="wrap">
  <div id="loading" class="card empty">در حال بارگذاری…</div>
  <div id="app" style="display:none">
    <section class="card" id="info" style="margin-bottom:18px"></section>
    <nav class="tabs" id="tabs">
      <button data-tab="payslip" onclick="switchTab('payslip')">فیش حقوقی</button>
      <button data-tab="penalties" onclick="switchTab('penalties')">جریمه‌های من</button>
      <div class="dd" id="ddReq">
        <button type="button" class="dd-btn" data-tab="requests" aria-haspopup="true" aria-expanded="false" aria-controls="ddMenu" onclick="toggleReqMenu(event)">
          <span>درخواست‌های من</span><svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="dd-menu" id="ddMenu" role="menu">
          <a href="#advance" class="dd-item" role="menuitem" data-go="advance" style="--i:0;--c1:#14b8a6;--c2:#0f9d8a">
            <span class="dd-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="6.5" width="19" height="11" rx="2.5"/><circle cx="12" cy="12" r="2.4"/><path d="M6 10v4M18 10v4"/></svg></span>
            <span class="dd-tx"><b>مساعده</b><small>درخواست مساعده از حقوق</small></span>
          </a>
          <a href="#loan" class="dd-item" role="menuitem" data-go="loan" style="--i:1;--c1:#3b82f6;--c2:#1d4e89">
            <span class="dd-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9.5 12 4l9 5.5"/><path d="M5 10.5V18M9.5 10.5V18M14.5 10.5V18M19 10.5V18"/><path d="M3 20.5h18"/></svg></span>
            <span class="dd-tx"><b>وام</b><small>درخواست وام با شرایط اعلام‌شده</small></span>
          </a>
          <a href="#leave" class="dd-item" role="menuitem" data-go="leave" style="--i:2;--c1:#f59e0b;--c2:#e8a317">
            <span class="dd-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg></span>
            <span class="dd-tx"><b>مرخصی</b><small>ثبت مرخصی ساعتی، روزانه یا استحقاقی</small></span>
          </a>
          <a href="#promotion" class="dd-item" role="menuitem" data-go="promotion" style="--i:3;--c1:#a78bfa;--c2:#7c3aed">
            <span class="dd-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg></span>
            <span class="dd-tx"><b>ارتقا گروه شغلی</b><small>درخواست ارتقا یا افزایش حقوق</small></span>
          </a>
        </div>
      </div>
    </nav>

    <div class="pane" id="pane-payslip">
      <div class="card">
        <div class="section-title no-print">
          <h2>فیش حقوقی</h2>
          <label style="flex-direction:row;align-items:center;gap:8px;font-weight:600">دوره:
            <select id="period" onchange="loadSlip(this.value)" style="min-width:200px"></select>
          </label>
        </div>
        <div id="slip"></div>
      </div>
    </div>

    <div class="pane" id="pane-advance">
      <div class="pane-nav"><a class="back" href="#payslip">‹ بازگشت به فیش حقوقی</a><span class="crumb">درخواست‌های من ‹ <b>مساعده</b></span></div>
      <div id="advBody"></div>
    </div>

    <div class="pane" id="pane-loan">
      <div class="pane-nav"><a class="back" href="#payslip">‹ بازگشت به فیش حقوقی</a><span class="crumb">درخواست‌های من ‹ <b>وام</b></span></div>
      <div id="loanBody"></div>
    </div>

    <div class="pane" id="pane-penalties">
      <div class="pane-nav"><a class="back" href="#payslip">‹ بازگشت به فیش حقوقی</a><span class="crumb"><b>جریمه‌های من</b></span></div>
      <div id="penBody"></div>
    </div>

    <div class="pane" id="pane-promotion">
      <div class="pane-nav"><a class="back" href="#payslip">‹ بازگشت به فیش حقوقی</a><span class="crumb">درخواست‌های من ‹ <b>ارتقا گروه شغلی</b></span></div>
      <div id="promoBody"></div>
    </div>

    <div class="pane" id="pane-leave">
      <div class="pane-nav"><a class="back" href="#payslip">‹ بازگشت به فیش حقوقی</a><span class="crumb">درخواست‌های من ‹ <b>مرخصی</b></span></div>
      <div id="leaveBalanceSummary"></div>
      <div class="grid g2" style="align-items:start">
        <div class="card">
          <h2>درخواست مرخصی</h2>
          <form class="stack" onsubmit="submitLeave(event)">
            <label>نوع مرخصی<select name="type" id="lvType"></select></label>
            <label id="lvFromLabel">از تاریخ<input name="from" class="ltr" data-jdate placeholder="1405/07/20" required></label>
            <div id="lvHourly" style="display:none" class="grid g2">
              <label>ساعت شروع<input name="start_time" class="ltr" placeholder="09:00"></label>
              <label>ساعت پایان<input name="end_time" class="ltr" placeholder="11:30"></label>
            </div>
            <div id="lvRange">
              <label>تا تاریخ<input name="to" class="ltr" data-jdate placeholder="1405/07/22"></label>
            </div>
            <label>دلیل یا توضیح<textarea name="reason" required></textarea></label>
            <button class="btn" type="submit">ثبت درخواست مرخصی</button>
          </form>
        </div>
        <div class="card flush"><div style="padding:18px 20px 0"><h2>درخواست‌های من</h2></div><div id="lvBody"></div></div>
      </div>
    </div>
  </div>
</main>
<script>window.API_BASE = ''; window.APP_NAME = <?= json_encode($appName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="assets/app.js"></script>
<script src="assets/jdate.js"></script>
<script src="assets/employee.js?v=9"></script>
<script>window.NOTIFY_SCOPE = 'employee';</script>
<script src="assets/notify.js?v=2"></script>
</body>
</html>

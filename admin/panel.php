<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
$admin = require_admin_page();
$isAdmin = in_array($admin['role'], ['admin', 'senior'], true); // دسترسی کامل: ادمین و مدیر ارشد
$roleLabel = ['admin' => 'ادمین', 'senior' => 'مدیر ارشد', 'manager' => 'مدیر / سرپرست مستقیم'][$admin['role']] ?? 'مدیر';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>پنل مدیریت | <?= e(cfg('app_name')) ?></title>
<link rel="stylesheet" href="../assets/app.css">
<style>
  :root{--sb-w:264px}
  html,body{overflow-x:clip}
  .shell{display:grid;grid-template-columns:var(--sb-w) minmax(0,1fr);min-height:100vh}
  .side{background:linear-gradient(180deg,#0a1f3d 0%,#0f2d55 60%,#12365f 100%);color:#fff;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;padding:22px 16px 18px;box-shadow:inset -1px 0 0 rgba(255,255,255,.06);z-index:60;min-width:0}
  .side::before{content:"";position:absolute;inset:0;background:radial-gradient(260px 180px at 100% 0%,rgba(232,163,23,.12),transparent 70%);pointer-events:none}
  .side > *{position:relative}
  .side-brand{display:flex;align-items:center;gap:12px;padding:6px 8px 20px;margin-bottom:6px;border-bottom:1px solid rgba(255,255,255,.08)}
  .side-brand .logo{width:42px;height:42px;flex:0 0 42px}
  .side-brand .logo-svg{width:100%;height:100%;display:block}
  .side-brand b{display:block;font-size:1.02rem;line-height:1.4}
  .side-brand small{display:block;color:#9fb6d6;font-size:.76rem;margin-top:2px}
  .side-label{font-size:.72rem;color:#7d97ba;padding:14px 12px 6px;letter-spacing:.02em}
  .side nav{display:flex;flex-direction:column;gap:4px;flex:1;overflow-y:auto;scrollbar-width:none}
  .side nav::-webkit-scrollbar{display:none}
  .side nav button{width:100%;text-align:right;font:inherit;font-weight:600;font-size:.95rem;color:#c3d6ef;background:transparent;border:0;padding:11px 12px;border-radius:14px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:background .2s,color .2s,transform .2s}
  .side nav button .ico{width:34px;height:34px;flex:0 0 34px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.07);transition:background .2s,box-shadow .2s}
  .side nav button .ico svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
  .side nav button .lbl{flex:1;min-width:0;text-align:right}
  .side nav button:hover{background:rgba(255,255,255,.07);color:#fff;transform:translateX(-2px)}
  .side nav button.on{background:linear-gradient(100deg,rgba(249,115,22,.24),rgba(249,115,22,.07));color:#fff;box-shadow:inset -4px 0 0 #f97316,0 8px 22px -16px rgba(249,115,22,.55)}
  .side nav button.on .ico{background:linear-gradient(135deg,#fdba74,#f97316);color:#431407;box-shadow:0 7px 18px -7px rgba(249,115,22,.9)}
  .side .cnt{background:#f97316;color:#fff;border:1px solid rgba(255,255,255,.28);border-radius:999px;padding:1px 8px;font-size:.72rem;font-weight:900;min-width:22px;text-align:center;box-shadow:0 5px 12px -7px rgba(249,115,22,.95)}
  .side-foot{margin-top:14px;padding:14px;border-radius:16px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px}
  .side-foot .av{width:38px;height:38px;flex:0 0 38px;border-radius:12px;background:linear-gradient(135deg,#2ec4b6,#0f9d8a);display:grid;place-items:center;font-weight:800;color:#fff}
  .side-foot .who{flex:1;min-width:0}
  .side-foot .who b{display:block;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .side-foot .who small{color:#9fb6d6;font-size:.76rem}
  .side-foot .out{width:38px;height:38px;flex:0 0 38px;border-radius:12px;border:0;background:rgba(239,68,68,.16);color:#ffb4b4;cursor:pointer;display:grid;place-items:center}
  .side-foot .out:hover{background:rgba(239,68,68,.3);color:#fff}
  .side-foot .out svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .m-top{display:none}
  .scrim{display:none}
  .main{padding:26px 30px 60px;min-width:0}
  .main > section{min-width:0}
  .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;min-width:0}
  .head h1{margin:0 0 4px}
  .sec{display:none} .sec.on{display:block;animation:rise .35s ease both}
  .grid > *{min-width:0}
  input,select,textarea{max-width:100%}
  .list-report{max-height:260px;overflow:auto;font-size:.88rem;line-height:1.9}
  .list-report li{margin-bottom:2px}
  .dz{border:2px dashed #b8c8de;border-radius:16px;padding:26px;text-align:center;background:#f8fbff;cursor:pointer;transition:.2s}
  .dz.over{border-color:var(--teal2);background:#ecfdf8}
  .dz input{display:none}

  /* ===== موبایل / تبلت: سایدبار تبدیل به کشوی بیرون‌کش ===== */
  @media(max-width:900px){
    .shell{grid-template-columns:minmax(0,1fr)}
    .side{position:fixed;top:0;right:0;bottom:0;height:100dvh;width:min(84vw,300px);transform:translateX(105%);transition:transform .32s cubic-bezier(.2,.8,.3,1);box-shadow:-20px 0 50px -10px rgba(0,0,0,.45);padding:18px 14px calc(16px + env(safe-area-inset-bottom,0px))}
    body.nav-open .side{transform:translateX(0)}
    .scrim{display:block;position:fixed;inset:0;background:rgba(6,18,38,.45);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .3s;z-index:55}
    body.nav-open .scrim{opacity:1;pointer-events:auto}
    .m-top{display:flex;position:sticky;top:0;z-index:50;align-items:center;gap:12px;padding:10px 14px;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);box-shadow:0 6px 18px -14px rgba(11,35,67,.5)}
    .m-top .burger{width:44px;height:44px;flex:0 0 44px;border-radius:14px;border:0;background:linear-gradient(135deg,#0f2d55,#1d4e89);color:#fff;display:grid;place-items:center;cursor:pointer;box-shadow:0 8px 18px -10px rgba(11,35,67,.8)}
    .m-top .burger svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
    .m-top .ttl{flex:1;min-width:0}
    .m-top .ttl b{display:block;font-size:.98rem;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .m-top .ttl small{color:var(--muted);font-size:.74rem}
    .m-top .coin{width:36px;height:36px;flex:0 0 36px}
    .m-top .coin svg{width:100%;height:100%;display:block}
    .main{padding:16px 14px 48px}
    .head{margin-bottom:14px}
    .head h1{font-size:1.2rem}
  }
  @media(max-width:900px){
    .grid.g2,.grid.g3,.grid.g4,.g2,.g3,.g4{grid-template-columns:minmax(0,1fr)!important}
  }
  @media(max-width:560px){
    .card{padding:14px}
    .card.flush{padding:0}
    .card.flush > div{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    table.t{min-width:560px}
    table.t th,table.t td{padding:10px 8px;font-size:.84rem}
    .btn{min-height:44px}
    .row{flex-wrap:wrap}
  }
  @media(prefers-reduced-motion:reduce){.side,.scrim,.side nav button{transition:none!important}}

  /* ===== کارت درخواست ارتقا ===== */
  .rq-list{display:grid;gap:16px}
  .rq{background:#fff;border:1px solid #e3ebf6;border-radius:22px;padding:20px;box-shadow:0 14px 34px -22px rgba(11,35,67,.4);position:relative;overflow:hidden;display:grid;gap:16px}
  .rq::before{content:"";position:absolute;inset-inline-start:0;top:0;bottom:0;width:4px;background:var(--rq-c,#94a3b8)}
  .rq.st-direct,.rq.st-evaluated{--rq-c:#f59e0b}
  .rq.st-general{--rq-c:#3b82f6}
  .rq.st-admin{--rq-c:#64748b}
  .rq.st-final{--rq-c:#16a34a}
  .rq.st-rejected{--rq-c:#dc2626}
  .rq-h{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;gap:14px;align-items:center}
  .rq-av{width:46px;height:46px;border-radius:15px;display:grid;place-items:center;font-weight:800;font-size:1.05rem;color:#fff;background:linear-gradient(135deg,#1d4e89,#0b2343)}
  .rq-who{min-width:0;display:grid;gap:3px}
  .rq-who b{font-size:1.02rem;color:#0b2343;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rq-who span{font-size:.82rem}
  .rq-tr{display:grid;gap:4px;justify-items:end;font-size:.8rem}
  .rq-code{background:#f1f5fb;border-radius:9px;padding:3px 10px;font-weight:700;color:#0b2343;font-size:.8rem}
  .rq-badge{font-size:.82rem;padding:6px 12px;white-space:nowrap}

  .rq-steps{display:flex;align-items:center;gap:6px;background:#f7faff;border-radius:16px;padding:12px 14px;overflow-x:auto}
  .rq-step{display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:700;white-space:nowrap;color:#94a3b8}
  .rq-dot{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:800;background:#e2e8f0;color:#475569;flex:0 0 26px}
  .rq-step.done{color:#166534}
  .rq-step.done .rq-dot{background:#16a34a;color:#fff}
  .rq-step.now{color:#0b2343}
  .rq-step.now .rq-dot{background:#0b2343;color:#fff;box-shadow:0 0 0 4px rgba(11,35,67,.12)}
  .rq-step.off{opacity:.45}
  .rq-line{flex:1 1 24px;min-width:24px;height:2px;background:#dbe4f0;border-radius:2px}

  .rq-change{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr) auto;gap:12px;align-items:center;background:linear-gradient(135deg,#fff,#f5f9ff);border:1px dashed #c8d7ea;border-radius:16px;padding:14px 16px}
  .rq-from,.rq-to{display:grid;gap:4px;min-width:0}
  .rq-from small,.rq-to small,.rq-reason small,.rq-tile small{color:#64748b;font-size:.76rem;font-weight:700}
  .rq-from b,.rq-to b{font-size:.98rem;color:#0b2343;overflow-wrap:anywhere}
  .rq-to b{color:#1d4e89}
  .rq-arrow{width:34px;height:34px;border-radius:50%;background:#0b2343;color:#fff;display:grid;place-items:center;font-weight:800}
  .rq-chip{background:#fff7e0;color:#8a5a07;border:1px solid #f5d88a;border-radius:999px;padding:5px 12px;font-size:.82rem;font-weight:700;white-space:nowrap}

  .rq-reason{display:grid;gap:6px;padding:4px 4px 0}
  .rq-reason p{margin:0;line-height:2;color:#334155;background:#fafcff;border-inline-start:3px solid #cbd5e1;padding:8px 14px;border-radius:0 10px 10px 0}

  .rq-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
  .rq-tile{background:#fff;border:1px solid #e3ebf6;border-radius:16px;padding:12px 14px;display:grid;gap:6px;font-size:.9rem;line-height:1.8}
  .rq-tile.money{background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border-color:#bbf7d0}
  .rq-tile.score{background:linear-gradient(135deg,#fffbeb,#fefce8);border-color:#fde68a}
  .rq-tile.ok{background:#f0fdf4;border-color:#bbf7d0}
  .rq-tile.bad{background:#fef2f2;border-color:#fecaca}
  .rq-money{font-size:1.15rem;font-weight:800;color:#166534}
  .rq-score{font-size:1.4rem;font-weight:800;color:#92400e}
  .rq-score small{font-size:.8rem;color:#b45309;font-weight:700}
  .rq-note{margin:2px 0 0;color:#475569;font-size:.86rem}

  .rq-a{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;border-top:1px solid #edf2f9;padding-top:14px}
  .rq-empty{background:#fff;border:1px dashed #c8d7ea;border-radius:20px;padding:40px 16px;text-align:center;color:var(--muted)}

  @media(max-width:900px){
    .rq-change{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)}
    .rq-chip{grid-column:1/-1;justify-self:start}
  }
  @media(max-width:760px){
    .rq{padding:16px;border-radius:20px;gap:14px}
    .rq-h{grid-template-columns:auto minmax(0,1fr)}
    .rq-tr{grid-column:1/-1;justify-items:start;grid-auto-flow:column;justify-content:start;align-items:center}
    .rq-badge{grid-column:1/-1;justify-self:start}
    .rq-steps{padding:10px 12px}
    .rq-step span:last-child{font-size:.78rem}
    .rq-change{grid-template-columns:minmax(0,1fr);gap:8px;padding:12px 14px}
    .rq-arrow{transform:rotate(-90deg);justify-self:start;width:28px;height:28px}
    .rq-chip{grid-column:auto}
    .rq-tiles{grid-template-columns:minmax(0,1fr)}
    .rq-a{display:grid;grid-template-columns:minmax(0,1fr)}
    .rq-a .btn{width:100%}
  }

  /* ===== جدول‌ها: دسکتاپ ===== */
  .card.flush{border-radius:20px;border:1px solid #e3ebf6;box-shadow:0 10px 30px -18px rgba(11,35,67,.35)}
  .card.flush > div{overflow:auto;-webkit-overflow-scrolling:touch}
  table.t{width:100%;border-collapse:separate;border-spacing:0;font-size:.9rem;color:var(--text)}
  table.t th{position:sticky;top:0;z-index:1;background:linear-gradient(180deg,#f7faff,#eef4fb);color:#0b2343;text-align:right;padding:14px 16px;font-size:.8rem;font-weight:800;letter-spacing:.01em;border-bottom:1px solid #d9e4f2;white-space:nowrap}
  table.t th:first-child{border-start-start-radius:0}
  table.t td{padding:14px 16px;border-bottom:1px solid #edf2f9;vertical-align:middle;line-height:1.85;min-width:120px}
  table.t tr:not(.hd):nth-child(even) td{background:#fbfdff}
  table.t tr:not(.hd):hover td{background:#f1f7ff;transition:background .15s}
  table.t tr:last-child td{border-bottom:0}
  table.t td.empty,table.t td[colspan]{text-align:center;color:var(--muted);padding:34px 16px;min-width:0}
  table.t td .muted{font-size:.82rem}
  table.t td .btn.sm{padding:7px 12px;font-size:.82rem;border-radius:10px}
  table.t td .row{gap:6px;flex-wrap:wrap}

  /* ===== جدول‌ها: موبایل به صورت کارت ===== */
  @media(max-width:760px){
    .card.flush{background:transparent;border:0;box-shadow:none;border-radius:0;padding:0;overflow:visible}
    .card.flush > div{overflow:visible!important}
    table.t{min-width:0!important;display:block;background:transparent;font-size:.9rem}
    table.t tbody{display:block}
    table.t tr.hd{display:none}
    table.t tr:not(.hd){display:block;background:#fff;border:1px solid #e3ebf6;border-radius:18px;margin:0 0 14px;padding:6px 0;box-shadow:0 12px 28px -20px rgba(11,35,67,.45);overflow:hidden}
    table.t tr:not(.hd):nth-child(even) td{background:transparent}
    table.t tr:not(.hd):hover td{background:transparent}
    table.t td{display:grid;grid-template-columns:minmax(88px,34%) minmax(0,1fr);gap:10px;align-items:start;border-bottom:1px dashed #e6edf7;padding:10px 16px;min-width:0;line-height:1.8;text-align:right;word-break:break-word}
    table.t td:last-child{border-bottom:0}
    table.t td::before{content:attr(data-label);color:#64748b;font-size:.78rem;font-weight:700;line-height:1.9;padding-top:1px}
    table.t td[data-label=""]{display:block}
    table.t td[data-label=""]::before{content:none}
    table.t td:empty{display:none}
    table.t td[colspan]{display:block;text-align:center;border-bottom:0;padding:26px 16px}
    table.t td[colspan]::before{content:none}
    table.t td .row{justify-content:flex-start}
    table.t td .btn.sm{min-height:40px;padding:8px 14px}
  }
</style>
<link rel="stylesheet" href="../assets/admin-ui.css?v=10">
<link rel="stylesheet" href="../assets/admin-settings.css?v=3">
<link rel="stylesheet" href="../assets/notify-ui.css?v=1">
<link rel="stylesheet" href="../assets/experience-ui.css?v=3">
<link rel="stylesheet" href="../assets/request-management.css?v=1">
<link rel="stylesheet" href="../assets/employee-dossier.css?v=4">
</head>
<body class="admin-app">
<div class="shell">
  <div class="scrim" onclick="toggleNav(false)"></div>
  <aside class="side" id="side">
    <div class="side-brand"><div class="logo"><svg class="logo-svg" viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="lgTile" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1d4e89"/><stop offset="1" stop-color="#0b2343"/></linearGradient><radialGradient id="lgCoin" cx="35%" cy="30%" r="75%"><stop offset="0" stop-color="#fff4c7"/><stop offset=".45" stop-color="#ffd166"/><stop offset=".8" stop-color="#e8a317"/><stop offset="1" stop-color="#b87b0a"/></radialGradient></defs><rect x="2" y="2" width="60" height="60" rx="16" fill="url(#lgTile)"/><rect x="2.6" y="2.6" width="58.8" height="58.8" rx="15.4" fill="none" stroke="#ffffff" stroke-opacity=".18"/><ellipse cx="32" cy="52" rx="19" ry="4" fill="#000" opacity=".35"/><path d="M14 46 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="46" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="46" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 40 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="40" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="40" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 34 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="34" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="34" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M20 31 q4 -5 10 -5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".75"/></svg></div><div><b>مدیریت حقوق</b><small>پنل مدیریت الزوکو</small></div></div>
    <div class="side-label">منو</div>
    <nav id="menu">
      <button data-s="dashboard" class="on">
        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></span>
        <span class="lbl">داشبورد</span>
      </button>
      <?php if ($isAdmin): ?>
      <button data-s="upload">
        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg></span>
        <span class="lbl">بارگذاری اکسل ماهانه</span>
      </button>
      <?php endif; ?>
      <button data-s="employees">
        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M1 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/><path d="M19 15a5 5 0 0 1 4 5v1"/></svg></span>
        <span class="lbl">کارمندان</span>
      </button>
      <div class="side-group" id="personnelGroup">
        <button type="button" class="side-group-toggle" data-submenu="personnelSubmenu" aria-expanded="false" aria-controls="personnelSubmenu">
          <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M1 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/><path d="M19 15a5 5 0 0 1 4 5v1"/></svg></span>
          <span class="lbl">امور پرسنلی</span>
          <span class="group-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></span>
        </button>
        <div class="side-submenu" id="personnelSubmenu" aria-hidden="true">
          <div class="side-submenu-inner">
            <button type="button" data-s="advances">
              <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg></span>
              <span class="lbl">مساعده و وام</span><span class="cnt" id="cAdv" style="display:none"></span>
            </button>
            <button type="button" data-s="promotions">
              <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 7l-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/></svg></span>
              <span class="lbl">ارتقا گروه شغلی</span><span class="cnt" id="cPr" style="display:none"></span>
            </button>
            <button type="button" data-s="leaves">
              <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></span>
              <span class="lbl">مرخصی‌ها</span><span class="cnt" id="cLv" style="display:none"></span>
            </button>
          </div>
        </div>
      </div>
      <?php if ($isAdmin): ?>
      <button data-s="admins">
        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <span class="lbl">مدیران و تنظیمات</span>
      </button>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <div class="av"><?= e(mb_substr($admin['name'],0,1)) ?></div>
      <div class="who"><b><?= e($admin['name']) ?></b><small><?= e($roleLabel) ?></small></div>
      <button class="out" type="button" title="خروج" onclick="logout()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg></button>
    </div>
  </aside>


  <main class="main">
  <header class="m-top">
    <button class="burger" type="button" aria-label="باز کردن منو" onclick="toggleNav(true)"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    <div class="ttl"><b>مدیریت حقوق</b><small><?= e($admin['name']) ?> · <?= e($roleLabel) ?></small></div>
    <div class="coin"><svg viewBox="0 0 64 64" aria-hidden="true"><rect x="2" y="2" width="60" height="60" rx="16" fill="#0f2d55"/><ellipse cx="32" cy="46" rx="18" ry="7" fill="#ffd166"/><ellipse cx="32" cy="40" rx="18" ry="7" fill="#ffd166"/><ellipse cx="32" cy="34" rx="18" ry="7" fill="#ffd166"/></svg></div>
  </header>
    <section class="sec on" id="s-dashboard"><div class="head"><div><h1>داشبورد</h1><div class="muted">وضعیت کلی کارکنان و آخرین بارگذاری</div></div></div><div id="dash"></div></section>

    <?php if ($isAdmin): ?>
    <section class="sec" id="s-upload">
      <div class="head"><div><h1>بارگذاری فایل اکسل ماهانه</h1><div class="muted">اطلاعات هویتی و حقوقی از شیت اطلاعات پایه در پرونده هر کارمند ثبت و به‌روزرسانی می‌شود؛ فیلدهای خالی اکسل اطلاعات قبلی را پاک نمی‌کنند و فیش‌ها و سوابق دوره‌های دیگر حفظ می‌شوند.</div></div></div>
      <div class="grid g2" style="align-items:start">
        <div class="card">
          <form id="upForm" class="stack">
            <div class="grid g2">
              <label>سال<input name="year" class="ltr" value="1405" required></label>
              <label>ماه<select name="month" required>
                <?php $ms=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
                $cur=(int)jalali_today()[1];
                foreach($ms as $i=>$m){ $sel = $i+1===$cur ? ' selected' : ''; echo "<option value=\"".($i+1)."\"$sel>$m</option>"; } ?>
              </select></label>
            </div>
            <label class="dz" id="dz">
              <div style="font-size:2rem">⬆</div>
              <div style="font-weight:700;margin-top:6px">فایل xlsx را اینجا بکشید یا کلیک کنید</div>
              <div class="hint" id="fname">حداکثر <?= (int)cfg('upload_max_mb') ?> مگابایت</div>
              <input type="file" name="file" accept=".xlsx" required>
            </label>
            <button class="btn" id="upBtn" type="submit">بارگذاری و به‌روزرسانی</button>
          </form>
          <div class="hint" style="margin-top:12px">اطلاعات و سوابق قبلی حذف نمی‌شوند. اگر کارمندی در فایل جدید نباشد، فقط وضعیت حضور او در آخرین فایل مشخص می‌شود. با هر بارگذاری، یک نسخه مستقل از اطلاعات و محاسبات فیش در تاریخچه پرونده هر کارمند ذخیره می‌شود؛ حتی بارگذاری مجدد همان دوره نسخه قبلی را پاک نمی‌کند. فیش فعال آن دوره با آخرین فایل به‌روزرسانی می‌شود و نسخه‌های قبلی در پرونده محفوظ می‌مانند. پیش از بارگذاری، خطای #VALUE! را برطرف کنید؛ ستون H شیت «بیمه» باید به‌جای مقدار خالی متنی، عدد ۰ داشته باشد.</div>
        </div>
        <div class="card"><h2>گزارش آخرین بارگذاری</h2><div id="upReport"><div class="empty">هنوز فایلی بارگذاری نشده است.</div></div></div>
      </div>
    </section>
    <?php endif; ?>

    <section class="sec" id="s-employees">
      <div class="head"><div><h1>کارمندان</h1><div class="muted">فهرست بر اساس آخرین فایل بارگذاری‌شده و پرونده‌های ثبت‌شده دستی</div></div><?php if ($isAdmin): ?><button class="btn" type="button" onclick="openEmployeeCreate()">＋ افزودن کارمند</button><?php endif; ?></div>
      <div class="card flush">
        <div style="padding:16px 18px;display:flex;gap:10px;flex-wrap:wrap;border-bottom:1px solid var(--line)">
          <input id="eq" placeholder="جستجو: نام، کد پرسنلی یا کد ملی" style="flex:1;min-width:220px">
          <select id="ef" style="min-width:180px" aria-label="فیلتر وضعیت کارمند">
            <option value="all">همه وضعیت‌ها</option><option value="active">فعال</option><option value="inactive">غیرفعال</option>
            <option value="incomplete">اطلاعات ناقص</option><option value="zero">کد ملی 0000000000</option><option value="nomanager">بدون مدیر مستقیم</option>
          </select>
          <select id="esSection" style="min-width:190px" aria-label="فیلتر سکشن">
            <option value="">همه سکشن‌ها</option><option value="__none__">بدون سکشن ثبت‌شده</option>
          </select>
          <button class="btn sm ghost" onclick="loadEmployees()">جستجو</button>
        </div>
        <div class="hint" id="sectionHint" style="padding:0 18px 12px">فیلتر سکشن بر اساس ستون «سکشن»، «بخش» یا «واحد کاری» در شیت اطلاعات پایه و اطلاعات پرونده پرسنلی است.</div>
        <?php if (!$isAdmin): ?><div class="hint" style="padding:0 18px 12px">این فهرست فقط کارکنان زیرمجموعه‌ی مستقیم و غیرمستقیم شما را نمایش می‌دهد؛ دسترسی به کارکنان سایر مدیران از سمت سرور نیز مسدود است.</div><?php endif; ?>
        <div id="bulkBar" style="display:none;padding:12px 18px;gap:10px;align-items:center;flex-wrap:wrap;background:#f2f7fd;border-bottom:1px solid var(--line)"><b>انتخاب‌شده: <span id="bulkCount">0</span></b><button class="btn sm" type="button" onclick="bulkSetManager()">تعیین مدیر مستقیم برای انتخاب‌شده‌ها</button><button class="btn sm ghost" type="button" onclick="toggleAllEmp(false)">لغو انتخاب</button></div>
        <div class="employee-table-scroll"><table class="t" id="etable"></table></div>
      </div>
    </section>


    <section class="sec" id="s-advances">
      <div class="head request-page-head"><div><h1>درخواست‌های مساعده و وام</h1><div class="muted">جستجو، بررسی مرحله‌ای و تهیه گزارش از درخواست‌های قابل مشاهده برای حساب شما</div></div></div>
      <div class="request-toolbar card">
        <div class="request-filter-grid">
          <label class="request-search">جستجوی نام، کد یا پیگیری<input id="advQ" placeholder="مثلاً نام کارمند یا کد پیگیری"></label>
          <label>نوع درخواست<select id="advKind" onchange="loadAdvances()"><option value="">مساعده و وام</option><option value="advance">فقط مساعده</option><option value="loan">فقط وام</option></select></label>
          <label>وضعیت مالی<select id="advStatus" onchange="loadAdvances()"><option value="">همه‌ی وضعیت‌ها</option><option>در انتظار بررسی</option><option>تایید شده</option><option>رد شده</option></select></label>
          <label>از تاریخ<input id="advFrom" class="ltr" data-jdate placeholder="1405/07/01"></label>
          <label>تا تاریخ<input id="advTo" class="ltr" data-jdate placeholder="1405/07/30"></label>
          <button class="btn sm" type="button" onclick="loadAdvances()">اعمال فیلتر</button>
        </div>
        <div class="request-export-row"><span class="request-export-hint">خروجی مطابق فیلترهای انتخاب‌شده</span><div class="row"><a class="btn sm ghost" id="advPrintLink" target="_blank" href="request_report.php?type=advance&mode=print">چاپ گزارش</a><a class="btn sm" id="advExcelLink" href="request_report.php?type=advance&mode=excel">خروجی Excel</a></div></div>
      </div>
      <div class="card flush"><div style="overflow:auto"><table class="t" id="atable"></table></div></div>
    </section>

    <section class="sec" id="s-promotions">
      <div class="head request-page-head"><div><h1>درخواست‌های ارتقای گروه شغلی</h1><div class="muted">بررسی دلایل، سوابق ارزیابی، تغییر شغل و افزایش حقوق پایه در گردش تأیید</div></div></div>
      <div class="request-toolbar card">
        <div class="request-filter-grid">
          <label class="request-search">جستجوی نام، کد یا شغل<input id="prQ" placeholder="نام، کد پرسنلی یا شغل درخواستی"></label>
          <label>مرحله درخواست<select id="prStage" onchange="loadPromotions()"><option value="">همه مراحل قابل مشاهده</option><option value="direct">نزد مدیر مستقیم</option><option value="general">نزد مدیر ارشد</option><option value="admin">نزد منابع انسانی</option><option value="evaluated">ارزیابی شده؛ منتظر مدیر</option><option value="final">تأیید نهایی</option><option value="rejected">رد نهایی</option></select></label>
          <label>از تاریخ ثبت<input id="prFrom" class="ltr" data-jdate placeholder="1405/07/01"></label>
          <label>تا تاریخ ثبت<input id="prTo" class="ltr" data-jdate placeholder="1405/07/30"></label>
          <button class="btn sm" type="button" onclick="loadPromotions()">اعمال فیلتر</button>
        </div>
        <div class="request-export-row"><span class="request-export-hint">خروجی شامل سوابق تصمیم و اطلاعات ارزیابی است</span><div class="row"><a class="btn sm ghost" id="prPrintLink" target="_blank" href="request_report.php?type=promotion&mode=print">چاپ گزارش</a><a class="btn sm" id="prExcelLink" href="request_report.php?type=promotion&mode=excel">خروجی Excel</a></div></div>
      </div>
      <div class="rq-list" id="ptable"></div>
    </section>

    <section class="sec" id="s-leaves">
      <div class="head request-page-head"><div><h1>درخواست‌های مرخصی</h1><div class="muted">بررسی نوع، مدت، تاریخ هم‌پوشان، دلیل کارمند و سوابق تصمیم مدیران پیش از تأیید یا رد</div></div></div>
      <div class="request-toolbar card">
        <div class="request-filter-grid">
          <label class="request-search">جستجوی نام، کد ملی یا پیگیری<input id="lvQ" placeholder="نام کارمند یا کد پیگیری"></label>
          <label>نوع مرخصی<select id="lvType" onchange="loadLeaves()"><option value="">همه انواع</option><option value="hourly">ساعتی</option><option value="daily">روزانه</option><option value="deserved">استحقاقی</option><option value="medical">پزشکی</option><option value="unpaid">بدون حقوق</option></select></label>
          <label>وضعیت بررسی<select id="lvStage" onchange="loadLeaves()"><option value="">همه وضعیت‌ها</option><option value="direct">نزد مدیر مستقیم</option><option value="general">نزد مدیر ارشد</option><option value="admin">نزد منابع انسانی</option><option value="final">تأیید نهایی</option><option value="rejected">رد نهایی</option></select></label>
          <label>از تاریخ<input id="lvFrom" class="ltr" data-jdate placeholder="1405/07/01"></label>
          <label>تا تاریخ<input id="lvTo" class="ltr" data-jdate placeholder="1405/07/30"></label>
          <button class="btn sm" type="button" onclick="loadLeaves()">اعمال فیلتر</button>
        </div>
        <div class="request-export-row"><span class="request-export-hint">خروجی چاپی و اکسل بر اساس فیلتر و سطح دسترسی شماست</span><div class="row"><a class="btn sm ghost" id="lvPrintLink" target="_blank" href="request_report.php?type=leave&mode=print">چاپ گزارش</a><a class="btn sm" id="lvExcelLink" href="request_report.php?type=leave&mode=excel">خروجی Excel</a></div></div>
      </div>
      <div class="request-summary" id="lvSummary"></div>
      <div class="leave-request-list" id="ltable"></div>
    </section>

    <?php if ($isAdmin): ?>
    <section class="sec settings-page" id="s-admins">
      <div class="head settings-page-head">
        <div>
          <h1>مدیران و تنظیمات</h1>
          <div class="muted">مدیریت دسترسی‌ها، زمان‌بندی درخواست‌ها و سیاست‌های منابع انسانی</div>
        </div>
        <span class="settings-page-mark"><span class="settings-page-mark-dot"></span> مرکز کنترل مدیریت</span>
      </div>

      <div class="settings-role-note">
        <span class="settings-note-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
        <div class="settings-note-copy">
          <span class="settings-eyebrow">سطح دسترسی و گردش تأیید</span>
          <h2>مدیر ارشد</h2>
          <p>نقش «مدیر ارشد» را از بخش حساب‌های مدیریت تعیین کنید. در درخواست‌هایی که سطح مدیر ارشد در مسیر تأیید آن‌ها فعال باشد، این نقش پس از مراحل قبلی بررسی نهایی مدیریتی را انجام می‌دهد.</p>
        </div>
        <span class="settings-note-tag">مرحله نهایی تأیید مدیریتی</span>
      </div>

      <div class="settings-panel settings-accounts-panel">
        <div class="settings-section-head">
          <span class="settings-section-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6"/></svg></span>
          <div><h2>حساب‌های مدیریت</h2><p>حساب‌ها و سطح دسترسی مدیران را از این قسمت مدیریت کنید.</p></div>
        </div>
        <div id="adminList"></div>

        <div class="settings-form-divider"></div>
        <div class="adm-form-head settings-account-form-head">
          <span class="ico"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M22 11h-6"/></svg></span>
          <div><b>ایجاد یا ویرایش حساب</b><small>اطلاعات ورود، نقش و کارمند مرتبط را مشخص کنید.</small></div>
        </div>
        <form id="adForm" class="stack settings-account-form" onsubmit="saveAdmin(event)">
          <input type="hidden" name="id" value="0">
          <div class="settings-form-grid">
            <label>نام کاربری<input name="username" class="ltr" required placeholder="مثلاً manager01" autocomplete="off"></label>
            <label>نام نمایشی<input name="name" required placeholder="نام و نام خانوادگی"></label>
            <label>نقش<select name="role"><option value="manager">مدیر مستقیم</option><option value="senior">مدیر ارشد (دسترسی کامل)</option><option value="admin">ادمین (دسترسی کامل)</option></select></label>
            <div class="settings-personnel-field">
              <span class="hint">کارمند مرتبط با این حساب (برای مدیر مستقیم یا مدیر ارشد)</span>
              <input type="hidden" name="personnel">
              <div class="settings-personnel-picker"><span id="adEmpName">هیچ کارمندی انتخاب نشده</span>
                <button class="btn sm ghost" type="button" onclick="pickAdminEmployee()">انتخاب کارمند</button>
                <button class="btn sm ghost" type="button" onclick="setAdminEmp('')">پاک کردن</button>
              </div>
            </div>
            <label class="settings-password-field">رمز عبور <small>(حداقل ۸ کاراکتر؛ برای ویرایش خالی بگذارید)</small><input type="password" name="password" autocomplete="new-password" placeholder="رمز عبور حساب"></label>
          </div>
          <div class="row settings-form-actions"><button class="btn" type="submit">ذخیره‌ی حساب</button><button class="btn ghost" type="button" onclick="resetAdminForm()">حساب جدید</button></div>
        </form>
      </div>

      <div class="settings-subhead">
        <div><span class="settings-eyebrow">کنترل زمان و شرایط ثبت</span><h2>بازه‌های زمانی</h2><p>تاریخ‌ها را با تقویم شمسی وارد کنید؛ بازه‌ها مستقل از یکدیگر ذخیره می‌شوند.</p></div>
        <span class="settings-subhead-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>
      </div>
      <div class="settings-window-grid">
        <article class="settings-window-card window-payslip">
          <div class="settings-window-head"><span class="settings-window-icon"><svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v6h5M8 13h8M8 17h8"/></svg></span><div><h3>مشاهده فیش حقوقی</h3><span>دسترسی کارکنان به فیش‌ها</span></div></div>
          <p>در صورت خالی بودن تاریخ‌ها، مشاهده‌ی فیش‌های ثبت‌شده همیشه آزاد است.</p>
          <form id="payWindowForm" class="stack settings-window-form" onsubmit="saveWindow(event,'payslip')">
            <div class="settings-date-grid"><label>شروع بازه<input name="start" class="ltr" data-jdate placeholder="1405/07/20"></label><label>پایان بازه<input name="end" class="ltr" data-jdate placeholder="1405/07/30"></label></div>
            <div class="settings-window-actions"><button class="btn" type="submit">ذخیره بازه</button><button class="btn ghost" type="button" onclick="clearWindow('payslip')">همیشه باز</button></div>
          </form>
          <div id="winPay" class="settings-window-status"></div>
        </article>

        <article class="settings-window-card window-advance">
          <div class="settings-window-head"><span class="settings-window-icon"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 6.5c0-1.7-2-3-5-3S7 5 7 7s1.8 3 5 4 5 2 5 4-2 4-5 4-5-1.3-5-3"/></svg></span><div><h3>ثبت درخواست مساعده</h3><span>مهلت ثبت مساعده کارکنان</span></div></div>
          <p>خارج از بازه‌ی تعیین‌شده، امکان ثبت درخواست مساعده وجود ندارد.</p>
          <form id="advanceWindowForm" class="stack settings-window-form" onsubmit="saveWindow(event,'advance')">
            <div class="settings-date-grid"><label>شروع بازه<input name="start" class="ltr" data-jdate placeholder="1405/07/20"></label><label>پایان بازه<input name="end" class="ltr" data-jdate placeholder="1405/07/30"></label></div>
            <div class="settings-window-actions"><button class="btn" type="submit">ذخیره بازه</button><button class="btn ghost" type="button" onclick="clearWindow('advance')">بستن بازه</button></div>
          </form>
          <div id="winAdv" class="settings-window-status"></div>
        </article>

        <article class="settings-window-card window-loan">
          <div class="settings-window-head"><span class="settings-window-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h4"/></svg></span><div><h3>ثبت درخواست وام</h3><span>مهلت ثبت درخواست وام</span></div></div>
          <p>زمان‌بندی وام مستقل است؛ شرایط استخدام و سقف آن نیز جداگانه تنظیم می‌شود.</p>
          <form id="loanWindowForm" class="stack settings-window-form" onsubmit="saveWindow(event,'loan')">
            <div class="settings-date-grid"><label>شروع بازه<input name="start" class="ltr" data-jdate placeholder="1405/07/20"></label><label>پایان بازه<input name="end" class="ltr" data-jdate placeholder="1405/07/30"></label></div>
            <div class="settings-window-actions"><button class="btn" type="submit">ذخیره بازه</button><button class="btn ghost" type="button" onclick="clearWindow('loan')">بستن بازه</button></div>
          </form>
          <div id="winLoan" class="settings-window-status"></div>
        </article>
      </div>

      <div class="settings-subhead settings-subhead-spaced">
        <div><span class="settings-eyebrow">سیاست‌های مالی کارکنان</span><h2>قوانین مساعده و وام</h2><p>این ضوابط برای همه‌ی کارکنان اعمال می‌شوند؛ تنظیمات هر خدمت مستقل است.</p></div>
        <span class="settings-subhead-icon rules-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16M4 12h16M4 19h16M8 3v4M16 10v4M10 17v4"/></svg></span>
      </div>
      <form class="stack settings-rules-form" id="rulesForm" onsubmit="saveRules(event)">
        <div class="settings-rule-grid">
          <section class="settings-rule-card rule-advance">
            <div class="settings-rule-head"><span class="settings-rule-icon">م</span><div><h3>قوانین مساعده</h3><p>سقف و شرایط دریافت مساعده</p></div></div>
            <label>حداقل سابقه از تاریخ استخدام <span class="settings-field-unit">ماه</span><input name="adv_wait_months" class="ltr" inputmode="numeric" required></label>
            <label>سقف مساعده از حقوق ماهانه <span class="settings-field-unit">درصد</span><input name="adv_ratio_pct" class="ltr" inputmode="decimal" required></label>
            <label>گرد کردن سقف به مضرب <span class="settings-field-unit">ریال</span><input name="adv_round" class="ltr" inputmode="numeric" required></label>
          </section>
          <section class="settings-rule-card rule-loan">
            <div class="settings-rule-head"><span class="settings-rule-icon">و</span><div><h3>قوانین وام</h3><p>سقف و شرایط دریافت وام</p></div></div>
            <label>حداقل سابقه از تاریخ استخدام <span class="settings-field-unit">ماه</span><input name="loan_wait_months" class="ltr" inputmode="numeric" required></label>
            <label>سقف وام از حقوق ماهانه <span class="settings-field-unit">درصد</span><input name="loan_ratio_pct" class="ltr" inputmode="decimal" required></label>
            <label>گرد کردن سقف به مضرب <span class="settings-field-unit">ریال</span><input name="loan_round" class="ltr" inputmode="numeric" required></label>
          </section>
        </div>
        <div class="settings-save-row"><span class="hint" id="rulesHint">تغییرات پس از ذخیره برای همه‌ی کارکنان اعمال می‌شود.</span><button class="btn" type="submit">ذخیره‌ی قوانین</button></div>
      </form>

      <div class="settings-subhead settings-subhead-spaced">
        <div><span class="settings-eyebrow">تنظیمات پس از ارتقای شغلی</span><h2>افزایش حقوق و تغییر شغل</h2><p>نحوه‌ی اعمال نتیجه‌ی ارتقا در اطلاعات حقوق و شغل کارکنان را انتخاب کنید.</p></div>
        <span class="settings-subhead-icon promo-icon"><svg viewBox="0 0 24 24"><path d="m3 17 6-6 4 3 8-9M15 5h6v6"/></svg></span>
      </div>
      <div class="settings-panel settings-promo-panel">
        <p class="settings-panel-intro">این انتخاب برای حقوق و شغل هر دو اعمال می‌شود. تغییر روش از بارگذاری بعدی فایل اکسل اثر می‌گذارد و فیش‌های دوره‌ی جاری را تغییر نمی‌دهد.</p>
        <form class="stack" id="promoModeForm" onsubmit="savePromoMode(event)">
          <div class="settings-promo-options">
            <label class="promo-choice"><input type="radio" name="mode" value="carry"><span class="promo-choice-copy"><b>الف) ثبت و نگهداری در سامانه</b><small>افزایش حقوق و شغل جدید در سامانه ذخیره می‌شود و در هر بارگذاری فایل اکسل دوباره اعمال خواهد شد. مبلغ افزایش به «مزد ثابت ماهانه» فیش اضافه می‌شود؛ بنابراین برای این موارد فایل اکسل را دستی تغییر ندهید.</small></span><span class="promo-choice-tag">پیشنهاد برای مدیریت متمرکز</span></label>
            <label class="promo-choice"><input type="radio" name="mode" value="excel"><span class="promo-choice-copy"><b>ب) دریافت اطلاعات فقط از فایل اکسل</b><small>سامانه تغییرات را نگه نمی‌دارد. منابع انسانی باید فایل ماه بعد را با حقوق و شغل جدید به‌روز کند. سقف مساعده و وام نیز با حقوق جدید محاسبه می‌شود.</small></span><span class="promo-choice-tag">نیازمند به‌روزرسانی فایل اکسل</span></label>
          </div>
          <div class="settings-save-row"><span class="hint">روش انتخاب‌شده برای بارگذاری‌های بعدی استفاده می‌شود.</span><button class="btn" type="submit">ذخیره‌ی روش</button></div>
        </form>
      </div>

      <div class="settings-subhead settings-subhead-spaced">
        <div><span class="settings-eyebrow">گردش کار و سطح مسئولیت</span><h2>روش تأیید درخواست‌های پرسنل</h2><p>مسیر تأیید هر نوع درخواست را جداگانه فعال یا غیرفعال کنید.</p></div>
        <span class="settings-subhead-icon approval-icon"><svg viewBox="0 0 24 24"><path d="M8 5h12M8 12h12M8 19h12M3 5h.01M3 12h.01M3 19h.01"/></svg></span>
      </div>
      <div class="settings-panel settings-approval-panel">
        <div class="settings-approval-guide"><b>ترتیب ثابت گردش درخواست</b><span>کارمند <i>←</i> مدیران فعال <i>←</i> منابع انسانی (ثبت نهایی)</span></div>
        <p class="settings-panel-intro">سوپروایزر، مدیران بالادستی و مدیر ارشد فقط در صورت فعال بودن در مسیر قرار می‌گیرند. منابع انسانی همیشه آخرین مرحله است. اگر همه‌ی سطوح مدیریتی خاموش باشند، درخواست مستقیم به منابع انسانی می‌رود. تغییرات فقط بر درخواست‌های جدید اثر دارد.</p>
        <form class="stack" id="approvalFlowForm" onsubmit="saveApprovalFlows(event)">
          <div id="approvalFlows" class="settings-approval-grid"></div>
          <div class="settings-save-row"><span class="hint">مسیر ثبت‌شده برای درخواست‌های قبلی تغییر نمی‌کند.</span><button class="btn" type="submit">ذخیره‌ی مسیر تأیید</button></div>
        </form>
      </div>
    </section>
    <?php endif; ?>
  </main>
</div>

<div class="modal-bg" id="modal"><div class="modal" id="modalBody"></div></div>

<script>window.API_BASE = '../'; window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;</script>
<script src="../assets/app.js"></script>
<script src="../assets/jdate.js"></script>
<script src="../assets/admin.js?v=16"></script>
<script src="../assets/promotions-cards.js?v=4"></script>
<script src="../assets/admin-ui.js?v=2"></script>
<script src="../assets/admins-ui.js?v=1"></script>
<script>window.NOTIFY_SCOPE = 'admin'; window.NOTIFY_ADMIN_ID = <?= (int)$admin['id'] ?>;</script>
<script src="../assets/notify.js?v=2"></script>
<script>
  /* هدر جدول‌ها را به سلول‌ها منتقل می‌کند تا در موبایل به شکل کارت با برچسب نمایش داده شوند */
  (function(){
    function enhance(t){
      const rows = [...t.querySelectorAll(':scope > tbody > tr, :scope > tr')];
      const head = rows.find(r => r.querySelector(':scope > th'));
      if (!head) return;
      head.classList.add('hd');
      const labels = [...head.children].map(th => th.textContent.trim());
      rows.forEach(r => {
        if (r === head) return;
        let i = 0;
        [...r.children].forEach(td => {
          if (td.hasAttribute('colspan')) { td.removeAttribute('data-label'); return; }
          const lab = labels[i] || '';
          if (td.getAttribute('data-label') !== lab) td.setAttribute('data-label', lab);
          i += 1;
        });
      });
    }
    function run(){ document.querySelectorAll('table.t').forEach(enhance); }
    new MutationObserver(run).observe(document.body, {childList:true, subtree:true});
    run();
  })();
  function toggleNav(open){
    document.body.classList.toggle('nav-open', open);
    document.getElementById('side').scrollTop = 0;
  }
  document.getElementById('menu').addEventListener('click', e => {
    if (e.target.closest('button[data-s]') && window.matchMedia('(max-width:900px)').matches) toggleNav(false);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') toggleNav(false); });
</script>
</body>
</html>

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
<title>تاریخچه اعلان‌ها | <?= e($appName) ?></title>
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="assets/experience-ui.css?v=3">
<style>
body{min-height:100vh;background:#171514;color:#f5eee9}
.history-wrap{max-width:900px;margin:auto;padding:26px 16px 56px}
.history-top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:20px}
.history-top h1{font-size:1.35rem;margin:0;color:#fff}
.history-top p{margin:6px 0 0;color:#b9aaa1;font-size:.9rem}
.history-back,.history-action{display:inline-flex;align-items:center;justify-content:center;border:1px solid #594438;border-radius:12px;padding:10px 15px;color:#f5eee9;text-decoration:none;background:#29231f;font:inherit;font-weight:700;cursor:pointer}
.history-list{display:grid;gap:12px}
.history-item{background:#211e1c;border:1px solid #49372e;border-radius:17px;padding:17px;display:grid;gap:10px}
.history-item.unread{border-color:#bd7849;box-shadow:inset -3px 0 #c47a45}
.history-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.history-title{font-weight:800;color:#fff}
.history-state,.history-date{font-size:.8rem;color:#bd9a82}
.history-body{white-space:pre-wrap;line-height:2;color:#d6c9c0;overflow-wrap:anywhere}
.history-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.history-action{padding:7px 12px;font-size:.82rem}
.history-empty{padding:34px 16px;text-align:center;color:#b9aaa1;background:#211e1c;border:1px dashed #594438;border-radius:17px}
.history-loading{padding:28px;text-align:center;color:#cbb9ac}
@media(max-width:600px){.history-wrap{padding:18px 12px 40px}.history-item{padding:14px}}
</style>
</head>
<body class="employee-app">
<main class="history-wrap">
  <header class="history-top">
    <div><h1>تاریخچه اعلان‌ها</h1><p>اعلان‌های دریافتی شما در ۳۰ روز اخیر</p></div>
    <a class="history-back" href="dashboard.php">بازگشت به پنل کارمند</a>
  </header>
  <div id="historyList" class="history-list"><div class="history-loading">در حال دریافت اعلان‌ها…</div></div>
</main>
<script>window.API_BASE = '';</script>
<script src="assets/app.js"></script>
<script>
const root=document.getElementById('historyList');
function escHistory(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function faDate(s){if(!s)return '';const d=new Date(String(s).replace(' ','T'));return Number.isNaN(d.getTime())?escHistory(s):new Intl.DateTimeFormat('fa-IR',{dateStyle:'medium',timeStyle:'short'}).format(d);}
async function loadHistory(){
  root.innerHTML='<div class="history-loading">در حال دریافت اعلان‌ها…</div>';
  const r=await api('employee','notif_history');
  if(!r||!r.ok){root.innerHTML='<div class="history-empty">دریافت تاریخچه ممکن نشد. صفحه را تازه کنید و دوباره تلاش کنید.</div>';return;}
  if(!r.items||!r.items.length){root.innerHTML='<div class="history-empty">در ۳۰ روز اخیر اعلانی برای شما ثبت نشده است.</div>';return;}
  root.innerHTML=r.items.map(n=>'<article class="history-item '+(!Number(n.shown)?'unread':'')+'"><div class="history-head"><div class="history-title">'+escHistory(n.title)+'</div><span class="history-state">'+(Number(n.shown)?'خوانده‌شده':'خوانده‌نشده')+'</span></div><div class="history-body">'+escHistory(n.body)+'</div><div class="history-foot"><span class="history-date">'+faDate(n.created_at)+'</span>'+(!Number(n.shown)?'<button class="history-action" type="button" data-read="'+Number(n.id)+'">علامت‌گذاری به‌عنوان خوانده‌شده</button>':'')+'</div></article>').join('');
}
root.addEventListener('click',async e=>{
  const b=e.target.closest('[data-read]');if(!b)return;
  b.disabled=true;
  const r=await api('employee','notif_ack',{ids:[Number(b.dataset.read)]});
  if(r&&r.ok)loadHistory();else{b.disabled=false;toast((r&&r.message)||'ثبت وضعیت خواندن انجام نشد.','bad');}
});
loadHistory();
</script>
</body>
</html>

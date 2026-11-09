<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
$admin=require_admin_page();
if (!in_array($admin['role'],['admin','senior'],true)) { http_response_code(403); exit('دسترسی غیرمجاز'); }
?>
<!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>اطلاعیه‌ها و اعلان‌ها | <?= e(cfg('app_name')) ?></title>
<link rel="stylesheet" href="../assets/app.css"><link rel="stylesheet" href="../assets/experience-ui.css?v=4">
<style>
body{min-height:100vh;background:#171514;color:#f5eee9}.ann-wrap{max-width:1180px;margin:auto;padding:28px 18px 60px}
.ann-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap}
.ann-top h1{font-size:1.45rem;color:#fff;margin:0}.ann-top p{color:#b9aaa1;margin:6px 0 0}
.ann-card{background:#211e1c;border:1px solid #49372e;border-radius:22px;padding:22px;box-shadow:0 18px 48px -32px #000;margin-bottom:18px}
.ann-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.ann-field{display:grid;gap:7px}.ann-field.full{grid-column:1/-1}.ann-field label{color:#d7c5b8;font-weight:700}
.ann-field input,.ann-field select,.ann-field textarea{width:100%;background:#171514;color:#fff;border:1px solid #594438;border-radius:13px;padding:12px;min-height:46px}
.ann-field textarea{min-height:150px;resize:vertical;line-height:2}
.ann-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}
.ann-btn{border:0;border-radius:13px;padding:12px 20px;background:linear-gradient(135deg,#c47a45,#a64b35);color:white;font:inherit;font-weight:800;cursor:pointer}
.ann-btn:disabled{opacity:.55;cursor:wait}.ann-btn.alt{background:#39312c;border:1px solid #604b3e}
.ann-help{color:#c6b4a7;font-size:.84rem;line-height:1.9}.ann-status{margin-top:12px;min-height:24px;color:#e7b58c}
.ann-list{display:grid;gap:12px}.ann-item{border:1px solid #49372e;background:#211e1c;border-radius:16px;padding:16px;display:grid;gap:8px}
.ann-item header{display:flex;justify-content:space-between;gap:12px;align-items:center}.ann-item h3{color:#fff;margin:0}.ann-item p{white-space:pre-wrap;color:#c9bbb2;margin:0;line-height:1.9}
.ann-meta{color:#bd8c6a;font-size:.8rem}.ann-empty{color:#b9aaa1;text-align:center;padding:24px}
@media(max-width:650px){.ann-grid{grid-template-columns:1fr}.ann-field.full{grid-column:auto}.ann-card{padding:16px}.ann-wrap{padding:18px 12px 40px}}
</style></head><body>
<main class="ann-wrap">
<header class="ann-top"><div><h1>مرکز اطلاعیه‌ها و اعلان‌ها</h1><p>ارسال پیام به کارکنان، شعبه‌ها یا سکشن‌های انتخاب‌شده</p></div>
<a class="ann-btn alt" href="panel.php">بازگشت به پنل</a></header>
<section class="ann-card">
<form id="annForm"><div class="ann-grid">
<div class="ann-field"><label for="annTitle">عنوان اطلاعیه</label><input id="annTitle" name="title" maxlength="190" required placeholder="مثلاً انتشار فیش حقوقی"></div>
<div class="ann-field"><label for="annKind">نوع اطلاعیه</label><select id="annKind" name="kind"><option value="general">اطلاع‌رسانی عمومی</option><option value="urgent">فوری</option><option value="reminder">یادآوری</option><option value="news">خبر و اطلاعیه</option></select></div>
<div class="ann-field"><label for="annAudience">مخاطبان</label><select id="annAudience" name="audience"><option value="all">تمام کارکنان فعال</option><option value="section">یک سکشن</option><option value="branch">یک شعبه</option><option value="selected">کارکنان انتخاب‌شده</option></select></div>
<div class="ann-field" id="valueWrap" hidden><label id="valueLabel" for="annValue">مقدار مخاطب</label><input id="annValue" name="audience_value" placeholder="نام دقیق سکشن یا شعبه"></div>
<div class="ann-field full"><label for="annBody">متن اطلاعیه</label><textarea id="annBody" name="body" maxlength="5000" required placeholder="متن اطلاعیه را بنویسید..."></textarea></div>
</div>
<p class="ann-help">در این نسخه پیام در صندوق اعلان داخل سامانه ثبت می‌شود. برای نمایش اعلان در گوشی هنگام بسته بودن برنامه، پس از آماده‌سازی اتصال Firebase باید تنظیمات اختصاصی پروژه تکمیل شود.</p>
<div class="ann-actions"><button class="ann-btn" id="sendBtn" type="submit">ثبت و ارسال اطلاعیه</button><span class="ann-status" id="status" role="status"></span></div></form>
</section>
<section class="ann-card"><h2>سوابق اطلاعیه‌ها</h2><div id="annList" class="ann-list"><div class="ann-empty">در حال دریافت...</div></div></section>
</main>
<script>
const csrf=document.querySelector('meta[name="csrf"]').content;
const api=async(action,data={})=>{const r=await fetch('../api/announcements.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,...data})});return r.json()};
const audience=document.getElementById('annAudience'),wrap=document.getElementById('valueWrap'),valueLabel=document.getElementById('valueLabel');
audience.addEventListener('change',()=>{wrap.hidden=!['section','branch'].includes(audience.value);valueLabel.textContent=audience.value==='section'?'نام سکشن':'نام شعبه';});
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function load(){const r=await api('list');const root=document.getElementById('annList');if(!r.ok){root.innerHTML='<div class="ann-empty">دریافت سوابق ناموفق بود.</div>';return;}if(!r.announcements.length){root.innerHTML='<div class="ann-empty">هنوز اطلاعیه‌ای ثبت نشده است.</div>';return;}root.innerHTML=r.announcements.map(x=>'<article class="ann-item"><header><h3>'+esc(x.title)+'</h3><span class="ann-meta">'+(Number(x.active)?'فعال':'غیرفعال')+' · '+esc(x.recipient_count)+' نفر</span></header><p>'+esc(x.body)+'</p><div class="ann-meta">'+esc(x.created_at)+' · '+esc(x.audience)+'</div>'+(Number(x.active)?'<button class="ann-btn alt" type="button" data-off="'+Number(x.id)+'">غیرفعال‌سازی</button>':'')+'</article>').join('');}
document.getElementById('annList').addEventListener('click',async e=>{const b=e.target.closest('[data-off]');if(!b)return;if(!confirm('این اطلاعیه غیرفعال شود؟'))return;const r=await api('deactivate',{id:Number(b.dataset.off)});document.getElementById('status').textContent=r.message||'';load();});
document.getElementById('annForm').addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,btn=document.getElementById('sendBtn'),status=document.getElementById('status');btn.disabled=true;status.textContent='در حال ثبت...';const data=Object.fromEntries(new FormData(f).entries());try{const r=await api('create',data);status.textContent=r.message||'درخواست انجام نشد.';if(r.ok){f.reset();wrap.hidden=true;load();}}catch(_){status.textContent='ارتباط با سرور برقرار نشد.';}finally{btn.disabled=false;}});
load();
</script></body></html>

<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
start_session_secure();
if (emp_session()) {
    header('Location: dashboard.php');
    exit;
}
$appName = cfg('app_name');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>ورود کارکنان | <?= e($appName) ?></title>
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="assets/experience-ui.css?v=1">
</head>
<body class="login-page">
<div class="auth-page">
  <div class="auth-wrap">
    <aside class="auth-aside">
      <div class="aside-logo"><div class="tilt"><svg class="logo-svg" viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="lgTileAside" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1d4e89"/><stop offset="1" stop-color="#0b2343"/></linearGradient><radialGradient id="lgCoinAside" cx="35%" cy="30%" r="75%"><stop offset="0" stop-color="#fff4c7"/><stop offset=".45" stop-color="#ffd166"/><stop offset=".8" stop-color="#e8a317"/><stop offset="1" stop-color="#b87b0a"/></radialGradient></defs><rect x="2" y="2" width="60" height="60" rx="16" fill="url(#lgTileAside)"/><rect x="2.6" y="2.6" width="58.8" height="58.8" rx="15.4" fill="none" stroke="#ffffff" stroke-opacity=".18"/><ellipse cx="32" cy="52" rx="19" ry="4" fill="#000" opacity=".35"/><path d="M14 46 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="46" rx="18" ry="7" fill="url(#lgCoinAside)"/><ellipse cx="32" cy="46" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 40 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="40" rx="18" ry="7" fill="url(#lgCoinAside)"/><ellipse cx="32" cy="40" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 34 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="34" rx="18" ry="7" fill="url(#lgCoinAside)"/><ellipse cx="32" cy="34" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M20 31 q4 -5 10 -5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".75"/></svg></div></div>
      <h2><?= e($appName) ?></h2>
      <p>سامانه‌ی خدمات کارکنان؛ همه‌ی اطلاعات حقوقی خود را در یک جا ببینید.</p>
      <ul class="aside-list">
        <li>مشاهده‌ی فیش حقوقی ماهانه</li>
        <li>ثبت و پیگیری درخواست مساعده</li>
        <li>ثبت و پیگیری درخواست مرخصی</li>
      </ul>
    </aside>
    <div class="auth-box">
    <div class="auth-head">
      <div class="logo"><svg class="logo-svg" viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="lgTile" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1d4e89"/><stop offset="1" stop-color="#0b2343"/></linearGradient><radialGradient id="lgCoin" cx="35%" cy="30%" r="75%"><stop offset="0" stop-color="#fff4c7"/><stop offset=".45" stop-color="#ffd166"/><stop offset=".8" stop-color="#e8a317"/><stop offset="1" stop-color="#b87b0a"/></radialGradient></defs><rect x="2" y="2" width="60" height="60" rx="16" fill="url(#lgTile)"/><rect x="2.6" y="2.6" width="58.8" height="58.8" rx="15.4" fill="none" stroke="#ffffff" stroke-opacity=".18"/><ellipse cx="32" cy="52" rx="19" ry="4" fill="#000" opacity=".35"/><path d="M14 46 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="46" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="46" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 40 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="40" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="40" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M14 34 v5 a18 7 0 0 0 36 0 v-5 z" fill="#8a5a07"/><ellipse cx="32" cy="34" rx="18" ry="7" fill="url(#lgCoin)"/><ellipse cx="32" cy="34" rx="13.5" ry="4.8" fill="none" stroke="#b87b0a" stroke-width="1" opacity=".55"/><path d="M20 31 q4 -5 10 -5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".75"/></svg></div>
      <h1><?= e($appName) ?></h1>
      <p>برای مشاهده‌ی فیش حقوقی، مساعده و مرخصی وارد شوید</p>
    </div>
    <div class="auth-body">
      <h2 class="desk-title">ورود کارکنان</h2>
      <div id="msg"></div>
      <form class="stack" id="loginForm">
        <label class="f3d">
          <span class="f3d-title"><span class="f3d-ic">۱</span>کد ملی</span>
          <span class="otp" data-cells="10">
            <input name="national" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="کد ملی" required>
          </span>
          <span class="hint">اگر کد ملی ندارید، عدد <b class="ltr">0000000000</b> را وارد کنید.</span>
        </label>
        <label class="f3d">
          <span class="f3d-title"><span class="f3d-ic">۲</span>کد پرسنلی</span>
          <span class="otp" data-cells="9" data-grow="1" data-max="11">
            <input name="personnel" inputmode="numeric" maxlength="11" autocomplete="off" aria-label="کد پرسنلی" required>
          </span>
        </label>
        <label class="f3d" id="phoneLabel" style="display:none">
          <span class="f3d-title"><span class="f3d-ic">۳</span>شماره موبایل ثبت‌شده</span>
          <span class="otp" data-cells="11">
            <input name="phone" inputmode="tel" maxlength="11" autocomplete="tel" aria-label="شماره موبایل">
          </span>
        </label>
        <button class="btn" id="btn" type="submit">ورود</button>
      </form>
    </div>
    </div>
  </div>
</div>
<script>window.API_BASE = '';</script>
<script src="assets/app.js"></script>
<script>
(function () {
  /* کج‌شدن آیکون سکه با حرکت ماوس (فقط دسکتاپ و بدون حرکت‌کاهش‌یافته) */
  const panel = document.querySelector('.auth-aside');
  const tilt = document.querySelector('.tilt');
  if (!panel || !tilt || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  panel.addEventListener('mousemove', (e) => {
    const r = panel.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5;
    const y = (e.clientY - r.top) / r.height - 0.5;
    tilt.style.transform = `rotateY(${x * 26}deg) rotateX(${-y * 20}deg)`;
  });
  panel.addEventListener('mouseleave', () => { tilt.style.transform = ''; });
})();
</script>
<script>
(function () {
  const form = document.getElementById('loginForm');
  const phone = document.getElementById('phoneLabel');
  const msg = document.getElementById('msg');
  const showPhone = (on) => { phone.style.display = on ? '' : 'none'; phone.querySelector('input').required = on; };

  /* ارقام فارسی و عربی را به انگلیسی تبدیل و بقیه‌ی کاراکترها را حذف می‌کند */
  const toDigits = (s) => s
    .replace(/[۰-۹]/g, (c) => String(c.charCodeAt(0) - 0x06F0))
    .replace(/[٠-٩]/g, (c) => String(c.charCodeAt(0) - 0x0660))
    .replace(/\D/g, '');

  /* هر فیلد یک ردیف خانه‌ی جداگانه دارد. ورودی واقعی روی خانه‌ها شفاف قرار دارد،
     بنابراین کلیک، تایپ، پیست و کیبورد عددی موبایل مثل قبل کار می‌کنند. */
  document.querySelectorAll('.otp').forEach((wrap) => {
    const input = wrap.querySelector('input');
    const fixed = Number(wrap.dataset.cells) || 0;
    const grow = wrap.dataset.grow === '1';
    const max = Number(wrap.dataset.max) || fixed;
    let focused = false;

    const render = () => {
      const v = input.value;
      const count = grow ? Math.min(max, Math.max(fixed, v.length + (v.length < fixed ? 1 : 0))) : fixed;
      wrap.querySelectorAll('.otp-cell').forEach((c) => c.remove());
      const active = Math.min(v.length, count - 1);
      for (let i = 0; i < count; i++) {
        const cell = document.createElement('span');
        cell.className = 'otp-cell' + (focused && i === active ? ' on' : '');
        cell.textContent = v[i] || '';
        wrap.insertBefore(cell, input);
      }
    };

    input.addEventListener('input', (ev) => {
      const clean = toDigits(input.value);
      if (clean !== input.value) input.value = clean;
      render();
      /* با تکمیل هر فیلد، فوکوس به فیلد بعدی یا دکمه‌ی ورود می‌رود */
      const targetLength = input.name === 'personnel' ? max : fixed;
      if (ev.inputType && ev.inputType.startsWith('insert') && fixed && input.value.length === targetLength) {
        const btn = document.getElementById('btn');
        if (input.name === 'national') form.personnel.focus();
        // طول کد پرسنلی می‌تواند ۹ یا ۱۱ رقم باشد (N شش/هشت‌رقمی + F سه‌رقمی).
        // برای جلوگیری از قطع کد ۱۱ رقمی، در ۹ رقم فوکوس خودکار از فیلد خارج نمی‌شود.
        else if (input.name === 'personnel') {
          if (input.value.length === max) (phone.style.display === 'none' ? btn : form.phone).focus();
        } else btn.focus();
      }
    });
    input.addEventListener('focus', () => { focused = true; render(); });
    input.addEventListener('blur', () => { focused = false; render(); });
    render();
  });

  form.national.addEventListener('input', () => {
    showPhone(form.national.value === '0000000000');
  });
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = document.getElementById('btn');
    msg.innerHTML = '';
    setBusy(btn, true);
    const r = await api('employee', 'login', {
      national: form.national.value, personnel: form.personnel.value, phone: form.phone.value,
    });
    setBusy(btn, false, 'ورود');
    if (r.ok) { location.href = 'dashboard.php'; return; }
    if (r.need_phone) showPhone(true);
    msg.innerHTML = `<div class="alert">● ${esc(r.message)}</div>`;
  });
})();
</script>
</body>
</html>

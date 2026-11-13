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
<link rel="stylesheet" href="assets/login-logo.css?v=2">
</head>
<body class="login-page">
<div class="auth-page">
  <div class="auth-wrap">
    <aside class="auth-aside">
      <div class="lg-stage lg-aside"><div class="tilt"><img class="login-logo" src="assets/logo-login.png" alt="EL ZOCO"></div></div>
      <h2><?= e($appName) ?></h2>
     
     
    </aside>
    <div class="auth-box">
    <div class="auth-head">
      <div class="lg-stage lg-head"><div class="tilt"><img class="login-logo" src="assets/logo-login.png" alt="EL ZOCO"></div></div>
      <h1><?= e($appName) ?></h1>
    </div>
    <div class="auth-body">
      <h2 class="desk-title">ورود کارکنان</h2>
      <div id="msg"></div>
      <form class="stack" id="loginForm">
        <label class="f3d">
          <span class="f3d-title">کد ملی</span>
          <span class="otp" data-cells="10">
            <input name="national" inputmode="numeric" maxlength="10" autocomplete="off" aria-label="کد ملی" required>
          </span>
          <span class="hint">اگر کد ملی ندارید، عدد <b class="ltr">0000000000</b> را وارد کنید.</span>
        </label>
        <label class="f3d">
          <span class="f3d-title">کد پرسنلی</span>
          <span class="otp" data-cells="9" data-grow="1" data-max="11">
            <input name="personnel" inputmode="numeric" maxlength="11" autocomplete="off" aria-label="کد پرسنلی" required>
          </span>
        </label>
        <label class="f3d" id="phoneLabel" style="display:none">
          <span class="f3d-title">شماره موبایل ثبت‌شده</span>
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
  /* کج‌شدن لوگو با حرکت ماوس (فقط ماوس و بدون حرکت‌کاهش‌یافته) — هم در پنل دسکتاپ هم در سربرگ موبایل */
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('.lg-stage').forEach((stage) => {
    const tilt = stage.querySelector('.tilt');
    const area = stage.closest('.auth-aside, .auth-head');
    if (!tilt || !area) return;
    area.addEventListener('pointermove', (e) => {
      if (e.pointerType !== 'mouse') return;
      const r = area.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      tilt.style.transform = `rotateY(${x * 26}deg) rotateX(${-y * 20}deg)`;
    });
    area.addEventListener('pointerleave', () => { tilt.style.transform = ''; });
  });
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

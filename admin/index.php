<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
start_session_secure();
if (admin_session()) {
    header('Location: panel.php');
    exit;
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>ورود مدیران | <?= e(cfg('app_name')) ?></title>
<link rel="stylesheet" href="../assets/app.css">
<link rel="stylesheet" href="../assets/login-admin.css?v=2">
<link rel="stylesheet" href="../assets/brand-logo.css?v=1">
<link rel="stylesheet" href="../assets/experience-ui.css?v=1">
</head>
<body class="lg">
<div class="lg-bg" aria-hidden="true">
  <span class="lg-orb a"></span><span class="lg-orb b"></span><span class="lg-orb c"></span><span class="lg-grid"></span>
</div>

<main class="lg-card" id="card">
  <div class="lg-brand">
    <div class="lg-coin" aria-hidden="true"><img class="brand-logo" src="../assets/logo-login.png" alt="EL ZOCO"></div>
    <h1>پنل مدیریت</h1>
    <p>ورود ادمین و مدیران سامانه‌ی حقوق</p>
    <span class="lg-chip"><i></i>اتصال امن</span>
  </div>

  <div id="msg" role="alert" aria-live="assertive"></div>

  <form id="f" novalidate autocomplete="on">
    <div class="lg-field" id="fu">
      <input id="inU" name="username" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder=" " required>
      <label for="inU">نام کاربری</label>
      <span class="lg-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span>
    </div>

    <div class="lg-field is-pass" id="fp">
      <input id="inP" type="password" name="password" autocomplete="current-password" placeholder=" " required>
      <label for="inP">رمز عبور</label>
      <span class="lg-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
      <button class="lg-eye" type="button" id="eye" aria-label="نمایش رمز عبور" aria-pressed="false">
        <svg viewBox="0 0 24 24" id="eyeIco"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>

    <button class="lg-submit" id="btn" type="submit">
      <span class="lg-txt">ورود به پنل</span>
      <span class="arr" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></span>
    </button>
  </form>

  <div class="lg-foot">
    <svg viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    دسترسی فقط برای مدیران مجاز است
  </div>
</main>

<script>window.API_BASE = '../';</script>
<script src="../assets/app.js"></script>
<script>
(function () {
  const card = document.getElementById('card');
  const form = document.getElementById('f');
  const btn = document.getElementById('btn');
  const msg = document.getElementById('msg');
  const inP = document.getElementById('inP');
  const eye = document.getElementById('eye');
  const eyeIco = document.getElementById('eyeIco');
  const SHOW = '<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.1A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.3 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7a9.5 9.5 0 0 0 5.4-1.7"/>';
  const HIDE = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>';

  eye.addEventListener('click', () => {
    const show = inP.type === 'password';
    inP.type = show ? 'text' : 'password';
    eye.setAttribute('aria-pressed', String(show));
    eye.setAttribute('aria-label', show ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
    eyeIco.innerHTML = show ? SHOW : HIDE;
  });

  function fail(text) {
    msg.innerHTML = `<div class="alert">${esc(text)}</div>`;
    ['fu', 'fp'].forEach(id => document.getElementById(id).classList.add('has-err'));
    card.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(-4px)' }, { transform: 'translateX(0)' }], { duration: 450, easing: 'ease-out' });
  }

  ['fu', 'fp'].forEach(id => {
    const box = document.getElementById(id);
    const input = box.querySelector('input');
    const sync = () => box.classList.toggle('filled', input.value.length > 0);
    input.addEventListener('input', () => {
      box.classList.remove('has-err');
      sync();
      box.classList.remove('typed');
      void box.offsetWidth;
      box.classList.add('typed');
    });
    input.addEventListener('blur', sync);
    sync();
  });

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const u = form.username.value.trim();
    const p = form.password.value;
    if (!u || !p) {
      if (!u) document.getElementById('fu').classList.add('has-err');
      if (!p) document.getElementById('fp').classList.add('has-err');
      return fail('نام کاربری و رمز عبور را وارد کنید.');
    }
    msg.innerHTML = '';
    setBusy(btn, true);
    const r = await api('admin', 'login', { username: u, password: p });
    setBusy(btn, false);
    if (r.ok) {
      card.classList.add('is-done');
      setTimeout(() => { location.href = 'panel.php'; }, 480);
      return;
    }
    fail(r.message || 'ورود ناموفق بود.');
  });

  document.getElementById('inU').focus();
})();
</script>
</body>
</html>

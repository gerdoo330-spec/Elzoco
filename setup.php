<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
start_session_secure();

$lock = __DIR__ . '/storage/installed.lock';
if (file_exists($lock)) {
    http_response_code(403);
    exit('نصب قبلاً انجام شده است. برای امنیت، فایل setup.php را از سرور حذف کنید.');
}

$error = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        if ($user === '' || strlen($pass) < 8 || $name === '') {
            throw new RuntimeException('نام کاربری، نام و رمز (حداقل ۸ کاراکتر) را کامل کنید.');
        }
        $sql = file_get_contents(__DIR__ . '/db/schema.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            db()->exec($stmt);
        }
        q('INSERT INTO admins (username, password_hash, name, role, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())
           ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)',
            [$user, password_hash($pass, PASSWORD_DEFAULT), $name, 'admin']);
        file_put_contents($lock, date('c'));
        $done = true;
    } catch (Throwable $ex) {
        $error = 'خطا در نصب: ' . $ex->getMessage();
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب سامانه</title>
<link rel="stylesheet" href="assets/app.css">
<link rel="stylesheet" href="assets/experience-ui.css?v=1">
</head>
<body class="center-page">
<div class="card login-card">
  <h1>نصب اولیه</h1>
  <?php if ($done): ?>
    <p class="ok">نصب انجام شد. فایل <b>setup.php</b> را از سرور حذف کنید.</p>
    <a class="btn" href="admin/">ورود به پنل مدیریت</a>
  <?php else: ?>
    <?php if ($error): ?><div class="alert">● <?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <label>نام کاربری ادمین<input name="username" required></label>
      <label>نام نمایشی<input name="name" required></label>
      <label>رمز عبور (حداقل ۸ کاراکتر)<input type="password" name="password" minlength="8" required></label>
      <button class="btn" type="submit">ایجاد جداول و ادمین</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/config.php';

if (is_setup_done()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $expiry = trim($_POST['expiry_days'] ?? '30');

    if ($user === '' || $pass === '') {
        $error = 'Username and password are required.';
    } elseif (strlen($user) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (!ctype_digit($expiry)) {
        $error = 'Expiry must be a non-negative integer (0 = never).';
    } else {
        $days = (int)$expiry;
        if ($days < 0 || $days > 3650) $error = 'Expiry must be between 0 and 3650 days.';
        else {
            // init db already done via config, ensure tables
            db(); // trigger creation
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            set_setting('admin_user', $user);
            set_setting('admin_hash', $hash);
            set_setting('expiry_days', (string)$days);
            // auto-login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user;
            header('Location: admin.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>n0c0.de-sht — Setup</title>
<link rel="stylesheet" href="n0c0de-classless.css">
<style>main{max-width:32rem}</style>
</head>
<body>
<header>
<h1>n0c0.de-sht // setup</h1>
<p>First run — create admin account. This will only be shown once.</p>
</header>
<main>
<?php if ($error): ?><blockquote style="border-color:#ff0040;color:#ff6b6b"><strong>Error:</strong> <?=h($error)?></blockquote><?php endif; ?>

<form method="post" autocomplete="off">
  <p>
    <label for="username">Admin username</label><br>
    <input id="username" name="username" type="text" required minlength="3" maxlength="64" value="<?=h($_POST['username'] ?? '')?>" placeholder="admin">
  </p>
  <p>
    <label for="password">Admin password (min 8 chars)</label><br>
    <input id="password" name="password" type="password" required minlength="8" placeholder="••••••••">
  </p>
  <p>
    <label for="password2">Confirm password</label><br>
    <input id="password2" name="password2" type="password" required minlength="8" placeholder="••••••••">
  </p>
  <p>
    <label for="expiry_days">Default link expiry (days, 0 = never expire)</label><br>
    <input id="expiry_days" name="expiry_days" type="number" min="0" max="3650" step="1" required value="<?=h($_POST['expiry_days'] ?? '30')?>">
    <small>Admin can change this later. 0 means links never expire.</small>
  </p>
  <p><button type="submit">Create admin &amp; finish setup</button></p>
</form>

<hr>
<p><small>DB file <code>links.db</code> will be created in the same directory. Make sure PHP can write to this folder.</small></p>
</main>
<footer><p><small>n0c0.de-sht — Minimal url shorter. — <a href="index.php">back to home</a></small></p></footer>
</body>
</html>

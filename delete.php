<?php
require_once __DIR__ . '/config.php';
if (!is_setup_done()) { header('Location: setup.php'); exit; }

$error=''; $success='';
$prefillCode = trim($_GET['code'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $error='Invalid CSRF token. Reload page.';
    else {
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!preg_match('/^[A-Za-z0-9]{6}$/', $code)) $error='Code must be exactly 6 alphanumeric characters.';
        elseif ($password==='') $error='Password is required.';
        else {
            $st = db()->prepare("SELECT * FROM links WHERE code=?");
            $st->execute([$code]);
            $link = $st->fetch();
            if (!$link) $error='Link not found.';
            elseif (!password_verify($password, $link['delete_token_hash'])) $error='Wrong password.';
            else {
                db()->prepare("DELETE FROM links WHERE code=?")->execute([$code]);
                $success = "Link <code>".h($code)."</code> has been deleted.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>n0c0.de-sht — delete link</title>
<link rel="stylesheet" href="n0c0de-classless.css">
</head>
<body>
<header>
<h1>n0c0.de-sht // delete</h1>
<nav><small><a href="index.php">home</a> · <a href="delete.php">delete</a> · <a href="admin.php">admin</a></small></nav>
</header>
<main>
<p>Enter the 6-character code and the delete password you received when shortening.</p>

<?php if ($error): ?><blockquote style="border-color:#ff0040;color:#ff6b6b"><strong>Error:</strong> <?=($error)?></blockquote><?php endif; ?>
<?php if ($success): ?><blockquote style="border-color:#00ff88;color:#00ff88"><strong>Success:</strong> <?=$success?></blockquote><?php endif; ?>

<form method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <p>
    <label for="code">Short code (6 chars)</label><br>
    <input id="code" name="code" type="text" required pattern="[A-Za-z0-9]{6}" maxlength="6" minlength="6" placeholder="aB3x9Q" value="<?=h($_POST['code'] ?? $prefillCode)?>" style="font-family:monospace; letter-spacing:0.15em">
  </p>
  <p>
    <label for="password">Delete password</label><br>
    <input id="password" name="password" type="text" required placeholder="your delete password">
    <small>Case-sensitive.</small>
  </p>
  <p><button type="submit">Delete link</button></p>
</form>

<?php if ($success): ?><p><a href="index.php">← back to home</a></p><?php endif; ?>

<hr>
<p><small>Admin can delete any link without a password at <a href="admin.php">admin panel</a>.</small></p>
</main>
<footer><hr><p><small>n0c0.de-sht</small></p></footer>
</body>
</html>

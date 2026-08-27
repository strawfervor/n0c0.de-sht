<?php
require_once __DIR__ . '/config.php';
if (!is_setup_done()) { header('Location: setup.php'); exit; }

$error=''; $info='';

// handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

// handle login
if (!is_admin() && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['login'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) $error='Invalid CSRF token.';
    else {
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $expUser = get_setting('admin_user');
        $expHash = get_setting('admin_hash');
        if ($u === $expUser && $expHash && password_verify($p, $expHash)) {
            // rehash if needed
            if (password_needs_rehash($expHash, PASSWORD_DEFAULT)) {
                set_setting('admin_hash', password_hash($p, PASSWORD_DEFAULT));
            }
            $_SESSION['admin_logged_in']=true;
            $_SESSION['admin_user']=$u;
            session_regenerate_id(true);
            header('Location: admin.php');
            exit;
        } else $error='Invalid username or password.';
    }
}

// if admin, handle actions
if (is_admin()) {
    cleanup_expired();
    // update expiry
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_expiry'])) {
        if (!csrf_check($_POST['csrf'] ?? null)) $error='Invalid CSRF token.';
        else {
            $v = trim($_POST['expiry_days'] ?? '');
            if (!ctype_digit($v)) $error='Expiry must be 0-3650.';
            else {
                $n=(int)$v;
                if($n<0||$n>3650) $error='Expiry must be between 0 and 3650.';
                else { set_setting('expiry_days',(string)$n); $info='Expiry updated to '.h((string)$n).' day(s).'.($n===0?' (never expire)':''); }
            }
        }
    }
    // delete link as admin
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_code'])) {
        if (!csrf_check($_POST['csrf'] ?? null)) $error='Invalid CSRF token.';
        else {
            $c = trim($_POST['delete_code'] ?? '');
            if (!preg_match('/^[A-Za-z0-9]{6}$/',$c)) $error='Invalid code.';
            else {
                $st=db()->prepare("DELETE FROM links WHERE code=?");
                $st->execute([$c]);
                if($st->rowCount()) $info="Link <code>".h($c)."</code> deleted.";
                else $error='Link not found.';
            }
        }
    }
    // change password
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_pw'])) {
        if (!csrf_check($_POST['csrf'] ?? null)) $error='Invalid CSRF token.';
        else {
            $cur = $_POST['current_password'] ?? '';
            $nw = $_POST['new_password'] ?? '';
            $nw2 = $_POST['new_password2'] ?? '';
            $hash = get_setting('admin_hash');
            if (!$hash || !password_verify($cur,$hash)) $error='Current password is wrong.';
            elseif (strlen($nw)<8) $error='New password must be at least 8 chars.';
            elseif ($nw!==$nw2) $error='New passwords do not match.';
            else { set_setting('admin_hash', password_hash($nw,PASSWORD_DEFAULT)); $info='Password changed.'; }
        }
    }
}

$expiry = get_setting('expiry_days','30');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>n0c0.de-sht — admin</title>
<link rel="stylesheet" href="n0c0de-classless.css">
<style>table{width:100%;font-size:0.85rem;word-break:break-all}th,td{padding:0.4rem 0.5rem;text-align:left;border:1px solid #222}th{background:#0a0a0a;color:#00ff88}</style>
</head>
<body>
<header>
<h1>n0c0.de-sht // admin</h1>
<nav><small><a href="index.php">home</a> · <a href="delete.php">delete</a> · <a href="admin.php">admin</a><?php if(is_admin()):?> · <a href="admin.php?logout=1">logout (<?=h($_SESSION['admin_user']??'')?>)</a><?php endif;?></small></nav>
</header>
<main>

<?php if($error):?><blockquote style="border-color:#ff0040;color:#ff6b6b"><strong>Error:</strong> <?=$error?></blockquote><?php endif;?>
<?php if($info):?><blockquote style="border-color:#00ff88;color:#00ff88"><?=$info?></blockquote><?php endif;?>

<?php if(!is_admin()):?>
  <h2>Admin login</h2>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="login" value="1">
    <p><label>Username<br><input name="username" type="text" required value="<?=h($_POST['username']??'')?>"></label></p>
    <p><label>Password<br><input name="password" type="password" required></label></p>
    <p><button type="submit">Login</button></p>
  </form>
  <p><small>First run? Go to <a href="setup.php">setup.php</a>.</small></p>
<?php else:?>
  <h2>Settings</h2>
  <form method="post" autocomplete="off" style="border:1px solid #222;padding:1rem;background:#080808">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <p>
      <label>Default expiry (days, 0 = never)<br>
      <input name="expiry_days" type="number" min="0" max="3650" required value="<?=h($expiry)?>" style="max-width:10rem"></label>
      <button type="submit" name="save_expiry" value="1" style="margin-left:0.5rem">Save</button>
    </p>
    <p><small>Applies to newly created links. Existing links keep their original expiry.</small></p>
  </form>

  <details style="margin-top:1.5rem;border:1px solid #222;padding:0.8rem;background:#080808">
    <summary style="cursor:pointer;color:#ff0080">Change admin password</summary>
    <form method="post" autocomplete="off" style="margin-top:1rem">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <p><label>Current password<br><input name="current_password" type="password" required></label></p>
      <p><label>New password<br><input name="new_password" type="password" required minlength="8"></label></p>
      <p><label>Confirm new password<br><input name="new_password2" type="password" required minlength="8"></label></p>
      <p><button type="submit" name="change_pw" value="1">Change password</button></p>
    </form>
  </details>

  <h2>Links (<?=h((string)db()->query("SELECT COUNT(*) FROM links")->fetchColumn())?>)</h2>
  <?php
    $links = db()->query("SELECT * FROM links ORDER BY created_at DESC LIMIT 500")->fetchAll();
  ?>
  <?php if(!$links):?>
    <p><em>No links yet.</em></p>
  <?php else:?>
    <div style="overflow-x:auto">
    <table>
      <thead><tr><th>code</th><th>url</th><th>created</th><th>expires</th><th>hits</th><th>action</th></tr></thead>
      <tbody>
      <?php foreach($links as $l):?>
        <tr>
          <td><a href="<?=h(short_url($l['code']))?>" target="_blank" rel="noopener"><?=h($l['code'])?></a></td>
          <td><a href="<?=h($l['url'])?>" target="_blank" rel="noopener" title="<?=h($l['url'])?>"><?=h(mb_strimwidth($l['url'],0,60,'…'))?></a></td>
          <td><small><?=h(date('Y-m-d H:i', (int)$l['created_at']))?></small></td>
          <td><small><?php if($l['expires_at']===null) echo 'never'; else echo h(date('Y-m-d H:i',(int)$l['expires_at'])); if($l['expires_at']!==null && (int)$l['expires_at']<time()) echo ' <span style="color:#ff0040">(expired)</span>'; ?></small></td>
          <td><?=h((string)$l['hits'])?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete '+this.delete_code.value+' ?')">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="delete_code" value="<?=h($l['code'])?>">
              <button type="submit" style="padding:0.2rem 0.5rem;font-size:0.75rem;border-color:#ff0040;color:#ff0040">delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    </div>
    <p><small>Showing up to 500 most recent links.</small></p>
  <?php endif;?>
<?php endif;?>
</main>
<footer><hr><p><small>n0c0.de-sht — sqlite @ <code>links.db</code></small></p></footer>
</body>
</html>

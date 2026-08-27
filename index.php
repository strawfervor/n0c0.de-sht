<?php
require_once __DIR__ . '/config.php';

// redirect setup if not done
if (!is_setup_done()) {
    header('Location: setup.php');
    exit;
}

cleanup_expired();

// --- handle short code redirect ---
// support both ?code=XXX and /XXX via .htaccess rewrite and php built-in router via REQUEST_URI
$codeParam = $_GET['code'] ?? null;
if ($codeParam === null) {
    // fallback: parse REQUEST_URI like /AbCd12
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $uri = trim($uri ?? '', '/');
    // ignore known files
    $known = ['index.php','setup.php','admin.php','delete.php','n0c0de-classless.css','links.db','config.php','.htaccess'];
    // if uri is a 6-char code and not a known file
    if (preg_match('/^[A-Za-z0-9]{6}$/', $uri) && !in_array($uri, $known, true)) {
        $codeParam = $uri;
    }
}
if ($codeParam !== null && preg_match('/^[A-Za-z0-9]{6}$/', $codeParam)) {
    $st = db()->prepare("SELECT * FROM links WHERE code = ?");
    $st->execute([$codeParam]);
    $link = $st->fetch();
    if (!$link) {
        http_response_code(404);
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not found — n0c0.de-sht</title><link rel="stylesheet" href="n0c0de-classless.css"></head><body><h1>n0c0.de-sht</h1><blockquote style="border-color:#ff0040">Link not found. — <a href="index.php">go home</a></blockquote></body></html><?php
        exit;
    }
    if ($link['expires_at'] !== null && (int)$link['expires_at'] < time()) {
        // expired -> delete and 410
        db()->prepare("DELETE FROM links WHERE code = ?")->execute([$codeParam]);
        http_response_code(410);
        $msg = 'Link has expired and was removed.';
        // render simple page
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Expired — n0c0.de-sht</title><link rel="stylesheet" href="n0c0de-classless.css"></head><body><h1>n0c0.de-sht</h1><blockquote style="border-color:#ffaa00"><?=h($msg)?></blockquote><p><a href="index.php">← back to home</a></p></body></html><?php
        exit;
    }
    // hit count
    db()->prepare("UPDATE links SET hits = hits + 1 WHERE code = ?")->execute([$codeParam]);
    // ensure url has scheme
    $url = $link['url'];
    header('Location: ' . $url, true, 302);
    exit;
} elseif ($codeParam !== null) {
    http_response_code(404);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not found — n0c0.de-sht</title><link rel="stylesheet" href="n0c0de-classless.css"></head><body><h1>n0c0.de-sht</h1><p>Invalid short code.</p><p><a href="index.php">← back</a></p></body></html><?php
    exit;
}

// --- handle shorten form ---
$error = '';
$result = null; // ['code'=>..., 'url'=>..., 'short'=>..., 'token'=>..., 'expires_at'=>...]

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Invalid CSRF token. Please reload the page.';
    } else {
        $raw = trim((string)$_POST['url']);
        if ($raw === '') $error = 'Please enter a URL.';
        elseif (strlen($raw) > 2048) $error = 'URL is too long (max 2048).';
        else {
            // add scheme if missing
            if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . $raw;
            if (!filter_var($raw, FILTER_VALIDATE_URL)) $error = 'Invalid URL.';
            else {
                $parts = parse_url($raw);
                if (empty($parts['host'])) $error = 'Invalid URL — missing host.';
                elseif (!in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)) $error = 'Only http and https URLs are allowed.';
                else {
                    // ok -> create link
                    $expiryDays = (int)(get_setting('expiry_days', '30') ?? '30');
                    $expiresAt = $expiryDays === 0 ? null : (time() + $expiryDays * 86400);
                    // generate unique code
                    $pdo = db();
                    $code = '';
                    for ($i=0;$i<10;$i++) {
                        $candidate = generate_code();
                        $chk = $pdo->prepare("SELECT 1 FROM links WHERE code = ?");
                        $chk->execute([$candidate]);
                        if (!$chk->fetch()) { $code = $candidate; break; }
                    }
                    if ($code === '') $error = 'Failed to generate code, try again.';
                    else {
                        $token = generate_token();
                        $hash = password_hash($token, PASSWORD_DEFAULT);
                        $st = $pdo->prepare("INSERT INTO links(code,url,created_at,expires_at,delete_token_hash) VALUES(?,?,?,?,?)");
                        $st->execute([$code, $raw, time(), $expiresAt, $hash]);
                        $result = [
                            'code' => $code,
                            'url' => $raw,
                            'short' => short_url($code),
                            'token' => $token,
                            'expires_at' => $expiresAt,
                        ];
                    }
                }
            }
        }
    }
}

$expiryDaysInfo = get_setting('expiry_days', '30');
$expiryText = $expiryDaysInfo === '0' ? 'never expire' : (h($expiryDaysInfo) . ' day(s)');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>n0c0.de-sht — url shortener</title>
<link rel="stylesheet" href="n0c0de-classless.css">
</head>
<body>
<header>
<h1>n0c0.de-sht</h1>
<p>Minimal url shorter.</p>
<nav><small><a href="index.php">home</a> · <a href="delete.php">delete a link</a> · <a href="admin.php">admin</a></small></nav>
</header>

<main>
<?php if ($error): ?>
  <blockquote style="border-color:#ff0040;color:#ff6b6b"><strong>Error:</strong> <?=h($error)?></blockquote>
<?php endif; ?>

<?php if ($result): ?>
  <section style="border:1px solid #00ff88; padding:1rem; background:#080808; margin:1.5rem 0">
    <h3 style="margin-top:0;color:#00ff88">✓ Link shortened!</h3>
    <p>Original: <br><code><?=h($result['url'])?></code></p>
    <p>Short link: <br><a href="<?=h($result['short'])?>"><?=h($result['short'])?></a></p>
    <p>Delete password: <br><code style="color:#ff0080;border-color:#ff0080; font-size:1.1rem"><?=h($result['token'])?></code></p>
    <blockquote>
      <strong>Save this password!</strong> You need the 6-char code <code><?=h($result['code'])?></code> + this password to delete the link at <a href="delete.php">delete.php</a>.
      It is shown only once.
    </blockquote>
    <?php if ($result['expires_at']): ?>
      <p><small>Expires: <?=h(date('Y-m-d H:i:s', $result['expires_at']))?> (in <?=h($expiryDaysInfo)?> days)</small></p>
    <?php else: ?>
      <p><small>This link never expires.</small></p>
    <?php endif; ?>
    <p><a href="index.php">← shorten another</a></p>
  </section>
<?php else: ?>
  <h2>Shorten a URL</h2>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <p>
      <label for="url">Paste URL to shorten</label><br>
      <input id="url" name="url" type="text" required maxlength="2048" placeholder="https://example.com/very/long/link" value="<?=h($_POST['url'] ?? '')?>" style="font-size:1rem">
      <small>http(s) only. Links currently expire after <?=h($expiryText)?>.</small>
    </p>
    <p><button type="submit">Shorten →</button></p>
  </form>
<?php endif; ?>

<hr>
<h3>How it works</h3>
<ul>
  <li>No login required — paste a link, get a <code><?=h(short_url('aB3x9Q'))?></code> style URL + delete password.</li>
  <li>Visit <code>/&lt;6-char&gt;</code> to redirect.</li>
  <li>To remove a link go to <a href="delete.php">delete.php</a> and enter code + password.</li>
</ul>
</main>

<footer><hr><p><small>n0c0.de-sht — <a href="admin.php">admin</a> · <a href="delete.php">delete</a></small></p></footer>
</body>
</html>

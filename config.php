<?php
// n0c0.de-sht - url shortener config + helpers
declare(strict_types=1);
session_start();

define('DB_FILE', __DIR__ . '/links.db');
define('CODE_LEN', 6);
define('DELETE_TOKEN_LEN', 12);

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $needInit = !file_exists(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($needInit) init_db($pdo);
    else ensure_tables($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        url TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        expires_at INTEGER,
        delete_token_hash TEXT NOT NULL,
        hits INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_links_code ON links(code)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_links_expires ON links(expires_at)");
}

function ensure_tables(PDO $pdo): void {
    // idempotent for existing db without tables (e.g. empty file)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        url TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        expires_at INTEGER,
        delete_token_hash TEXT NOT NULL,
        hits INTEGER NOT NULL DEFAULT 0
    )");
}

function get_setting(string $k, ?string $def = null): ?string {
    $st = db()->prepare("SELECT v FROM settings WHERE k = ?");
    $st->execute([$k]);
    $row = $st->fetch();
    return $row ? $row['v'] : $def;
}

function set_setting(string $k, string $v): void {
    $st = db()->prepare("INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v");
    $st->execute([$k, $v]);
}

function is_setup_done(): bool {
    return get_setting('admin_user') !== null && get_setting('admin_hash') !== null;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // strip port for display? keep as is
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    // if script is index.php at root, dirname is / -> empty
    // we want base as scheme://host + (dir if not /)
    // but for redirects and short links we want scheme://host/
    // So just scheme://host
    return $scheme . '://' . $host;
}

function short_url(string $code): string {
    return rtrim(base_url(), '/') . '/' . $code;
}

function generate_code(int $len = CODE_LEN): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($chars) - 1;
    $code = '';
    for ($i = 0; $i < $len; $i++) $code .= $chars[random_int(0, $max)];
    return $code;
}

function generate_token(int $len = DELETE_TOKEN_LEN): string {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($chars) - 1;
    $t = '';
    for ($i = 0; $i < $len; $i++) $t .= $chars[random_int(0, $max)];
    return $t;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(?string $tok): bool {
    return !empty($_SESSION['csrf']) && !empty($tok) && hash_equals($_SESSION['csrf'], $tok);
}

function require_setup(): void {
    if (!is_setup_done()) {
        header('Location: setup.php');
        exit;
    }
}

function cleanup_expired(): void {
    try {
        db()->prepare("DELETE FROM links WHERE expires_at IS NOT NULL AND expires_at < ?")->execute([time()]);
    } catch (Throwable $e) {}
}

function is_admin(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function admin_require(): void {
    if (!is_admin()) {
        header('Location: admin.php');
        exit;
    }
}

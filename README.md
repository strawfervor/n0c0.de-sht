# n0c0.de-sht
Private, self-hosted, web-based url shortener.

### What is n0c0.de?
[n0c0.de](https://n0c0.de) - a collection of small and lightweight apps based mostly on PHP, with a minimal amount of CSS and JS.

### Features
- 100% self-hosted, zero dependencies
- No JavaScript needed (works without JS)
- Shorten any URL without an account - get a 6-char code + delete password (shown once)
- Clean short URLs (`/Ab3x9Q`), with `?code=` fallback when rewrites are not available
- Links expire after a configurable number of days (0 = never) and are cleaned up automatically
- Dashboard with hits, expiry / delete and separate login/password
- SQLite only - no MySQL needed
- CSRF protection, `password_hash`

## Requirements
- **PHP >= 7.4** (recommended >= 8.1)
- PHP extensions: `pdo_sqlite`, `sqlite3`, `session`
- Optional: `mbstring` for URL excerpts in the admin panel
- Writable script directory (`links.db` is created there)
- Any webserver: Apache / Nginx / Caddy

## Installation
1. Download / clone the repo
2. Unpack on your hosting
3. Make sure the script directory is writable and `links.db` / `config.php` are protected from direct access (deny rules)
4. Route unknown paths to `index.php` for clean short links - otherwise the `?code=` fallback still works
5. Go through `setup.php`
6. Enjoy!

To start fresh, just delete the database file (`links.db`).

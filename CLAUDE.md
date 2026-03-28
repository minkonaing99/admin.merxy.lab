# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Custom PHP admin panel for MerxyLab — manages retail and wholesale sales, products, users, and reporting. No external framework or package manager. Pure PHP 8+ backend, vanilla JS frontend, minified CSS.

## Development Setup

No build step required. Run locally with a PHP-capable web server (LAMP/LEMP/Valet):

```bash
php -S localhost:8000   # Quick local server
```

**Required `.env` file** (not in repo) with these keys:
```
MERXYLAB_DB_HOST=
MERXYLAB_DB_PORT=
MERXYLAB_DB_NAME=
MERXYLAB_DB_USER=
MERXYLAB_DB_PASS=
MERXYLAB_SESSION_NAME=
MERXYLAB_SESSION_SECURE=
MERXYLAB_SESSION_SAMESITE=
MERXYLAB_SESSION_MAX_LIFETIME=
MERXYLAB_REMEMBER_SECRET=
```

`MERXYLAB_REMEMBER_SECRET` must be set to a random string of at least 32 bytes. If missing, the app will throw on any remember-me login attempt rather than falling back to a known default.

No automated test suite. To clear the API response cache, delete the contents of `app/cache/response/`. Note that the JS layer also caches API responses in `sessionStorage` — a hard refresh (Shift+Reload) or clearing session storage is needed to flush the browser-side cache.

## Architecture

### Request Flow

Every page (`sales_overview.php`, `product_catalog.php`, `summary.php`, `user_list.php`) renders server-side HTML with role-gated content, then JavaScript fetches data from `api/` endpoints via `fetch()`.

Every API endpoint starts with:
```php
require __DIR__ . '/session_bootstrap.php';
require __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
```

`session_bootstrap.php` initializes and hardens the PHP session. `auth.php` provides `auth_require_login()` and related helpers that terminate the request on authorization failure. `app/bootstrap.php` is the shared autoloader — it loads all four core classes and boots `Config`. New endpoints must include it; do not require the individual core files directly.

### Core Utilities (`app/core/`)

| File | Purpose |
|------|---------|
| `Config.php` | Loads `.env` and exposes `Config::get()` |
| `Database.php` | PDO singleton — call `Database::getInstance()` |
| `Http.php` | `Http::json()` / `Http::error()` response helpers |
| `ResponseCache.php` | File-based API cache with ETag support (30s TTL) |

### Dual Retail/Wholesale Pattern

Every feature is implemented twice with symmetric files:
- `api/sales_*.php` ↔ `api/ws_sales_*.php`
- `api/product_*.php` ↔ `api/ws_product_*.php`
- `js/sales_overview.js` ↔ `js/ws_sales_overview.js`

When modifying a feature, changes almost always need to be applied to both the retail and wholesale variants.

**Schema differences:** wholesale sales (`ws_sale_overview`) include `quantity` and `profit` columns that the retail view (`sale_overview`) does not. Account for this when writing queries or serializing rows that touch both variants.

### Database Views

The API layer queries MySQL views, not base tables. The views in use are:
- `sale_overview` — retail sales (read by `api/sales_table.php`, `api/sales_minimal.php`)
- `ws_sale_overview` — wholesale sales (read by `api/ws_sales_table.php`, `api/ws_sales_minimal.php`)
- `products_catalog` — retail products
- `ws_products_catalog` — wholesale products
- `users` / `bot_users` — web and bot user accounts

If query results look wrong, verify the view definitions in the database before editing PHP.

### Session Security

Implemented in `api/session_bootstrap.php` and `api/auth.php`:
- Idle timeout: 15 min, absolute timeout: 8 hours
- Session ID regenerated every 5 minutes
- Fingerprint: user-agent + IP masked to `/24` subnet
- Remember-me: signed cookie (tamper-resistant, no DB lookup)

### Pagination

Sales tables use cursor-based pagination. Cursors are base64-encoded `purchase_date|sale_id` pairs. Do not convert these to offset-based queries — they exist specifically to handle large datasets.

### Roles

Three roles enforced at both page and API level: `owner` > `admin` > `staff`. Use `requireRole(['admin', 'owner'])` pattern from `auth.php`.

### CSRF Protection

Added to `api/auth.php`:
- `csrf_token()` — generates or returns the session-bound token
- `csrf_verify()` — validates `X-CSRF-Token` header with `hash_equals`; returns 403 on failure
- `auth_require_login()` calls `csrf_verify()` automatically on every POST request

Every protected page (`sales_overview.php`, `product_catalog.php`, `summary.php`, `user_list.php`) emits `<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">`. `js/csrf.js` patches `window.fetch` to read that tag and attach `X-CSRF-Token` on all state-changing requests (`POST`, `PUT`, `PATCH`, `DELETE`) — individual fetch call sites need no changes. Load `js/csrf.js` as the first script on any new protected page.

No CORS headers are set on any endpoint. Do not add `Access-Control-Allow-Origin` headers.

## Known Issues & Past Fixes

### Security hardening (fixed)
Six security issues were fixed in one pass:

1. **CORS wildcard removed** — `Access-Control-Allow-Origin: *` was present on all product mutation and table endpoints. All CORS headers have been stripped; the app is same-origin only.
2. **CSRF protection added** — see Architecture section above.
3. **XSS in upload preview** — `js/upload.js` `showPreview()` was interpolating CSV-parsed values directly into `innerHTML`. Rewritten to use DOM APIs (`createElement`, `textContent`) exclusively.
4. **Debug data leak** — `api/sales_bulk_insert.php` was returning `debug_data: $sales` in error responses. Removed.
5. **console.log in production** — `js/product_catalog.js` logged the full request payload; `js/loading.js` logged an init message. Both removed.
6. **Type-juggling in user_delete** — `api/user_delete.php` used loose `==` for self-delete guard. Changed to `(int) === (int)`.

### Inline edit cell resize (fixed)
`startInlineEdit()` in `js/sales_overview.js` and `js/ws_sales_overview.js` was using `form-control form-control-sm` on the injected `<input>`, whose padding and border caused the table cell to grow. Fixed by:
1. Snapshotting `td.offsetWidth`/`td.offsetHeight` and pinning them as inline styles before hiding the span.
2. Replacing `form-control` with a transparent, borderless style (`padding:0; border:none; background:transparent; font:inherit`).
3. Calling `unlockCellSize(td)` (clears the pinned styles) in all exit paths — cancel, no-change save, and normal save.
Do not add `form-control` or similar framework input classes to inline edit inputs; they will cause reflow.

### SHOW COLUMNS per-request overhead (fixed)
`api/product_insertion.php` and `api/product_update.php` ran `SHOW COLUMNS FROM products_catalog LIKE 'store'` on every request. Result is now cached in `$_SESSION['_schema_has_store_col']` — the query runs once per session. Apply the same pattern (`$_SESSION['_schema_has_store_col']`) if new endpoints need the same check.

### PDO param style inconsistency (partially fixed)
`api/sales_table.php` (retail) uses positional `?` params with `execute($params)`. `api/ws_sales_table.php` (wholesale) uses named `:param` params with explicit `bindValue()` calls. The two files still differ in param style. When editing either file, maintain the style already present in that file and keep logic in sync across both.

### Remember-me fallback secret (fixed)
`api/remember.php` previously fell back to a hardcoded secret (`change-me-please-32bytes-min`) if `MERXYLAB_REMEMBER_SECRET` was missing from `.env`. This allowed anyone with source access to forge valid remember-me cookies. It now throws a `RuntimeException` on startup if the key is absent. Always generate the secret with:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### sales_overview.php loading performance (fixed)
Three issues were found and fixed in the sales overview page load:

1. **Artificial 1-second minimum spinner delay** — `loadSales()` in both `js/sales_overview.js` and `js/ws_sales_overview.js` enforced a 1-second minimum loading time even when data came from cache instantly. Removed — loader now hides as soon as data is ready.

2. **Wholesale data fetched on every page load** — `ws_sales_overview.js` auto-fetched on `DOMContentLoaded` regardless of which tab was active. Changed to lazy-init: wholesale data only loads the first time the user clicks the wholesale tab. The trigger lives in `js/add_sales_toggle.js` via `window.loadWsSalesIfNeeded?.()`.

3. **Initial fetch limit reduced** — `API_FETCH_LIMIT` dropped from 500 → 200 in both JS files. The table renders 100 rows at a time via infinite scroll, so fetching 500 upfront was 5× more than needed for the first view.

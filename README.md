# MerxyLab Admin Panel

This repository contains the internal web admin panel for MerxyLab. It is a PHP-based back office used to manage sales records, product catalogs, summary reporting, and user accounts for both retail and wholesale workflows.

The application is built as a server-rendered PHP site with JavaScript-enhanced pages. Root-level PHP files render the main screens, the `api/` directory exposes JSON endpoints for page interactions, and `app/core/` provides shared configuration, database, HTTP, and response-cache utilities.

## Overview

The panel is organized around four main areas:

- Authentication: secure login, session bootstrapping, role checks, logout, and remember-me cookies.
- Sales operations: retail and wholesale sales listing, creation, inline editing, deletion, refresh, and customer search.
- Product catalog: separate retail and wholesale product datasets with add, edit, and delete workflows.
- Reporting and administration: summary dashboards, renewal/expiry tracking, charts, CSV export endpoints, and owner-only user management.

## Key Features

- Role-based access control for `owner`, `admin`, and `staff`.
- Secure session handling with idle timeout, absolute timeout, and session ID regeneration.
- Remember-me login using signed cookies.
- Retail and wholesale sales views on the same operational dashboard.
- Cached sales loading with ETags, short-lived file-based response caching, and client-side session storage caching.
- Infinite-scroll style progressive rendering for large sales tables and mobile card views.
- Inline editing for selected sales fields such as customer, email, manager, and note.
- Product catalog CRUD for both retail and wholesale product lists.
- Summary screen for expiring items, renewable items, KPI cards, and sales/profit charts.
- User management for web users and bot users.
- Shared responsive navigation and mobile-friendly table/card layouts.

## Access Model

The application enforces access at the page and API level:

- `staff`: sales operations.
- `admin`: sales operations, product catalog, summary, and user list API access.
- `owner`: full access, including the user management page.

Main page access:

- `index.php`: public login page.
- `sales_overview.php`: `admin`, `staff`, `owner`.
- `product_catalog.php`: `admin`, `owner`.
- `summary.php`: `admin`, `owner`.
- `user_list.php`: `owner`.

## Application Structure

### Root pages

- `index.php`: login screen and remember-me auto-login redirect.
- `sales_overview.php`: main sales workspace for retail and wholesale records.
- `product_catalog.php`: product management page with retail/wholesale tabs.
- `summary.php`: reporting dashboard with KPI cards, renew/expiry monitoring, and charts.
- `user_list.php`: owner-only account management screen.

### `api/`

The `api/` directory contains JSON endpoints grouped by domain:

- Auth/session:
  - `session_bootstrap.php`
  - `auth.php`
  - `login.php`
  - `logout.php`
  - `remember.php`
- Sales:
  - `sales_table.php`
  - `sale_insertion.php`
  - `sale_delete.php`
  - `sale_update_inline.php`
  - `sales_minimal.php`
  - `sales_export_csv.php`
  - `sales_bulk_insert.php`
- Wholesale sales:
  - `ws_sales_table.php`
  - `ws_sale_insertion.php`
  - `ws_sale_delete.php`
  - `ws_sale_update_inline.php`
  - `ws_sales_minimal.php`
  - `ws_sales_export_csv.php`
- Products:
  - `products_table.php`
  - `product_insertion.php`
  - `product_update.php`
  - `product_delete.php`
  - `product_options.php`
- Wholesale products:
  - `ws_products_table.php`
  - `ws_product_insertion.php`
  - `ws_product_update.php`
  - `ws_product_delete.php`
  - `ws_product_options.php`
- Users:
  - `user_list.php`
  - `user_create.php`
  - `user_delete.php`
  - `bot_user_delete.php`
- Database bootstrap:
  - `dbinfo.php`

### `app/`

- `bootstrap.php`: loads shared core classes and environment configuration.
- `core/Config.php`: lightweight `.env` loader and config access helpers.
- `core/Database.php`: PDO connection factory for MySQL.
- `core/Http.php`: request method checks, JSON response helpers, and request body parsing.
- `core/ResponseCache.php`: file-based response caching used by selected API endpoints.
- `cache/response/`: generated JSON cache files for short-lived API responses.

### `js/`

Frontend scripts are organized by page and behavior:

- Auth and shared UI:
  - `auth.js`
  - `nav.js`
  - `toggle.js`
  - `loading.js`
- Sales:
  - `sales_overview.js`
  - `sales_add_form.js`
  - `add_sales_toggle.js`
  - `download_csv.js`
- Wholesale sales:
  - `ws_sales_overview.js`
  - `ws_sales_add_form.js`
- Products:
  - `product_catalog.js`
  - `product_catalog_toggle.js`
- Summary:
  - `summary_table.js`
  - `deplay_chart.js`
- Users:
  - `user_list.js`
- Miscellaneous:
  - `upload.js`

### `style/`

Minified CSS bundles are split by screen and shared behavior:

- `style.min.css`: shared layout and global styles.
- `login.min.css`: login page styling.
- `sales_overview.min.css`: sales dashboard styling.
- `product_catalog.min.css`: product catalog styling.
- `summary.min.css`: summary and reporting UI styling.
- `mobile_table.min.css`: mobile table/card presentation.
- `wholesale.min.css`: wholesale-specific page states.
- `loading.min.css`: loading overlay styling.
- `upload.min.css`: upload-related styles.

### `assets/`

Static assets include the MerxyLab logo and SVG icons used across buttons, navigation, and actions.

## Architecture Notes

- Server rendering is used for protected pages and navigation layout.
- Dynamic interactions are handled with vanilla JavaScript and `fetch`.
- Database access is centralized through PDO in `app/core/Database.php`.
- Auth is enforced both before page render and inside API endpoints.
- Selected APIs use short-lived file caching plus ETags to reduce repeated query cost.
- Several frontend screens also cache fetched data in `sessionStorage` and revalidate in the background.
- Retail and wholesale flows are intentionally parallel: each has its own API endpoints and page scripts while sharing the same UI pattern.

## Data Flow Summary

### Login flow

1. `index.php` boots the session and tries remember-me login.
2. `api/login.php` verifies the submitted credentials against the `users` table.
3. `api/auth.php` stores the authenticated user in the session and enforces timeouts and role access.

### Sales flow

1. `sales_overview.php` renders the page shell.
2. `js/sales_overview.js` and `js/ws_sales_overview.js` request paginated sales data from their respective APIs.
3. The frontend renders desktop tables and mobile cards, supports search, and sends inline updates/delete requests back to the API layer.

### Product flow

1. `product_catalog.php` renders retail and wholesale tables and forms.
2. `js/product_catalog.js` switches between retail and wholesale modes.
3. Form submissions call the matching product CRUD endpoint.

### Summary flow

1. `summary.php` renders KPI containers, range filters, tables, and chart canvases.
2. `js/summary_table.js` loads merged sales data from `api/sales_minimal.php`.
3. `js/deplay_chart.js` renders chart-based reporting from the summary dataset.

## Configuration Surface

The project reads configuration from `.env`. The current codebase uses keys for:

- Database host, port, name, user, and password.
- Session cookie name, security flags, SameSite policy, and session lifetime.
- Remember-me signing secret.
- Optional storefront path reference.
- Environment/debug flags.

Sensitive values should stay out of documentation, screenshots, and commits meant for sharing.

## Important Directories at a Glance

```text
admin.merxylab.online/
|-- api/                  JSON endpoints for auth, sales, products, summary, and users
|-- app/
|   |-- core/             Config, DB, HTTP, and cache helpers
|   `-- cache/response/   File-based API cache
|-- assets/               Icons and branding assets
|-- js/                   Frontend page controllers
|-- style/                Minified CSS bundles
|-- index.php             Login page
|-- sales_overview.php    Sales dashboard
|-- product_catalog.php   Product management
|-- summary.php           Summary and reporting
`-- user_list.php         Owner-only user management
```

## Maintenance Notes

- The codebase mixes legacy naming with newer structured utilities, so both patterns exist at the same time.
- A number of flows have separate retail and wholesale implementations with mirrored filenames.
- Some summary and catalog behavior depends on specific database columns and views such as `sale_overview`, `ws_sale_overview`, `products_catalog`, `ws_products_catalog`, `users`, and `bot_users`.
- The `app/cache/response/` directory is generated runtime cache data, not source content.

## Intended Audience

This README is meant to help developers, maintainers, and future contributors understand:

- what the admin panel is responsible for,
- where each feature lives,
- how the frontend and backend are split,
- and which files matter when extending or debugging a specific screen.

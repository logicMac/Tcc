# TCC RRS — Codebase Index

Tampakan Community College Registration & Records System.
Stack: **PHP 8.0+ (procedural, no framework), MySQL/MariaDB via PDO, Bootstrap 5 + Bootstrap Icons (CDN), vanilla JS**. Runs on WAMP at `C:\wamp64\www\tcc_rrs` → `http://localhost/tcc_rrs/`.
~15k LOC PHP + `assets/css/main.css` (2852 lines) + `assets/js/main.js` (370 lines). Full docs in `README.md`.

## Request flow
- Every page: `require config.php` → `helpers.php` → `startSession()` → auth guard → handle POST → render HTML via `includes/partials/*` + `components.php`.
- `index.php` (root): redirects logged-in staff to `{role}/dashboard.php`, else to `student/index.php`.
- Server-rendered pages; CSRF token on every form (`csrfField()`/`abortCsrf()`); flash messages via `flash()`/`renderFlash()`.

## Roles & sessions (two parallel session namespaces)
- `users` table → `registrar` | `staff`; session keys `user_id`, `user_role`, `user`. `requireAuth(ROLE_*)` guards; `must_change_password` forces `auth/change_password.php`.
- `students` table → portal accounts; session keys `student_id`, `student`. `requireStudentAuth()` guards. Both can be signed in simultaneously.

## Directory map
| Path | Purpose |
|---|---|
| `auth/` | `login.php` (staff+registrar+student unified), `logout.php`, `change_password.php`, `unauthorized.php` |
| `student/` | Public portal + optional accounts: `index`, `pre_registration` (1043 ln, largest form), `document_request`, `track` (+QR scanner), `register`, `login`, `logout`, `account` |
| `staff/` | Protected (staff+registrar): `dashboard`, `registrations`, `view_/edit_registration`, `document_requests`, `view_/edit_document`, `issue_document` (QR release slip), `qr_verify`, `master_list` (CSV export), `reports` |
| `registrar/` | Registrar-only: `dashboard`, `staff`/`create_staff`/`edit_staff`, `students`, `catalog` (document types), monitors, mirrors of staff view/edit/issue pages, `reports`, `activity_logs`, `settings` |
| `api/geo.php` | JSON cascade: `?level=regions`, `?parent={code}` → provinces/cities/barangays from `assets/data/psgc.json` (PSGC) |
| `includes/` | `config.php` (constants/DB/statuses/security headers), `db.php` (`db()` PDO singleton + `fetchAll/fetchOne/fetchValue/execute/insert/paginate`), `helpers.php`, `components.php` (reusable UI), `notifications.php` (email via SMTP settings), `partials/` |
| `database/` | `tcc_rrs.sql` (fresh install, DROPS db — never re-import), `migration_v2/v3/v4.sql` (idempotent upgrades) |
| `uploads/` | `credentials/` (student scans, v4), `photos/`, `qrcodes/`, `documents/` |
| root | `verify.php` (public issued-doc check), `setup.php` + `reset_accounts.php` (one-time scripts) |

## Database — 12 tables (`tcc_rrs`, utf8mb4)
`users` (staff/registrar), `students` (portal accounts), `programs`, `document_types`, `pre_registrations` (enrollments; v3 added PSGC address split, parents/guardian/spouse; v4 added `credentials_submitted`/`credentials_presented` JSON), `document_requests`, `status_history`, `issued_documents` (TCC-VRF codes), `notifications`, `activity_logs`, `system_settings` (key-value: school info, SMTP, AY), `tracking_sequences` (atomic per-prefix-year counters).
Migrations add: `student_account_id` links, `is_archived`/`archived_at`/`archived_by` soft-archive on both submission tables.

## Key conventions
- Tracking codes via `generateTrackingCode(prefix)`: `TCC-REG-YYYY-NNNNNN`, `TCC-DOC-*`, `TCC-VRF-*`.
- Status enums in `config.php`: `REG_STATUSES` (6) / `DOC_STATUSES` (8, adds Ready for Release/Released). `statusBadge()`, `statusTimeline()`, `addStatusHistory()`, `getStatusHistory()`.
- Every important action → `logActivity($action,$module,$desc,$ref)`.
- Status change → `addStatusHistory()` + `notifications.php` email (toggleable in settings).
- Output escaping: `e()`/`sanitize()`; request input: `post()/get()/postInt()/getInt()`; `isPost()/isAjax()`; `jsonResponse()`.
- `getSetting($key)` cached reader for `system_settings`.
- `linkSubmissionToStudent()` + `claimSubmissionsForStudent()` auto-link submissions to accounts by email.

## Config / security
- `includes/config.php`: `APP_DEBUG=true` (dev), DB creds `root`/empty pass, bcrypt cost 12, TZ Asia/Manila, security headers, `BASE_URL` auto-detect.
- `.htaccess` in `includes/` and `database/` deny web access; root `.htaccess` has general rules.
- Passwords bcrypt; session cookie httponly+samesite=Strict, 2h lifetime; `secure` flag off (HTTP localhost).

## Gotchas
- `database/tcc_rrs.sql` starts with `DROP DATABASE IF EXISTS` — destructive.
- `setup.php`/`reset_accounts.php` are one-time utilities; README says delete after install.
- QR scanning via Html5-QRCode CDN; QR generation via QRCodeJS CDN (internet required).
- Registrar section largely mirrors staff pages (view/edit/issue) with registrar sidebar — changes to shared logic may need edits in both copies.
- `uploads/` must stay writable.

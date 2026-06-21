# Security Notes

LedgerFlow implements the following controls:

- CSRF tokens on state-changing forms.
- Escaped output through `e()` in PHP views.
- Prepared PDO statements for database queries.
- Password hashing with PHP `password_hash()`.
- Secure session cookie flags: `HttpOnly`, `SameSite=Lax`, and `Secure` when HTTPS is detected.
- Login rate limiting backed by the database.
- Role-based access control with owner, manager, accountant, and viewer roles.
- File upload MIME validation through Fileinfo, random names, size limits, and `.htaccess` script execution blocks.
- Password reset tokens stored as SHA-256 hashes with one-hour expiry and single-use marking.
- Audit logs for authentication and financial/privacy actions.
- Protected `app`, `config`, `database`, `storage`, and dependency directories through `.htaccess`, plus dotfiles, `VERSION`, and `*.md` metadata files.
- Privacy exports and client anonymization to support GDPR data subject rights.
- Rate limiting keys off `REMOTE_ADDR` by default; `X-Forwarded-For` is only honored when the connecting IP is explicitly listed in `security.trusted_proxies`, preventing header-spoofing bypass of login/throttle limits.
- Only an `owner` account can grant the `owner` role when creating a user, closing a privilege-escalation path from `manager`-level accounts (which also hold `settings.manage`).
- Idle (`security.session_idle_minutes`, default 120) and absolute (`security.session_absolute_minutes`, default 720) session expiry, enforced on every authenticated request.
- Global security headers on every response: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, a restrictive `Content-Security-Policy`, and `Strict-Transport-Security` when served over HTTPS.
- Invoice/quote/recurring line items are capped (200 per document) to prevent oversized submissions from exhausting memory or holding a long-running DB transaction.
- The self-update feature (Settings → Software updates, owner-only) only downloads from URLs prefixed with the configured GitHub repo, backs up the full installation to `storage/backups/` before overwriting anything, and never touches `config/config.php`, `.env`, or `storage/` data.

## Operational Recommendations

- Use HTTPS only.
- Keep PHP and MySQL/MariaDB patched.
- Prefer a document root pointed at `public/`.
- Restrict database user privileges to this application database.
- Back up the database before future migrations.
- Review audit logs after user, payment, invoice, and privacy operations.
- Only set `security.trusted_proxies` if this app sits behind a reverse proxy/CDN you control; otherwise leave it empty so forwarded-IP headers are ignored.
- After applying a self-update, verify the app still loads correctly before discarding the matching backup in `storage/backups/`.

## Known Extension Points

- Add Composer packages `dompdf/dompdf` and `phpmailer/phpmailer` for more sophisticated PDFs and SMTP if the host supports Composer.
- Add TOTP verification using the existing `two_factor_secret` and `two_factor_enabled` columns.
- Add payment gateway modules through isolated service classes and audit all webhook handlers.


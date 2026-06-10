# Deployment Readiness

Jackson V1 remains acquisition intelligence only. SyncERP execution, billing, production reporting, labor tracking, and equipment tracking stay outside this release.

## Environment

Recommended variables:

- `APP_ENV=production` to hide stack traces and avoid local-only reset token display.
- `JIP_SEED_MODE=production` for minimal production seed data.
- `JIP_ASSUME_HTTPS=1` only after HTTPS is confirmed at the web server or reverse proxy.
- `JIP_STRICT_PRODUCTION=1` when running the final launch gate.
- Configure a production mailer before enabling real password reset delivery.

## Credentials

Production seed creates baseline users only when missing and marks new seeded users for password change. Do not expose the app with the seeded `password` credential active.

Rotate any remaining seeded default passwords:

```bash
php scripts/rotate_default_passwords.php
```

The script writes one-time passwords to `storage/secrets/` with restricted permissions. Operators must change those passwords at first login.

## Database

SQLite operational rule: run database-writing scripts sequentially.

Daily cycle:

```bash
php scripts/run_acquisition_cycle.php
```

Do not run harvesters, raw processing, target building, and decision rebuilds in parallel on SQLite.

## Backup And Restore

Create a backup:

```bash
php scripts/backup_database.php
```

Restore requires explicit confirmation:

```bash
php scripts/restore_database.php storage/backups/example.sqlite CONFIRM_RESTORE
```

Always test restore before real-data work and before any real-data import session.

Verify a backup without overwriting the live database:

```bash
php scripts/verify_backup_restore.php
```

## Quality Gates

Run before deployment or operational review:

```bash
php scripts/migrate.php
php scripts/seed.php
php scripts/run_acquisition_cycle.php
php scripts/check_data_integrity.php
php scripts/smoke_routes.php
php scripts/validate_erp_contract.php
php scripts/backup_database.php
php scripts/verify_backup_restore.php
php scripts/check_production_launch.php
```

Then run PHP lint across all PHP files and `git diff --check`.

Final strict launch gate:

```bash
APP_ENV=production JIP_ASSUME_HTTPS=1 JIP_STRICT_PRODUCTION=1 php scripts/check_production_launch.php
```

## Security Checklist

- Confirm HTTPS in production so secure session cookies are active.
- Confirm `APP_ENV=production`.
- Confirm all seeded/default passwords have been rotated.
- Confirm operators can complete the forced password-change flow.
- Confirm password reset mailer is configured before external users rely on reset.
- Confirm backup restore verification has run in the last 24 hours.
- Confirm the release worktree is clean before deployment.
- Review `/audit-logs` after operator actions.
- Confirm Viewer role cannot submit POST actions.
- Confirm non-global users cannot access out-of-scope regional URLs.

## Logs

- Application errors: `storage/logs/app.log`
- Local password reset tokens: `storage/logs/password_resets.log`

Production should ship logs to a managed log destination when available.

## Connectors

The first connector path is review-gated. It writes to review-gated source items as `Needs Review` and never bypasses the signal quality engine.

Schedule connector runs only after the operations owner confirms source reliability and review capacity.

# Deployment Readiness

Jackson V1 remains acquisition intelligence only. SyncERP execution, billing, production reporting, labor tracking, and equipment tracking stay outside this release.

## Environment

Recommended variables:

- `APP_ENV=production` to hide stack traces and avoid local-only reset token display.
- `JIP_SEED_MODE=production` for minimal production seed data.
- Configure a production mailer before enabling real password reset delivery.

## Database

SQLite pilot rule: run database-writing scripts sequentially.

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

Always test restore before pilot kickoff and before any real-data import session.

## Quality Gates

Run before deployment or pilot review:

```bash
php scripts/migrate.php
php scripts/seed.php
php scripts/run_acquisition_cycle.php
php scripts/check_data_integrity.php
php scripts/smoke_routes.php
php scripts/validate_erp_contract.php
php scripts/backup_database.php
```

Then run PHP lint across all PHP files and `git diff --check`.

## Security Checklist

- Confirm HTTPS in production so secure session cookies are active.
- Confirm `APP_ENV=production`.
- Confirm password reset mailer is configured before external users rely on reset.
- Review `/audit-logs` after pilot actions.
- Confirm Viewer role cannot submit POST actions.
- Confirm Mike/Ron cannot access out-of-scope regional URLs.

## Logs

- Application errors: `storage/logs/app.log`
- Local password reset tokens: `storage/logs/password_resets.log`

Production should ship logs to a managed log destination when available.

## Connectors

The first connector path is review-gated. It writes to Raw Signal Items as `Needs Review` and never bypasses the signal quality engine.

Schedule connector runs only after the pilot owner confirms source reliability and review capacity.

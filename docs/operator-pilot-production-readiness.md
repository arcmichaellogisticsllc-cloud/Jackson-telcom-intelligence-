# Operator Pilot Production Readiness

Objective: move Jackson Intelligence Platform from architecturally complete to pilot-ready and production-safe.

## Focus Order

1. Authorization
2. Security hardening
3. Data review queue
4. First real connector
5. Operator pilot feedback loop
6. Recommendation/action tuning
7. Deployment readiness
8. SyncERP contract validation

## Authorization

Operator-mode filtering remains a user experience feature, but pilot readiness now adds server-side region and write checks:

- Mike: Southeast, shared Southwest, shared National
- Ron: Great Lakes, shared Southwest, shared National
- Regional Owner / Operator / Viewer: assigned region
- Southwest Owner: Southwest, National
- Admin / Executive: all regions

Regional URLs, mapped detail records, and POST requests carrying `region_id` are checked against the authenticated operator's allowed regions before the controller runs. Viewer users are read-only and blocked from POST workflows. Unauthorized attempts return a 403 page and write an audit log event.

## Security Hardening

Security controls now include:

- CSRF token generation and validation for all rendered POST forms
- Automatic CSRF hidden-field injection into rendered POST forms
- Session timeout enforcement
- CSRF token rotation on login
- Session regeneration on login
- Basic login attempt throttling
- Password reset token foundation with expiry and one-time use
- Security headers for frame, content type, referrer, and content security policy
- Existing HTTP-only, SameSite session cookies remain active in `public/index.php`
- Application error logging to `storage/logs/app.log`

## Audit Logging

Audit logs are available at `/audit-logs` and inside `/production-readiness`.

Tracked events include login, logout, failed login, unauthorized access, CSRF failure, data quality updates, connector runs, recommendation suppression, pilot feedback, SyncERP validation updates, and generic POST route attempts where available.

## Data Review Queue

The Data Review Queue is available at `/production-readiness`.

It creates human review items from:

- Raw signals marked Needs Review, Duplicate, or Rejected
- High/Critical signals that were archived by quality classification
- Low-score open recommendations that may be creating noise

Operators can move review items through Open, In Review, Resolved, and Dismissed. Updates write activity records.

## Data Quality Review

Data Quality Review is available at `/data-quality` and in the Production Readiness workspace.

Issue types include duplicate entities, missing contact info, bad imports, low-confidence signals, disputed classifications, source reliability concerns, stale contacts, conflicting data, missing region, and missing owner.

Issues can be assigned, linked to records, moved through Open/In Review/Resolved/Dismissed, and resolved with outcome notes.

## First Real Connector

The first real connector path is review-gated RSS/static-source import with a CSV/source-file fallback.

Rules:

- RSS connector only runs when a Signal Source has `collection_method = RSS` and a non-empty `source_url`.
- Connector framework records connector configuration and connector run logs.
- Connector runs create or reuse source records.
- It reads RSS or Atom feeds into Raw Signal Items.
- Fallback connector rows are supported when local HTTP is unavailable.
- It does not scrape pages.
- It does not send outreach.
- It does not auto-convert records.
- Human review remains required through the normal signal pipeline.

Seed data includes a paused FCC broadband RSS example. It is intentionally not active by default so local verification does not depend on network access.

## Operator Pilot Feedback Loop

The pilot feedback form captures:

- Owner
- Theater
- Feedback area
- Feedback summary
- Friction score
- Impact score
- Recommended change

Use this during the first 30 days to tune the interface and action logic around Mike and Ron's actual workflow.

## Recommendation / Action Tuning

Tuning rules track:

- Source module
- Category
- Owner scope
- Region
- Minimum priority score
- Maximum daily actions
- Whether a recommendation can promote to Daily Action

These rules are pilot controls. They document the desired behavior before deeper recommendation governance is added.

Operators can also mark a recommendation as not useful. That suppresses the recommendation, lowers usefulness score, tracks the reason, and prevents it from remaining in the executive action path.

## Deployment Readiness

Before a pilot session:

1. Run `php scripts/migrate.php`.
2. Run `php scripts/run_acquisition_cycle.php`.
3. Run `php scripts/check_data_integrity.php`.
4. Run `php scripts/smoke_routes.php`.
5. Run PHP lint across all PHP files.
6. Run `git diff --check`.
7. Run `php scripts/validate_erp_contract.php`.
8. Create a database backup with `php scripts/backup_database.php`.
9. Verify the backup can be restored in a non-production location with `php scripts/restore_database.php <backup> CONFIRM_RESTORE`.

SQLite DB-writing scripts must run sequentially.

See `docs/deployment-readiness.md` and `docs/operator-pilot-guide.md`.

## SyncERP Contract Validation

SyncERP remains isolated. Contract validation exists only to confirm the future handoff fields.

Validation tracks:

- Customer
- Project
- Capacity
- Subcontractors
- Margin Forecast
- Risk
- Scenario
- Relationships
- Package Metadata

Each required field should map to a source record and source field before execution integration work begins.

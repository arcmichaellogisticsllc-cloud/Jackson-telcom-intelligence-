# Production Data Transition

This transition removes demo business intelligence while preserving the system controls needed to start real operation.

Jackson Platform remains acquisition intelligence only. This transition does not build SyncERP execution, billing, production reporting, or new business modules.

## Demo vs Production Mode

Demo mode is the default:

```bash
php scripts/seed.php
```

Demo mode creates sample contacts, organizations, targets, hunts, capacity, relationships, opportunities, demand, executive packages, onboarding records, and pilot feedback for development and training.

Production mode creates only baseline system/reference data:

```bash
JIP_SEED_MODE=production php scripts/seed.php
```

Production mode creates:

- users
- regions / theaters
- capacity targets
- operator modes
- platform health definitions
- connector registry
- recommendation governance rules
- ERP contract validation metadata

Production mode does not create fake contacts, companies, subcontractors, opportunities, recommendations, daily actions, strategic accounts, capacity records, visual data, executive packages, onboarding records, or communications.

## Dry-Run Purge

Preview what will be removed:

```bash
php scripts/purge_demo_data.php --dry-run
```

Dry-run prints row counts by table and does not delete records.

## Confirmed Purge

Run the confirmed purge:

```bash
php scripts/purge_demo_data.php --confirm
```

Confirm mode:

- creates a SQLite backup first
- verifies the backup file exists
- purges demo business records in FK-safe order
- preserves system/configuration records
- writes a production data marker to `storage/production_data_mode`
- runs `php scripts/check_data_integrity.php`

If backup creation fails, purge aborts.

## Preserved Data

The purge preserves:

- users
- regions / theaters
- capacity targets
- operator modes
- platform health checks
- connector registry
- recommendation tuning rules
- ERP contract validation items

## Removed Data

The purge removes demo/sample operating records, including:

- contacts and organizations
- strategic accounts
- raw signal items and signals
- acquisition targets, hunts, playbooks, and hunt tasks
- opportunities, pursuits, preconstruction profiles, and project packages
- capacity profiles, subcontractors, documents, compliance, and scorecards
- relationships, communications, recommendations, and daily actions
- executive packages and visual decision data
- onboarding records, reviews, and documents
- intelligence warehouse outcomes and lessons
- pilot feedback demo rows
- connector run demo rows
- market intelligence demo rows
- demand/content/distribution demo rows

## Demo Data Identification Strategy

Current legacy demo records are purged by known table strategy. This avoids risky partial deletion in tables that do not yet have universal source metadata.

Going forward:

- connector imported raw items include `seed_source=connector` in notes and remain review-gated
- manual records should be treated as production/manual data
- future demo seed-generated records should either be table-purged by this script or marked with `seed_source=demo` where a table supports source metadata

## First Real Records

After purge, start with real operating assets.

Recommended strategic accounts:

- Comcast
- Charter
- Frontier
- AT&T
- Windstream

Recommended hunting categories:

- Project Managers
- Construction Managers
- OSP Managers
- Aerial Contractors
- Underground Contractors
- Fiber Splicing Contractors
- Directional Boring Contractors

Initial regions:

- Southeast
- Great Lakes
- Southwest

## Real Data Flow

1. Add the first strategic accounts.
2. Add real contacts and organizations.
3. Add capacity providers and subcontractor candidates.
4. Run or import connector data into the raw signal queue.
5. Review raw signals through Signal Quality and Data Quality.
6. Promote only real, reviewed intelligence into targets, hunts, onboarding, and decisions.

## Rollback

Every confirmed purge prints the backup path.

Restore from backup:

```bash
php scripts/restore_database.php storage/backups/<backup-file>.sqlite --confirm
```

Always run integrity after restore:

```bash
php scripts/check_data_integrity.php
```

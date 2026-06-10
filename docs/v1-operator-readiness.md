# V1 Operator Readiness Guide

This guide is for getting current and future operators using the Jackson Intelligence Platform without drowning in module noise.

## Operating Rule

The platform should answer five questions first:

1. Who has work?
2. Who has capacity?
3. Who needs work?
4. Who influences work?
5. What should we do next?

Start every day at `/operating-view`.

## Clean Navigation Doctrine

Navigation is grouped by operator intent:

- Operate: daily executive screens and platform health.
- Command: national/regional command and institutional memory.
- Acquire: signals, targets, hunts, playbooks, and outreach prep.
- Capacity & Work: capacity, relationships, demand, pursuits, preconstruction, and SyncERP handoff.
- Records: system records and settings.

Do not train operators to inspect every module every day. Use Operator Modes to decide the right screen for the job.

## Operator Modes

Executive Mode:
Use `/operating-view`. Decide the top five actions and remove noise.

Regional Owner Mode:
Use `/acquisition-command`. Operators work from the shared company state, then use owner and regional filters to focus assigned priorities. Southwest remains shared until ownership changes.

Hunting Mode:
Use `/hunt-actions`, `/targets`, `/hunting-lists`, `/hunts`, and `/playbooks`. This is for calls, research, qualification, and outcome capture.

Capacity Mode:
Use `/capacity-radar` and `/subcontractor-acquisition`. This is for closing crew gaps and moving subcontractors toward approved/preferred/strategic.

Preconstruction Mode:
Use `/preconstruction` and `/syncerp-integration`. This is for bid readiness and execution handoff packaging only.

## Seed Modes

Demo mode:

```bash
php scripts/seed.php
```

This creates realistic sample data for local validation and training.

Production/minimal mode:

```bash
JIP_SEED_MODE=production php scripts/seed.php
```

This seeds only the baseline regions, users, and capacity targets. Do not load demo records into a live operating database.

Before production use:

- Change all seeded passwords.
- Replace sample organizations, contacts, opportunities, and subcontractors with verified source data.
- Run data integrity checks before operators start working.

## Reliability Rules

SQLite database-writing scripts must run sequentially.

Use:

```bash
php scripts/run_acquisition_cycle.php
```

Do not run these in parallel:

- `run_harvesters.php`
- `process_raw_signals.php`
- `build_acquisition_targets.php`
- rebuild services from dashboards
- `seed.php`

## Data Integrity

Run:

```bash
php scripts/check_data_integrity.php
```

This checks missing profiles, broken relationships, missing recommendations, orphaned activities, missing project package snapshots, missing readiness records, and other release-critical records.

Expected V1 release result:

```text
Summary: PASS (0 fail, 0 warn)
```

## Release Gate

Run the full V1 release check:

```bash
php scripts/release_check.php
```

This runs:

- migrations
- seed
- acquisition cycle
- data integrity
- route smoke tests
- database backup
- operating data export
- PHP lint

## Security Basics

V1 security baseline:

- Session cookies are `HttpOnly`.
- Session cookies use `SameSite=Lax`.
- HTTPS deployments mark session cookies secure.
- Login regenerates session IDs after successful authentication.
- Login errors do not disclose whether the email exists.
- Apache directory indexes are disabled in `public/.htaccess`.
- SQLite database files are ignored by Git.
- Backups and exports are ignored by Git.

Before live use:

- Change seeded passwords immediately.
- Serve only the `public/` directory as the web root.
- Keep `storage/`, `database/`, `app/`, `config/`, and `docs/` outside public access.
- Enable HTTPS.
- Restrict server file permissions to the web user.
- Do not email or upload database backups without encryption.
- Treat exports as sensitive operating intelligence.

## Backup And Export

Create a SQLite database backup:

```bash
php scripts/backup_database.php
```

Backups are written to:

```text
storage/backups/
```

Export key operating records:

```bash
php scripts/export_operating_data.php
```

Exports are written to:

```text
storage/exports/
```

Exports are for operator review and backup. They are not SyncERP exports.

## First 30-Day Operating Plan

Days 1-3: System Readiness

- Run `php scripts/release_check.php`.
- Change seeded passwords.
- Confirm `/operating-view`, `/acquisition-command`, `/capacity-radar`, `/preconstruction`, and `/syncerp-integration` load.
- Review Platform Health and resolve warnings before operator use.

Days 4-7: Operator Training

- Train relationship/opportunity ownership on account, relationship, opportunity, and market workflows.
- Train capacity/readiness ownership on capacity, subcontractor, workforce, readiness, and handoff workflows.
- Review Southwest shared ownership rules.
- Work only the top five actions per day.
- Record outcomes instead of adding unstructured notes.

Days 8-14: Capacity And Relationship Focus

- Review Capacity Radar gaps.
- Move subcontractor candidates through qualification and compliance.
- Review Relationship Graph for project managers, construction managers, utility contacts, and prime contacts.
- Convert high-value signals into targets only when they support Work, Capacity, Need, or Influence.

Days 15-21: Market And Pursuit Discipline

- Review Market Intelligence for 12-24 month fiber backbone signals.
- Confirm which opportunities should be pursued, monitored, or avoided.
- Move pursuit-ready opportunities into Preconstruction.
- Resolve capacity, relationship, margin, and risk blockers before handoff.

Days 22-30: Handoff Readiness

- Review SyncERP Integration packages.
- Confirm capacity snapshots, relationship snapshots, preconstruction snapshots, and readiness profiles exist.
- Use Executive Handoff Brief to identify ready and blocked packages.
- Back up the database weekly.
- Export operating data weekly.

End of Month Review:

- Which sources created useful signals?
- Which relationships created work or access?
- Which subcontractors added deployable capacity?
- Which hunts converted?
- Which pursuits should be avoided next month?
- What are the top five actions for the next 30 days?

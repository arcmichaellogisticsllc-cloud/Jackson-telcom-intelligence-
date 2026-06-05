# Jackson Intelligence Platform

Phase 1 acquisition system and decision support layer for Jackson Telcom.

This app does **not** build SyncERP, billing, production, labor, or ERP workflows. Phase 1 focuses on:

- subcontractor capacity acquisition
- regional relationships
- market intelligence
- signal intake
- opportunities
- recommended actions
- activity tracking

## Regions

- Southeast Region: Mike, GA, AL, FL, TN, NC, SC
- Great Lakes Region: Ron, MI, OH, IN, WI, IL

## Requirements

- PHP 8+
- SQLite PDO extension

No external CRM dependencies are used.

## Setup

```bash
cd /Users/User/Jackson-Intelligence-Platform
php scripts/migrate.php
php scripts/seed.php
php -S localhost:8088 -t public
```

Open:

`http://localhost:8088`

## Production Seed Policy

The production seeder does not create converted organizations, contacts, subcontractors, opportunities, intelligence records, or activities. It only creates:

- region records
- login users
- regional service capacity targets
- signal intelligence records for Signal Center workflow validation
- system-generated recommendations based on the real empty starting state

Signal records are acquisition inputs. They must be reviewed, assigned, converted, or ignored before they become operating records. Real converted acquisition data should be entered through the app or imported from verified Jackson Telcom source files.

## Seeded Login

All seeded users use password:

`password`

Users:

- `admin@jacksontelcom.com` - Admin
- `mike@jacksontlcom.com` - Southeast Owner
- `ron@jacksontelcom.com` - Great Lakes Owner

## Modules

- Authentication
- Command Center
- Signal Center
- Organizations
- Contacts
- Subcontractors
- Opportunities
- Recommendations
- Activities
- Settings

## Command Center

The Command Center is built for acquisition decisions, not generic CRM review.

- Executive Overview: total approved subcontractors, available crews, open opportunities, pipeline value, critical recommendations, capacity gaps by region, opportunities by stage, and recent activity.
- Southeast Command Center: Mike's regional view for GA, AL, FL, TN, NC, and SC.
- Great Lakes Command Center: Ron's regional view for MI, OH, IN, WI, and IL.

Regional dashboards show approved network strength, available crews by service type, capacity gaps, open opportunities, relationships needing follow-up, compliance issues, and top recommended daily actions.

Command Center signal widgets show:

- New Signals
- Critical Signals
- Signals Needing Review
- Signals Assigned To Me
- Signals Converted This Month

## Signal Center

Signal Center is the top-level acquisition intake module. JAS is not a CRM; it converts market, relationship, capacity, and opportunity signals into action.

Signal types:

- Capacity
- Opportunity
- Relationship
- Market

Source types:

- Facebook Marketplace
- LinkedIn
- Industry News
- Referral
- Conference
- Website Form
- Manual Entry
- Government Data
- Contractor Intelligence
- Equipment Listing
- Other

Signal workflow:

- New
- Reviewed
- Assigned
- Converted
- Ignored

Signal conversion paths:

- Capacity signals convert to organizations or subcontractor prospects.
- Relationship signals convert to contacts or organizations.
- Opportunity signals convert to opportunities or organizations.
- Market signals convert to opportunities or intelligence records.

Each signal has a record timeline. Workflow moves and conversions create activity entries, and users can add notes from the signal detail page.

## Signal Scoring

Signals are scored automatically when created or updated.

The scoring engine calculates:

- confidence score, 0-100
- impact score, 0-100
- priority: Low, Medium, High, or Critical

Scoring considers signal type, source reliability, source URL presence, organization/contact detail, and telecom construction keywords such as bucket truck, splicing trailer, directional drill, broadband grant, utility expansion, BEAD funding, promotion, referral, and municipal fiber planning.

## Capacity Scoring

Each region receives a capacity score and category:

- Critical
- Weak
- Stable
- Strong

The score considers approved subcontractor count, total approved available crews, service coverage, subcontractor availability, and insurance/W9 compliance. Only approved or preferred subcontractors with approved insurance, approved W9, and active availability contribute to approved available capacity.

Service capacity is tracked separately for:

- Aerial
- Underground
- Fiber Splicing
- Emergency Restoration
- Traffic Control

## Capacity Gap Engine

Regional target capacity is configurable in Settings. Default targets are:

Southeast:

- Aerial: 10 crews
- Underground: 6 crews
- Fiber Splicing: 5 crews
- Emergency Restoration: 3 crews
- Traffic Control: 3 crews

Great Lakes:

- Aerial: 8 crews
- Underground: 5 crews
- Fiber Splicing: 4 crews
- Emergency Restoration: 3 crews
- Traffic Control: 2 crews

When approved available capacity is below target, the system generates capacity recommendations such as recruiting additional aerial, underground, splicing, restoration, or traffic control crews.

## Opportunity Pursuit Score

Each opportunity receives a pursuit score and label:

- Pursue Aggressively
- Pursue Selectively
- Monitor
- Avoid

The score considers estimated margin, probability, relationship strength, capacity availability, estimated value, and risk notes. This helps separate opportunities that should be actively pursued from those that need more capacity, better relationships, or risk review first.

## Recommendation Engine v1

The engine regenerates recommendations from operational rules:

- Regional capacity below target by service type
- Fewer than 5 approved subcontractors in a region
- Stale or missing contact follow-up
- Qualified/Pursuit opportunities without next action
- Opportunity capacity required exceeds approved available crew count
- Missing subcontractor insurance or W9
- Missing critical data on organizations, contacts, subcontractors, and opportunities
- Opportunity pursuit risk requiring review
- New, critical, stale, or high-confidence signals

Recommendation types include:

- Recruit Capacity
- Follow Up Relationship
- Resolve Compliance
- Assign Opportunity Next Action
- Avoid Opportunity Risk
- Review Pursuit

Each recommendation includes a category, priority, priority score, trigger detail, business reason, suggested next action, assigned owner, and status. Regional dashboards show the top five open actions for the assigned owner.

Run seed or use the Recommendations screen to regenerate. On a clean production baseline, each region will show a capacity acquisition recommendation until real approved subcontractor capacity is entered.

Signal recommendations include:

- review new signals
- act on critical signals
- convert high-confidence signals
- clear signals that remain New for more than 7 days

## Activity Timelines

Signals, organizations, contacts, subcontractors, and opportunities include record-level activity timelines. Users can add notes from record detail pages, and activities show date, activity type, user, note, and related record context.

## Database

SQLite database path:

`storage/jackson_intelligence.sqlite`

Migrations:

`database/migrations/001_create_schema.sql`

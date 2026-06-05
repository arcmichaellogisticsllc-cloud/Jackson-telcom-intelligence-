# Jackson Intelligence Platform

Phase 1 acquisition system and decision support layer for Jackson Telcom.

This app does **not** build SyncERP, billing, production, labor, or ERP workflows. Jackson Intelligence Platform presents Jackson Telcom with a national footprint while focusing daily action through three operating theaters. Phase 1 focuses on:

- traffic acquisition
- subcontractor capacity acquisition
- regional relationships
- market intelligence
- signal intake
- opportunities
- recommended actions
- activity tracking

## Platform Structure

Jackson Intelligence Platform is structured as an acquisition intelligence system:

- National Command Center
- Regions / Theaters
- Traffic Engine
- Signal Center
- Traffic Engine records: Keywords, Content Ideas, Outreach Targets, Outreach Sequences
- Capacity Acquisition
- Relationship Intelligence
- Opportunity Intelligence
- Decision Support Layer
- SyncERP as the last integration layer only

```text
Jackson Intelligence Platform
├── National Command Center
├── Regions / Theaters
│   ├── Southeast: Mike, GA, AL, FL, TN, NC, SC
│   ├── Great Lakes: Ron, MI, OH, IN, WI, IL
│   └── Southwest: Houston hub, TX, OK, LA, NM
├── Traffic Engine
├── Signal Center
├── Capacity Acquisition
├── Relationship Intelligence
├── Opportunity Intelligence
├── Decision Support Layer
└── SyncERP: last integration layer only
```

## Regions / Theaters

- Southeast: owner Mike, states GA, AL, FL, TN, NC, SC
- Great Lakes: owner Ron, states MI, OH, IN, WI, IL
- Southwest: Houston, TX hub, states TX, OK, LA, NM

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
- keyword, content, outreach target, and outreach sequence records
- signal intelligence records for Signal Center workflow validation
- system-generated recommendations based on seeded signals and real empty converted operating records

Traffic and signal records are acquisition inputs. They must be reviewed, assigned, converted, published, contacted, or ignored before they become operating records. Real converted acquisition data should be entered through the app or imported from verified Jackson Telcom source files.

## Seeded Login

All seeded users use password:

`password`

Users:

- `admin@jacksontelcom.com` - Admin
- `mike@jacksontlcom.com` - Southeast Owner
- `ron@jacksontelcom.com` - Great Lakes Owner

## Modules

- Authentication
- National Command Center
- Traffic Engine
- Signal Center
- Capacity Acquisition
- Relationship Intelligence
- Opportunity Intelligence
- Decision Support
- Activities
- Settings
- Records: Organizations, Contacts, Subcontractors, Opportunities

## National Command Center

The National Command Center is built for acquisition decisions, not generic CRM review.

- Executive Overview: total approved subcontractors, available crews, open opportunities, pipeline value, critical recommendations, capacity gaps by region, opportunities by stage, and recent activity.
- Southeast Command Center: Mike's regional view for GA, AL, FL, TN, NC, and SC.
- Great Lakes Command Center: Ron's regional view for MI, OH, IN, WI, and IL.
- Southwest Command Center: Houston hub view for TX, OK, LA, and NM.

Regional dashboards show approved network strength, available crews by service type, capacity gaps, open opportunities, relationships needing follow-up, compliance issues, and top recommended daily actions.

National Command Center signal widgets show:

- New Signals
- Critical Signals
- Signals Needing Review
- Signals Assigned To Me
- Signals Converted This Month

## Traffic Engine

Traffic Engine is the source layer for demand and contractor discovery:

- SEO
- Content Strategy
- Landing Pages
- Contractor Searches
- Regional Pages
- Outreach Campaigns

Traffic Engine tracks:

- Keywords by intent, theater, state, city, rank, and acquisition priority
- Content Ideas by audience, channel, target keyword, and status
- Outreach Targets by source, target type, owner, status, recommended message, and next action
- Outreach Sequences as planned workflows only; no emails, SMS, or messages are sent by Phase 1

SEO, content, and outreach feed the acquisition system by creating signals, target lists, regional pages, contractor searches, and recommended actions.

## Signal Center

Signal Center is the top-level acquisition intake module. JAS is not a CRM; it converts market, relationship, capacity, and opportunity signals into action.

Signal types:

- Capacity
- Opportunity
- Relationship
- Market
- SEO
- Content
- Outreach

Source types:

- Google Search
- Google Business Profile
- Facebook Marketplace
- LinkedIn
- Industry Forum
- YouTube
- Broadband Grant
- Utility Announcement
- Equipment Listing
- New Business Filing
- Hiring Activity
- Manual Entry
- Industry News
- Referral
- Conference
- Website Form
- Government Data
- Contractor Intelligence
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
- SEO signals convert to opportunities or intelligence records.
- Content signals convert to opportunities or intelligence records.
- Outreach signals convert to opportunities or intelligence records.

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

Southwest:

- Aerial: 8 crews
- Underground: 6 crews
- Fiber Splicing: 4 crews
- Emergency Restoration: 3 crews
- Traffic Control: 3 crews

When approved available capacity is below target, the system generates capacity recommendations such as recruiting additional aerial, underground, splicing, restoration, or traffic control crews.

## Capacity Acquisition

Capacity Acquisition prioritizes deployable capacity before opportunity execution:

- Subcontractors
- Workforce
- Equipment
- Vendors
- Capacity Radar

## Relationship Intelligence

Relationship Intelligence organizes who matters, who owns the relationship, and where influence exists:

- Organizations
- Contacts
- Influence Graph
- Regional Ownership

## Opportunity Intelligence

Opportunity Intelligence tracks possible work before project execution:

- Grants
- Utility Expansion
- Prime Awards
- Project Requests
- Bid Opportunities

## Opportunity Pursuit Score

Each opportunity receives a pursuit score and label:

- Pursue Aggressively
- Pursue Selectively
- Monitor
- Avoid

The score considers estimated margin, probability, relationship strength, capacity availability, estimated value, and risk notes. This helps separate opportunities that should be actively pursued from those that need more capacity, better relationships, or risk review first.

## Decision Support Layer

The Decision Support Layer regenerates recommendations from operational rules:

- Regional capacity below target by service type
- Fewer than 5 approved subcontractors in a region
- Stale or missing contact follow-up
- Qualified/Pursuit opportunities without next action
- Opportunity capacity required exceeds approved available crew count
- Missing subcontractor insurance or W9
- Missing critical data on organizations, contacts, subcontractors, and opportunities
- Opportunity pursuit risk requiring review
- New, critical, stale, or high-confidence signals
- Region traffic score below target
- Keyword without assigned content
- Southwest low coverage score requiring Houston-focused landing pages and subcontractor outreach
- SEO or Content signals requiring a content asset

Recommendation types include:

- Recruit Capacity
- Follow Up Relationship
- Resolve Compliance
- Assign Opportunity Next Action
- Avoid Opportunity Risk
- Review Pursuit

Recommendation categories include:

- Capacity
- Relationship
- Opportunity
- Compliance
- Market
- SEO
- Content
- Outreach
- Regional Expansion

Each recommendation includes a category, priority, priority score, trigger detail, business reason, suggested next action, assigned owner, and status. Regional dashboards show the top five open actions for the assigned owner.

Run seed or use the Recommendations screen to regenerate. On a clean production baseline, each region will show a capacity acquisition recommendation until real approved subcontractor capacity is entered.

Signal recommendations include:

- review new signals
- act on critical signals
- convert high-confidence signals
- clear signals that remain New for more than 7 days

Traffic recommendations include:

- create landing pages for priority contractor searches
- publish regional broadband funding content
- build subcontractor SEO pages
- contact equipment sellers discovered through search or marketplace signals
- create outreach targets and planned sequence steps

Decision Support answers:

- what to pursue
- who to contact
- what market to strengthen
- what capacity to recruit
- what content to publish
- what outreach to send
- what projects to avoid

## SyncERP Boundary

SyncERP is intentionally not built in Phase 1. It remains the last integration layer only, after acquisition intelligence, capacity acquisition, relationship intelligence, opportunity intelligence, and decision support are working.

## Activity Timelines

Signals, organizations, contacts, subcontractors, and opportunities include record-level activity timelines. Users can add notes from record detail pages, and activities show date, activity type, user, note, and related record context.

## Database

SQLite database path:

`storage/jackson_intelligence.sqlite`

Migrations:

`database/migrations/001_create_schema.sql`

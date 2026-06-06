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
- Acquisition Harvesters
- Acquisition Targets
- Hunting Lists
- Hunts
- Playbooks
- Today's Hunt Actions
- Traffic Engine
- Signal Center
- Signal Quality Engine
- Watchlists
- Escalation Center
- Daily Intelligence Briefing
- Capacity Radar
- Subcontractor Acquisition Engine
- Relationship & Influence Intelligence Engine
- Demand & Distribution Intelligence Engine
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
├── Acquisition Harvesters
├── Acquisition Targets
├── Hunting Lists
├── Hunts / Playbooks
├── Traffic Engine
├── Signal Center
├── Signal Quality / Watchlists / Escalations
├── Capacity Radar
├── Subcontractor Acquisition Engine
├── Relationship & Influence Intelligence Engine
├── Demand & Distribution Intelligence Engine
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

## Seed Policy

The seeder creates acquisition operating data for local validation and development. It includes:

- region records
- login users
- regional service capacity targets
- keyword, content, outreach target, and outreach sequence records
- signal source registry records
- harvester runs and raw signal items
- signal intelligence records for Signal Center workflow validation
- acquisition targets, hunts, playbooks, and hunt tasks
- capacity profiles, trust scores, and capacity radar targets
- subcontractor candidates, scorecards, compliance profiles, document metadata, and preferred network scoring
- system-generated recommendations based on the seeded acquisition records

Seeded records are realistic sample operating data for exercising the workflow. Before production use, replace seeded sample records with verified Jackson Telcom source files, imports, or app-entered records.

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
- Acquisition Harvesters
- Acquisition Targets
- Hunting Lists
- Traffic Engine
- Signal Center
- Signal Quality Engine
- Watchlists
- Escalations
- Daily Intelligence Briefing
- Capacity Radar
- Subcontractor Acquisition Engine
- Relationship & Influence Intelligence Engine
- Demand & Distribution Intelligence Engine
- Capacity Acquisition
- Relationship Intelligence
- Opportunity Intelligence
- Decision Support
- Activities
- Settings
- Records: Organizations, Contacts, Subcontractors, Opportunities

## Acquisition Harvesting Framework

Acquisition Harvesters are the automated fuel layer for JAS. Manual entry is reserved for physical traffic only:

- referrals
- conferences
- jobsite conversations
- phone calls
- face-to-face networking

All other acquisition inputs should be represented as sources that can be harvested, imported, or semi-automated.

The harvesting pipeline is:

1. Signal Source defines where intelligence should come from.
2. Harvester Run records when the source was checked and what happened.
3. Raw Signal Item stores unprocessed harvested data.
4. Signal Processing classifies, routes, scores, and converts raw items into clean Signals.
5. Acquisition Target Pipeline converts relevant Signals into scored Targets.
6. Hunting Lists prioritize Targets for Mike, Ron, Admin, and the future Southwest owner.
7. Recommendation Engine turns Signals and Targets into owner actions.

Signal sources track source type, target category, collection method, URL/search query, cadence, status, last run, next run, and run counts.

Harvester adapters live in `app/Services/Harvesters/`. Phase 1 uses mock/sample adapters only:

- GoogleSearchHarvester
- SecretaryOfStateHarvester
- JobBoardHarvester
- EquipmentListingHarvester
- BroadbandGrantHarvester
- PrimeAwardHarvester
- ManualPhysicalTrafficHarvester
- CsvImportHarvester

Real connectors can replace these adapters later without changing the controller or processing pipeline.

CLI commands:

```bash
php scripts/run_harvesters.php
php scripts/process_raw_signals.php
php scripts/import_csv_signals.php /path/to/file.csv <signal_source_id>
```

CSV imports must include these headers when available:

`title,description,source_type,source_url,company_name,contact_name,phone,email,city,state,notes`

CSV rows become Raw Signal Items first, then flow through the same processor as harvested records.

This framework exists before Capacity Radar because Capacity Radar needs fuel: source coverage, raw market activity, contractor movement, equipment signals, funding signals, relationship movement, SEO demand, and outreach targets.

## Acquisition Target Pipeline

The Acquisition Target Pipeline is the bridge between harvested intelligence and daily hunting.

```text
Signals
↓
Acquisition Targets
↓
Daily Hunting Lists
↓
Outreach Preparation
↓
Organization / Contact / Subcontractor / Opportunity / Outreach Target
```

Targets are scored with:

- Acquisition Score
- Confidence Score
- Strategic Value
- Urgency
- Capacity Value
- Relationship Value
- Opportunity Value

Targets are deduplicated by organization, phone, email, website, region, and target type. Signal-to-target conversion is handled by `app/Services/AcquisitionTargetService.php`.

Hunting lists:

- National Hunting List: top targets across all theaters
- Mike's Southeast Hunting List: Southeast targets only
- Ron's Great Lakes Hunting List: Great Lakes targets only
- Southwest Hunting List: Southwest targets assigned to Future Southwest Owner or Admin

Outreach Prep does not send messages. It only prepares a suggested opening message, call notes, reason the target matters, discovery questions, and recommended channel.

Targets can convert into organizations, contacts, subcontractor profiles, opportunities, or outreach targets.

CLI command:

```bash
php scripts/build_acquisition_targets.php
```

## Signal Quality Engine

The Signal Quality Engine exists to prevent acquisition overload.

```text
100,000 Signals
↓
5,000 Relevant Signals
↓
500 Acquisition Targets
↓
50 Hunts
↓
5 Critical Actions Today
```

JAS separates signal from noise before creating more targets. Every signal receives a Signal Quality Profile with source quality, signal value, strategic value, capacity value, opportunity value, relationship value, revenue value, confidence, impact, accumulation score, and classification.

Classifications:

- Escalate: high-value intelligence that should receive same-day owner attention.
- Hunt: relevant intelligence that should become a target or active pursuit input.
- Watch: useful future intelligence that should be monitored without creating more noise.
- Archive: low-value, duplicate, unrelated, or stale intelligence.

Signal accumulation connects multiple signals around the same organization or contact. One weak signal may stay Watch. Several related signals can become Hunt. Many strong signals can become Escalate. Example: a contractor hiring splicers, selling bucket trucks, opening an office, and winning a project crosses an escalation threshold.

Source Quality Profiles score each source by what it produces. Sources improve when they produce escalations, hunt signals, converted targets, opportunities, or subcontractors. Sources lose value when they create archive/noise or never convert.

Watchlists preserve "meat on the bone" intelligence. This keeps potentially valuable organizations, contacts, and signals visible without forcing them into active hunts too early.

The Escalation Center shows why something escalated, the supporting signals behind it, the owner, and the recommended next action.

The Daily Intelligence Briefing is the owner-facing operating screen:

- Mike sees Southeast escalations, hunts, watchlist changes, and top recommendations.
- Ron sees Great Lakes escalations, hunts, watchlist changes, and top recommendations.
- Admin sees national escalations, hunts, watchlists, and regional comparison.

Signal decay reduces influence as signals age. At 30 days, influence starts dropping. At 60 days, it drops further. At 90 days, a signal moves toward archive unless reinforced by newer related signals.

Signal Quality comes before Capacity Radar because Radar should measure verified, high-value capacity intelligence instead of raw harvested noise.

## Capacity Radar

Capacity Radar answers the operating question:

`What capacity gaps prevent us from pursuing opportunities today?`

It tracks both internal Jackson capacity and external capacity from subcontractors, vendors, equipment providers, and specialty providers. The goal is not to create a vendor database. The goal is to determine whether Jackson can execute before committing to a pursuit.

Capacity disciplines tracked:

- Aerial
- Underground
- Fiber Splicing
- Emergency Restoration
- Traffic Control
- Directional Boring
- Mowing / ROW
- Inspection
- QC
- Engineering
- Make Ready
- Drop Crews

Capacity Profiles represent internal teams, subcontractors, vendors, equipment providers, and specialty providers. Each profile can track market, theater, owner, status, mobilization readiness, travel radius, states served, markets served, equipment counts, discipline crew counts, and trust score.

Mobilization readiness values:

- 24 Hours
- 72 Hours
- 1 Week
- 2 Weeks
- 30 Days
- 60 Days

Trust scoring considers safety, quality, communication, responsiveness, production, documentation, and relationship history. Trust categories are:

- Critical Risk
- Developing
- Reliable
- Preferred
- Strategic Partner

Capacity Radar calculates reactive and predictive gaps:

- Reactive gap: target crews now minus available now
- Predictive 30-day gap: target crews in 30 days minus available within 30 days
- Predictive 60-day gap: target crews in 60 days minus available within 60 days

Severity levels are None, Low, Medium, High, and Critical. Critical or High gaps become blocking pursuit risks and feed recommendations such as recruiting crews, reviewing capacity targets, promoting strong providers, or avoiding low-trust capacity.

Capacity Radar connects with the existing acquisition system:

- Escalate/Hunt capacity signals suggest creating or updating Capacity Profiles.
- Subcontractor and equipment-seller Acquisition Targets show potential capacity contribution.
- Capacity Hunts show which gaps they are intended to solve.
- Recommendations convert gaps into owner action.

Capacity Radar comes after Signal Quality because radar should work from higher-value intelligence, not raw noise. SyncERP remains last because this phase is still acquisition and decision support, not production execution, billing, or ERP workflow.

## Subcontractor Acquisition Engine

The Subcontractor Acquisition Engine turns intelligence into deployable subcontractor capacity.

```text
Signals
↓
Targets
↓
Hunts
↓
Subcontractor Acquisition
↓
Qualification
↓
Compliance
↓
Approved Capacity
↓
Preferred Network
↓
Strategic Partner Network
```

Pipeline stages:

- Prospect
- Researching
- Qualified
- Documents Requested
- Compliance Review
- Approved
- Preferred
- Strategic Partner
- Inactive
- Rejected

Subcontractor profiles track company information, ownership/contact information, theater coverage, states and markets served, service disciplines, deployable crew counts, and equipment summaries. Disciplines include aerial, underground, fiber splicing, directional boring, emergency restoration, traffic control, mowing / ROW, inspection, QC, engineering, make ready, and drop crews.

Qualification scorecards score each candidate from 0-10 across service fit, geographic fit, crew capacity, mobilization speed, equipment availability, insurance readiness, W9 readiness, communication, experience, and safety. The total creates a 0-100 qualification score with these results:

- Not Fit
- Weak
- Qualified
- Preferred Candidate
- Strategic Candidate

Compliance profiles track W9, COI, business license, safety program, MSA, and NDA status as Missing, Requested, Submitted, Approved, or Expired. Document records store the document category, file name, upload date, expiration date, status, and storage path. Phase 1 records document metadata only; it does not integrate external document storage.

The Preferred Network engine ranks subcontractors as Prospect, Qualified, Approved, Preferred, or Strategic Partner. Promotion logic considers qualification score, required document approval, trust score, responsiveness, capacity contribution, regional importance, and strategic value.

Capacity contribution scoring answers how much a subcontractor can help close current gaps. It considers crew count, available crews, mobilization readiness, disciplines covered, equipment count, and trust score. Categories are Low, Medium, High, and Critical.

Acquisition Targets can convert directly into Subcontractor Candidates while preserving source signal, hunt history, qualification notes, outreach notes, source URL, region, owner, and contact information. Capacity Hunts show discovered, qualified, approved, and added crew capacity so Jackson can measure hunting performance.

Capacity Radar uses subcontractor network levels to show how much capacity exists at each maturity level by region and discipline. Command Centers surface new subcontractor candidates, compliance issues, capacity added this month, strategic partner candidates, and preferred network growth.

SyncERP remains last. This phase establishes qualified, compliant, deployable subcontractor capacity before production execution, billing, or ERP workflows exist.

## Relationship & Influence Intelligence Engine

Relationships are treated as influence assets, not passive CRM contacts. Every relationship should answer:

- Can this relationship create work?
- Can this relationship create capacity?
- Can this relationship create market intelligence?
- Can this relationship create access to primes, utilities, or projects?
- What should Mike or Ron do next?

The Relationship Graph organizes national and regional influence:

- National Relationship Graph
- Southeast Relationship Graph for Mike
- Great Lakes Relationship Graph for Ron
- Southwest Relationship Graph for shared Mike/Ron ownership

Relationship Intelligence Profiles connect contacts and organizations to scoring, objectives, risks, win conditions, and owner actions.

Scoring model:

- Decision Authority Score
- Influence Score
- Access Score
- Trust Score
- Strategic Value Score
- Relationship Value Score
- Relationship Priority: Low, Medium, High, Critical
- Relationship Status: Unknown, Cold, Developing, Warm, Strong, Strategic

Project Managers, Construction Managers, OSP Managers, Program Managers, Operations Managers, Procurement contacts, Executives, Field Supervisors, Engineers, Estimators, and Subcontractor Coordinators receive explicit influence roles. Project Managers score highly because they commonly expose real work access.

Every relationship requires a primary objective:

- Project Access
- Prime Access
- Utility Access
- Market Intelligence
- Capacity Access
- Future Opportunity

Win conditions track when a relationship can create an operating advantage:

- Looking for Work
- Looking for Workers
- Ready to Mobilize
- Prime Access Opened
- Utility Access Opened
- Market Intelligence Provided
- Future Opportunity Created

Relationship risks keep influence from becoming fragile:

- Single Point of Failure
- No Recent Contact
- Low Trust
- Low Access
- Weak Strategic Value
- Relationship Owner Missing
- Contact Role Unknown
- Objective Missing

Relationship Actions turn influence into daily work. Actions include calls, email, LinkedIn engagement, direct outreach, conference follow-up, research, meetings, content share, asking for work, asking for capacity, and asking for market intelligence. No real outreach is sent in this phase.

Aggressive Relationship Creation Signals capture non-passive relationship generation from LinkedIn engagement, direct outreach, conference attendance, convention attendance, thought leadership, cold calls, website visits, content interactions, referrals, manual physical traffic, and other sources. These signals can convert into contacts and relationship intelligence profiles.

The Daily Intelligence Briefing now surfaces top relationships to strengthen today, project managers to contact, utility access contacts, prime access contacts, relationship risks, and aggressive relationship creation actions. The goal is action, not raw contact volume.

This feeds Decision Support Layer V2 by generating recommendations for high-value relationships, project manager discoveries, missing objectives, missing actions, stale utility/prime access, single-point-of-failure risk, and relationships that can open work, capacity, or market intelligence.

## Demand & Distribution Intelligence Engine

The Demand & Distribution Engine turns JAS from a pure intelligence collector into a controlled ecosystem participant. The same places Jackson harvests intelligence can become distribution channels, but nothing is auto-published.

Closed-loop acquisition flywheel:

```text
Traffic
↓
Signals
↓
Intelligence
↓
Content
↓
Distribution
↓
Relationships
↓
Capacity
↓
Opportunities
↓
More Signals
```

Channel Registry tracks where Jackson can participate:

- Website
- LinkedIn
- Facebook Group
- Industry Forum
- Email Newsletter
- YouTube
- Conference
- Industry Publication
- Reddit
- Other

Channels are scored by relationship generation, capacity generation, opportunity generation, engagement, and downstream attribution. Quality categories are:

- Elite
- High Value
- Moderate
- Low Value
- Noise

The system prioritizes Elite and High Value channels for owner attention. Low-value or noisy channels generate review or pause recommendations.

Content Opportunities are generated from signals, escalations, watchlists, capacity gaps, relationship trends, broadband grants, prime awards, utility expansion, and demand trends. Each opportunity tracks audience, region, strategic value, expected capacity impact, expected relationship impact, expected opportunity impact, and review status.

Content Drafts are review-only. Draft statuses are Draft, Review Needed, Approved, Rejected, and Published. The platform does not auto-publish content. Human review is always required before publication or distribution.

Distribution Plans answer where a content asset should go. They connect content to channels with audience match score, priority, distribution reason, and status. Statuses are Planned, Approved, Scheduled, Published, and Skipped. Moving a plan forward is still a human workflow.

Demand Signals track topics, search intent, regional demand, trend direction, audience, suggested content, and suggested distribution. The SEO foundation focuses on relationship creation, capacity creation, and opportunity creation instead of rankings alone.

Acquisition Flywheel Attribution tracks whether reviewed/published assets created:

- signals
- targets
- relationships
- subcontractors
- opportunities

Relationship Distribution recommends who should see content: utility content routes toward utility contacts, prime content toward prime contacts, subcontractor content toward the capacity network, and workforce content toward recruiting audiences.

The Daily Demand Briefing shows top content opportunities, top demand signals, top channels, distribution opportunities, content needing review, and recommended publication/participation actions. Recommendations include LinkedIn engagement, forum participation, Facebook group participation, conference follow-up, industry commentary, and thought leadership opportunities. These are recommendations only.

## Acquisition Hunt Engine

The Hunt Engine defines how Jackson pursues acquisition targets. A target is a scored lead created from harvested intelligence. A hunt is an owner-led operating campaign with an objective, target goal, dates, success metric, and assigned targets.

```text
Signals
↓
Acquisition Targets
↓
Hunts
↓
Playbooks
↓
Tasks
↓
Qualification
↓
Outcome
↓
Conversion
```

Hunts support capacity, opportunity, relationship, workforce, equipment seller, vendor, utility, and prime contractor acquisition. Example hunts include Southeast aerial capacity expansion, Great Lakes fiber splicing capacity, Houston underground contractor acquisition, and national prime contractor relationships.

Playbooks are repeatable operating procedures for working a target. They define the opening script, qualification questions, disqualification rules, required documents, conversion goal, and ordered steps. Outreach Sequences are planned campaign templates. Playbooks are the human execution workflow used during hunting. Neither sends email, SMS, LinkedIn, or Facebook messages.

Playbook steps generate Hunt Tasks. The Today's Hunt Actions screen is the working queue for Mike, Ron, Admin, and future regional owners. Each task shows target, hunt, current step, recommended channel, instructions, questions, due date, owner, and completion notes.

Qualification scorecards are tied to the playbook and target type. They score fit for subcontractor capacity, equipment seller relevance, prime contractor value, utility influence, workforce readiness, or vendor usefulness. Results are stored as Strong Fit, Possible Fit, Weak Fit, or Not Fit.

Outcomes include converted records, not fit, no response, future follow-up, bad data, and duplicate. Conversion still happens through the target detail page, preserving source signal, region, owner, notes, source URL, and contact details.

This phase comes before Capacity Radar because hunting execution creates the verified capacity, relationship, and opportunity outcomes that Capacity Radar will later measure. SyncERP remains last because acquisition intelligence must first identify what to pursue, who to contact, and what capacity to recruit.

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
- Acquisition Target

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

## Decision Support Layer V2

Decision Support V2 is the operating brain above the module-level recommendation engine.

Recommended Actions are system findings. Daily Actions are the smaller executive list that Mike, Ron, shared Southwest ownership, and Admin should act on first.

Decision Support V2 adds:

- Daily Actions ranked by Decision Score
- National, Southeast, Great Lakes, and Southwest decision dashboards
- Executive Daily Brief
- Regional Strategy Scorecards
- Growth Blockers
- Pursue / Avoid decisions
- Capacity recruitment recommendations
- Content review and distribution decisions
- Relationship decisions
- Action completion, dismissal, and follow-up creation

Decision Score is a weighted score from impact, urgency, confidence, strategic value, capacity gap severity, relationship value, opportunity value, demand value, and risk severity.

Growth Blockers show what is stopping growth:

- Capacity Gap
- Relationship Risk
- Opportunity Risk
- Demand Gap
- Compliance Issue
- Hunt Stagnation
- Signal Quality Issue
- Regional Coverage Gap

Regional Strategy Scorecards answer: is this region ready to grow? The score combines capacity, relationships, opportunity position, demand, signal quality, subcontractor network health, hunt execution, and risk.

Pursue / Avoid decisions compare pursuit value against avoid risk. The system recommends Pursue Aggressively, Pursue Selectively, Monitor, or Avoid based on margin, probability, relationship access, capacity availability, strategic fit, compliance risk, and capacity gaps.

Capacity recruitment recommendations answer: what capacity should we recruit next? Suggested sources include Hunts, Acquisition Targets, Watchlists, Demand Content, and Direct Outreach.

Content decisions never publish automatically. They only recommend Draft, Review, Publish, Distribute, or Archive, with human review required before publication or distribution.

Relationship decisions identify which influence assets should be contacted, strengthened, assigned objectives, de-risked, or asked for work, capacity, or market intelligence.

## SyncERP Boundary

SyncERP is intentionally not built in Phase 1. It remains the last integration layer only, after acquisition intelligence, capacity acquisition, relationship intelligence, opportunity intelligence, and decision support are working.

## Activity Timelines

Signals, organizations, contacts, subcontractors, and opportunities include record-level activity timelines. Users can add notes from record detail pages, and activities show date, activity type, user, note, and related record context.

## Database

SQLite database path:

`storage/jackson_intelligence.sqlite`

Migrations:

`database/migrations/001_create_schema.sql`

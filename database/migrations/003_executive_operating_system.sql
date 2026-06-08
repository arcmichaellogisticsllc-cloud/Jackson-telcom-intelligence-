CREATE TABLE IF NOT EXISTS communication_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  linked_record_type TEXT NOT NULL,
  linked_record_id INTEGER,
  contact_id INTEGER,
  organization_id INTEGER,
  region_id INTEGER,
  communication_type TEXT NOT NULL CHECK(communication_type IN ('Note','Call','Meeting','Follow-Up','Email Draft','LinkedIn Draft','Text Draft','Relationship Action')),
  summary TEXT NOT NULL,
  outcome TEXT,
  next_step TEXT,
  owner TEXT,
  communication_date TEXT DEFAULT CURRENT_TIMESTAMP,
  draft_subject TEXT,
  draft_body TEXT,
  human_review_required INTEGER DEFAULT 1,
  status TEXT DEFAULT 'Open' CHECK(status IN ('Open','Completed','Skipped','Archived')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(contact_id) REFERENCES contacts(id),
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS network_relationships (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  from_organization_id INTEGER,
  to_organization_id INTEGER,
  from_contact_id INTEGER,
  to_contact_id INTEGER,
  region_id INTEGER,
  relationship_type TEXT NOT NULL CHECK(relationship_type IN ('Utility to Engineering Firm','Engineering Firm to Prime Contractor','Prime Contractor to Subcontractor','Utility to Prime','Prime to Capacity Provider','Influencer to Opportunity','Other')),
  strength_score INTEGER DEFAULT 0,
  trust_score INTEGER DEFAULT 0,
  recency_score INTEGER DEFAULT 0,
  confidence_score INTEGER DEFAULT 0,
  network_influence_score INTEGER DEFAULT 0,
  notes TEXT,
  last_verified_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(from_organization_id) REFERENCES organizations(id),
  FOREIGN KEY(to_organization_id) REFERENCES organizations(id),
  FOREIGN KEY(from_contact_id) REFERENCES contacts(id),
  FOREIGN KEY(to_contact_id) REFERENCES contacts(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS forecast_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  forecast_type TEXT NOT NULL CHECK(forecast_type IN ('Capacity','Opportunity','Relationship','Demand','Regional')),
  forecast_window TEXT NOT NULL CHECK(forecast_window IN ('30 Days','90 Days','180 Days','365 Days')),
  forecast_title TEXT NOT NULL,
  forecast_value REAL DEFAULT 0,
  confidence_score INTEGER DEFAULT 0,
  trend TEXT CHECK(trend IN ('Rising','Stable','Falling')),
  forecast_summary TEXT,
  recommended_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS ownership_assignments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  record_type TEXT NOT NULL CHECK(record_type IN ('Relationship','Opportunity','Capacity Provider','Market','Hunt','Strategic Account')),
  record_id INTEGER NOT NULL,
  region_id INTEGER,
  primary_owner TEXT NOT NULL,
  secondary_owner TEXT,
  ownership_reason TEXT,
  status TEXT DEFAULT 'Active' CHECK(status IN ('Active','Needs Review','Transferred','Archived')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS strategic_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  account_name TEXT NOT NULL,
  account_type TEXT NOT NULL CHECK(account_type IN ('Utility','Prime Contractor','Telecom Provider','Electric Cooperative','Municipal Broadband','Engineering Firm','Strategic Partner','Other')),
  region_id INTEGER,
  relationship_coverage_score INTEGER DEFAULT 0,
  opportunity_volume_score INTEGER DEFAULT 0,
  capacity_demand_score INTEGER DEFAULT 0,
  influence_coverage_score INTEGER DEFAULT 0,
  strategic_score INTEGER DEFAULT 0,
  primary_owner TEXT,
  next_best_action TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS regional_dominance_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER NOT NULL UNIQUE,
  relationship_strength_score INTEGER DEFAULT 0,
  capacity_strength_score INTEGER DEFAULT 0,
  opportunity_strength_score INTEGER DEFAULT 0,
  demand_strength_score INTEGER DEFAULT 0,
  influence_strength_score INTEGER DEFAULT 0,
  regional_dominance_score INTEGER DEFAULT 0,
  dominance_category TEXT CHECK(dominance_category IN ('Weak','Developing','Competitive','Strong','Dominant')),
  top_investment TEXT,
  top_risk TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS strategic_recommendations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recommendation_title TEXT NOT NULL,
  recommendation_category TEXT NOT NULL CHECK(recommendation_category IN ('Capacity Investment','Relationship Investment','Market Expansion','Strategic Account','Forecast Risk','Network Influence','Demand Investment','Avoidance')),
  region_id INTEGER,
  priority TEXT NOT NULL CHECK(priority IN ('Low','Medium','High','Critical')),
  reason TEXT,
  recommended_action TEXT,
  expected_impact TEXT,
  owner TEXT,
  status TEXT DEFAULT 'Open' CHECK(status IN ('Open','In Progress','Completed','Dismissed')),
  source_record_type TEXT,
  source_record_id INTEGER,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

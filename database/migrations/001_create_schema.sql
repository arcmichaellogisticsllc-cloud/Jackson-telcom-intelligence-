CREATE TABLE IF NOT EXISTS regions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  owner TEXT NOT NULL,
  states TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS capacity_targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER NOT NULL,
  service_type TEXT NOT NULL,
  target_crews INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  UNIQUE(region_id, service_type),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('Admin','Southeast Owner','Great Lakes Owner')),
  region_id INTEGER NULL,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS organizations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL,
  region_id INTEGER NOT NULL,
  state TEXT,
  city TEXT,
  website TEXT,
  phone TEXT,
  notes TEXT,
  status TEXT DEFAULT 'Active',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS contacts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  title TEXT,
  email TEXT,
  phone TEXT,
  organization_id INTEGER,
  region_id INTEGER NOT NULL,
  relationship_owner TEXT,
  influence_level TEXT,
  relationship_strength TEXT,
  last_contact_date TEXT,
  next_action TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS subcontractors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  organization_id INTEGER NOT NULL,
  region_id INTEGER NOT NULL,
  markets_served TEXT,
  services_offered TEXT,
  crew_count INTEGER DEFAULT 0,
  aerial_crew_count INTEGER DEFAULT 0,
  underground_crew_count INTEGER DEFAULT 0,
  fiber_splicing_crew_count INTEGER DEFAULT 0,
  emergency_restoration_crew_count INTEGER DEFAULT 0,
  traffic_control_crew_count INTEGER DEFAULT 0,
  bucket_trucks INTEGER DEFAULT 0,
  directional_drills INTEGER DEFAULT 0,
  splicing_trailers INTEGER DEFAULT 0,
  insurance_status TEXT,
  w9_status TEXT,
  approval_stage TEXT,
  availability TEXT,
  performance_score INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS opportunities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  organization_id INTEGER,
  region_id INTEGER NOT NULL,
  market TEXT,
  estimated_value REAL DEFAULT 0,
  estimated_margin REAL DEFAULT 0,
  probability INTEGER DEFAULT 0,
  stage TEXT,
  capacity_required INTEGER DEFAULT 0,
  decision_makers TEXT,
  next_action TEXT,
  owner TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(organization_id) REFERENCES organizations(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS signals (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  description TEXT,
  signal_type TEXT NOT NULL CHECK(signal_type IN ('Capacity','Opportunity','Relationship','Market')),
  source_type TEXT NOT NULL CHECK(source_type IN ('Facebook Marketplace','LinkedIn','Industry News','Referral','Conference','Website Form','Manual Entry','Government Data','Contractor Intelligence','Equipment Listing','Other')),
  source_url TEXT,
  region_id INTEGER NOT NULL,
  state TEXT,
  organization_name TEXT,
  contact_name TEXT,
  confidence_score INTEGER DEFAULT 0,
  impact_score INTEGER DEFAULT 0,
  priority TEXT NOT NULL DEFAULT 'Medium' CHECK(priority IN ('Low','Medium','High','Critical')),
  owner TEXT DEFAULT 'Unassigned' CHECK(owner IN ('Mike','Ron','Unassigned')),
  status TEXT NOT NULL DEFAULT 'New' CHECK(status IN ('New','Reviewed','Assigned','Converted','Ignored')),
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS intelligence_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  signal_id INTEGER,
  region_id INTEGER,
  title TEXT NOT NULL,
  summary TEXT,
  market TEXT,
  state TEXT,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(signal_id) REFERENCES signals(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS recommended_actions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  category TEXT NOT NULL,
  region_id INTEGER,
  priority TEXT NOT NULL,
  reason TEXT,
  recommended_next_action TEXT,
  assigned_owner TEXT,
  status TEXT DEFAULT 'Open',
  source_type TEXT,
  source_id INTEGER,
  recommendation_type TEXT,
  priority_score INTEGER DEFAULT 0,
  trigger_detail TEXT,
  why_it_matters TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS activities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entity_type TEXT NOT NULL,
  entity_id INTEGER NOT NULL,
  region_id INTEGER,
  activity_type TEXT NOT NULL,
  title TEXT NOT NULL,
  notes TEXT,
  activity_date TEXT DEFAULT CURRENT_TIMESTAMP,
  owner TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS executive_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  package_title TEXT NOT NULL,
  package_type TEXT NOT NULL CHECK(package_type IN ('Work','Capacity','Need','Relationship','Influence','Market','Pursuit','Strategic','Risk')),
  region_id INTEGER,
  market TEXT,
  confidence_score INTEGER DEFAULT 0,
  impact_score INTEGER DEFAULT 0,
  urgency_score INTEGER DEFAULT 0,
  decision_required TEXT,
  executive_summary TEXT,
  recommended_action TEXT,
  risk_of_inaction TEXT,
  owner TEXT,
  source_record_type TEXT,
  source_record_id INTEGER,
  package_status TEXT DEFAULT 'New' CHECK(package_status IN ('New','Active','Reviewed','Completed','Archived')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS work_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL UNIQUE,
  customer TEXT,
  opportunity TEXT,
  estimated_value REAL DEFAULT 0,
  strategic_alignment INTEGER DEFAULT 0,
  relationship_fit INTEGER DEFAULT 0,
  capacity_fit INTEGER DEFAULT 0,
  confidence INTEGER DEFAULT 0,
  recommendation TEXT,
  work_summary TEXT,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

CREATE TABLE IF NOT EXISTS capacity_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL UNIQUE,
  provider TEXT,
  available_crews INTEGER DEFAULT 0,
  mobilization TEXT,
  trust_score INTEGER DEFAULT 0,
  capacity_contribution INTEGER DEFAULT 0,
  region_id INTEGER,
  recommendation TEXT,
  capacity_summary TEXT,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS need_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL UNIQUE,
  contractor TEXT,
  workload_status TEXT,
  available_crews INTEGER DEFAULT 0,
  confidence INTEGER DEFAULT 0,
  urgency TEXT,
  recommendation TEXT,
  need_summary TEXT,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

CREATE TABLE IF NOT EXISTS influence_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL UNIQUE,
  contact TEXT,
  organization TEXT,
  influence_score INTEGER DEFAULT 0,
  authority_score INTEGER DEFAULT 0,
  trust_score INTEGER DEFAULT 0,
  objective TEXT,
  next_action TEXT,
  influence_summary TEXT,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

CREATE TABLE IF NOT EXISTS decision_packages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL UNIQUE,
  decision_type TEXT NOT NULL CHECK(decision_type IN ('Pursue Opportunity','Recruit Capacity','Promote Strategic Partner','Expand Market','Strengthen Relationship','Mitigate Risk','Review Package')),
  supporting_evidence TEXT,
  supporting_signals TEXT,
  supporting_relationships TEXT,
  supporting_capacity TEXT,
  risks TEXT,
  confidence INTEGER DEFAULT 0,
  recommendation TEXT,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

CREATE TABLE IF NOT EXISTS package_timeline_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL,
  event_type TEXT NOT NULL CHECK(event_type IN ('Created','Changed','Reviewed','Action Taken','Outcome','Archived')),
  event_title TEXT NOT NULL,
  event_summary TEXT,
  owner TEXT,
  event_date TEXT DEFAULT CURRENT_TIMESTAMP,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

CREATE TABLE IF NOT EXISTS executive_briefs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  brief_type TEXT NOT NULL CHECK(brief_type IN ('Daily','Weekly','Monthly','Quarterly Strategic')),
  region_id INTEGER,
  brief_title TEXT NOT NULL,
  brief_summary TEXT,
  top_actions TEXT,
  top_risks TEXT,
  top_opportunities TEXT,
  strategic_recommendations TEXT,
  generated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  status TEXT DEFAULT 'Draft' CHECK(status IN ('Draft','Reviewed','Archived')),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS package_actions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  executive_package_id INTEGER NOT NULL,
  action_type TEXT NOT NULL CHECK(action_type IN ('Call','Email','Add Note','Create Follow-Up','Assign Hunt','Assign Relationship Action','Promote Capacity Provider','Approve Pursuit','Create Preconstruction Profile','Mark Complete')),
  action_label TEXT NOT NULL,
  action_target TEXT,
  status TEXT DEFAULT 'Available' CHECK(status IN ('Available','Used','Completed','Skipped')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(executive_package_id) REFERENCES executive_packages(id)
);

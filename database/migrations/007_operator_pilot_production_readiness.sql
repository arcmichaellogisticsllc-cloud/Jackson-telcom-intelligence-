CREATE TABLE IF NOT EXISTS data_review_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  review_type TEXT NOT NULL CHECK(review_type IN ('Raw Signal','Source Item','Duplicate','Classification','Recommendation','Connector','Security','Data Quality','Other')),
  linked_record_type TEXT,
  linked_record_id INTEGER,
  region_id INTEGER,
  title TEXT NOT NULL,
  issue_summary TEXT,
  severity TEXT DEFAULT 'Medium' CHECK(severity IN ('Low','Medium','High','Critical')),
  status TEXT DEFAULT 'Open' CHECK(status IN ('Open','In Review','Resolved','Dismissed')),
  assigned_owner TEXT,
  recommended_resolution TEXT,
  resolution_notes TEXT,
  resolved_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS operator_pilot_feedback (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner TEXT NOT NULL,
  region_id INTEGER,
  feedback_area TEXT NOT NULL CHECK(feedback_area IN ('Command Center','Daily Actions','Recommendations','Visuals','Data Quality','Workflow','Security','Other')),
  feedback_summary TEXT NOT NULL,
  friction_score INTEGER DEFAULT 0,
  impact_score INTEGER DEFAULT 0,
  status TEXT DEFAULT 'New' CHECK(status IN ('New','Triaged','Planned','Resolved','Dismissed')),
  recommended_change TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS recommendation_tuning_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rule_name TEXT NOT NULL,
  source_module TEXT,
  category TEXT,
  owner_scope TEXT DEFAULT 'All',
  region_id INTEGER,
  min_priority_score INTEGER DEFAULT 0,
  max_daily_actions INTEGER DEFAULT 5,
  promote_to_daily_action INTEGER DEFAULT 1,
  active INTEGER DEFAULT 1,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS erp_contract_validation_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  contract_area TEXT NOT NULL CHECK(contract_area IN ('Customer','Project','Capacity','Subcontractors','Margin Forecast','Risk','Scenario','Relationships','Package Metadata')),
  field_name TEXT NOT NULL,
  required_for_handoff INTEGER DEFAULT 1,
  source_record_type TEXT,
  source_field TEXT,
  validation_status TEXT DEFAULT 'Pending' CHECK(validation_status IN ('Pending','Validated','Needs SyncERP Review','Not Required')),
  notes TEXT,
  reviewed_by TEXT,
  reviewed_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

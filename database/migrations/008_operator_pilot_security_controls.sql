CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  user_name TEXT,
  role TEXT,
  action TEXT NOT NULL,
  record_type TEXT,
  record_id INTEGER,
  ip_address TEXT,
  user_agent TEXT,
  outcome TEXT DEFAULT 'Success',
  details TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  used_at TEXT,
  requested_ip TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS data_quality_issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_type TEXT NOT NULL CHECK(issue_type IN ('Duplicate Entity','Missing Contact Info','Bad Import','Low Confidence Signal','Disputed Classification','Source Reliability Concern','Stale Contact','Conflicting Data','Missing Region','Missing Owner','Other')),
  linked_record_type TEXT,
  linked_record_id INTEGER,
  region_id INTEGER,
  title TEXT NOT NULL,
  description TEXT,
  severity TEXT DEFAULT 'Medium' CHECK(severity IN ('Low','Medium','High','Critical')),
  status TEXT DEFAULT 'Open' CHECK(status IN ('Open','In Review','Resolved','Dismissed')),
  assigned_owner TEXT,
  resolution_outcome TEXT,
  resolution_notes TEXT,
  resolved_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS connectors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  connector_name TEXT NOT NULL UNIQUE,
  source_type TEXT NOT NULL,
  run_mode TEXT DEFAULT 'Manual' CHECK(run_mode IN ('Manual','Scheduled','Fallback')),
  source_url TEXT,
  source_file_path TEXT,
  last_run_at TEXT,
  status TEXT DEFAULT 'Ready' CHECK(status IN ('Ready','Running','Paused','Failed','Needs Review')),
  records_found INTEGER DEFAULT 0,
  records_imported INTEGER DEFAULT 0,
  errors INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS connector_run_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  connector_id INTEGER,
  connector_name TEXT NOT NULL,
  started_at TEXT DEFAULT CURRENT_TIMESTAMP,
  finished_at TEXT,
  status TEXT DEFAULT 'Running' CHECK(status IN ('Running','Completed','Failed','Needs Review')),
  imported_count INTEGER DEFAULT 0,
  skipped_count INTEGER DEFAULT 0,
  error_count INTEGER DEFAULT 0,
  error_message TEXT,
  reviewed_by TEXT,
  review_status TEXT DEFAULT 'Pending' CHECK(review_status IN ('Pending','Reviewed','Needs Data Review')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(connector_id) REFERENCES connectors(id)
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_hash ON password_reset_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_data_quality_status ON data_quality_issues(status, severity);
CREATE INDEX IF NOT EXISTS idx_connector_run_logs_status ON connector_run_logs(status, review_status);

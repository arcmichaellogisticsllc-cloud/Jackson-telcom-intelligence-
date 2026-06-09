CREATE TABLE IF NOT EXISTS ownership_change_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  record_type TEXT NOT NULL,
  record_id INTEGER NOT NULL,
  region_id INTEGER,
  previous_primary_owner TEXT,
  new_primary_owner TEXT,
  previous_secondary_owner TEXT,
  new_secondary_owner TEXT,
  previous_shared_owner_flag INTEGER DEFAULT 0,
  new_shared_owner_flag INTEGER DEFAULT 0,
  changed_by TEXT,
  change_reason TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE INDEX IF NOT EXISTS idx_ownership_change_record ON ownership_change_log(record_type, record_id);
CREATE INDEX IF NOT EXISTS idx_ownership_change_created ON ownership_change_log(created_at);

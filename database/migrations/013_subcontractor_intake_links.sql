CREATE TABLE IF NOT EXISTS onboarding_intake_links (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  onboarding_type TEXT NOT NULL DEFAULT 'Subcontractor',
  onboarding_id INTEGER NOT NULL,
  token_hash TEXT NOT NULL UNIQUE,
  status TEXT DEFAULT 'Active' CHECK(status IN ('Active','Submitted','Expired','Revoked')),
  requested_by TEXT,
  expires_at TEXT NOT NULL,
  submitted_at TEXT,
  submission_payload TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_onboarding_intake_links_record ON onboarding_intake_links(onboarding_type, onboarding_id);
CREATE INDEX IF NOT EXISTS idx_onboarding_intake_links_status ON onboarding_intake_links(status, expires_at);

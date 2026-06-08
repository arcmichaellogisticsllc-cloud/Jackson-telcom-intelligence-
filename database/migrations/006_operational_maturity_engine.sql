CREATE TABLE IF NOT EXISTS operating_rhythms (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rhythm_name TEXT NOT NULL,
  cadence TEXT NOT NULL CHECK(cadence IN ('Daily','Weekly','Monthly','Quarterly')),
  review_type TEXT NOT NULL,
  owner TEXT NOT NULL,
  region_id INTEGER,
  required_sections TEXT,
  due_day TEXT,
  due_time TEXT,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS review_instances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  operating_rhythm_id INTEGER NOT NULL,
  review_period_start TEXT,
  review_period_end TEXT,
  owner TEXT NOT NULL,
  region_id INTEGER,
  status TEXT NOT NULL DEFAULT 'Pending' CHECK(status IN ('Pending','In Progress','Completed','Skipped','Overdue')),
  completed_at TEXT,
  summary TEXT,
  decisions_made TEXT,
  blockers_identified TEXT,
  follow_up_actions_created INTEGER DEFAULT 0,
  score INTEGER DEFAULT 0,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(operating_rhythm_id) REFERENCES operating_rhythms(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS rhythm_compliance_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner TEXT NOT NULL,
  region_id INTEGER,
  cadence TEXT NOT NULL CHECK(cadence IN ('Daily','Weekly','Monthly','Quarterly')),
  completion_rate INTEGER DEFAULT 0,
  overdue_count INTEGER DEFAULT 0,
  follow_up_completion_rate INTEGER DEFAULT 0,
  operating_rhythm_score INTEGER DEFAULT 0,
  category TEXT NOT NULL CHECK(category IN ('Critical','Weak','Stable','Strong','Dominant')),
  last_updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS workforce_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workforce_profile_id INTEGER NOT NULL,
  movement_type TEXT NOT NULL CHECK(movement_type IN ('Job Change','Promotion','New Market','New Company','New Role','Left Company','Returned to Market')),
  previous_company TEXT,
  new_company TEXT,
  previous_role TEXT,
  new_role TEXT,
  market TEXT,
  region_id INTEGER,
  confidence_score INTEGER DEFAULT 0,
  source_signal_id INTEGER,
  movement_date TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(workforce_profile_id) REFERENCES workforce_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id),
  FOREIGN KEY(source_signal_id) REFERENCES signals(id)
);

CREATE TABLE IF NOT EXISTS workforce_influence_relationships (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workforce_profile_id INTEGER NOT NULL,
  related_profile_id INTEGER,
  relationship_type TEXT NOT NULL CHECK(relationship_type IN ('worked_with','reports_to','influenced_by','influences','prior_company_relationship')),
  relationship_strength INTEGER DEFAULT 0,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(workforce_profile_id) REFERENCES workforce_profiles(id),
  FOREIGN KEY(related_profile_id) REFERENCES workforce_profiles(id)
);

CREATE TABLE IF NOT EXISTS workforce_forecasts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  workforce_profile_id INTEGER,
  region_id INTEGER,
  forecast_type TEXT NOT NULL CHECK(forecast_type IN ('Likely Mover','Emerging Leader','High-Value Recruit','Market Talent Gap')),
  forecast_score INTEGER DEFAULT 0,
  forecast_window TEXT,
  reason TEXT,
  recommended_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(workforce_profile_id) REFERENCES workforce_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS competitor_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  competitor_profile_id INTEGER NOT NULL,
  movement_type TEXT NOT NULL CHECK(movement_type IN ('Hiring Spike','Award Signal','Market Entry','Office Opening','Subcontractor Recruiting','Capacity Expansion','Layoff / Contraction','Equipment Acquisition')),
  region_id INTEGER,
  market TEXT,
  strategic_account TEXT,
  discipline TEXT,
  confidence_score INTEGER DEFAULT 0,
  source_signal_id INTEGER,
  movement_date TEXT,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(competitor_profile_id) REFERENCES competitor_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id),
  FOREIGN KEY(source_signal_id) REFERENCES signals(id)
);

CREATE TABLE IF NOT EXISTS competitive_pressure_indexes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  competitor_profile_id INTEGER,
  region_id INTEGER,
  market TEXT,
  strategic_account TEXT,
  discipline TEXT,
  hiring_pressure INTEGER DEFAULT 0,
  award_pressure INTEGER DEFAULT 0,
  relationship_pressure INTEGER DEFAULT 0,
  capacity_pressure INTEGER DEFAULT 0,
  market_entry_pressure INTEGER DEFAULT 0,
  competitive_pressure_score INTEGER DEFAULT 0,
  threat_level TEXT NOT NULL CHECK(threat_level IN ('Low','Medium','High','Critical')),
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(competitor_profile_id) REFERENCES competitor_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS competitor_forecasts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  competitor_profile_id INTEGER NOT NULL,
  region_id INTEGER,
  forecast_type TEXT NOT NULL CHECK(forecast_type IN ('Likely Expansion','Likely Pursuit Activity','Likely Capacity Growth','Likely Market Risk')),
  forecast_score INTEGER DEFAULT 0,
  forecast_window TEXT,
  reason TEXT,
  recommended_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(competitor_profile_id) REFERENCES competitor_profiles(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS win_loss_intelligence (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  competitor_profile_id INTEGER,
  opportunity_id INTEGER,
  outcome TEXT NOT NULL CHECK(outcome IN ('Won','Lost','Avoided')),
  reason TEXT,
  region_id INTEGER,
  account TEXT,
  lesson_learned TEXT,
  outcome_date TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(competitor_profile_id) REFERENCES competitor_profiles(id),
  FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS executive_doctrines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  doctrine_name TEXT NOT NULL UNIQUE,
  doctrine_order INTEGER NOT NULL,
  doctrine_description TEXT,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctrine_evaluations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  entity_type TEXT NOT NULL,
  entity_id INTEGER NOT NULL,
  region_id INTEGER,
  owner TEXT,
  work_alignment_score INTEGER DEFAULT 0,
  capacity_alignment_score INTEGER DEFAULT 0,
  relationship_alignment_score INTEGER DEFAULT 0,
  flow_alignment_score INTEGER DEFAULT 0,
  action_alignment_score INTEGER DEFAULT 0,
  overall_doctrine_alignment_score INTEGER DEFAULT 0,
  work_status TEXT DEFAULT 'Review',
  capacity_status TEXT DEFAULT 'Review',
  relationship_status TEXT DEFAULT 'Review',
  flow_status TEXT DEFAULT 'Review',
  action_status TEXT DEFAULT 'Review',
  reason_for_score TEXT,
  recommended_action TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(entity_type, entity_id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS quarterly_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  review_quarter TEXT NOT NULL,
  work_created INTEGER DEFAULT 0,
  capacity_added INTEGER DEFAULT 0,
  relationships_strengthened INTEGER DEFAULT 0,
  influence_growth INTEGER DEFAULT 0,
  opportunities_won INTEGER DEFAULT 0,
  pursuits_lost INTEGER DEFAULT 0,
  doctrine_alignment INTEGER DEFAULT 0,
  quarterly_summary TEXT,
  recommended_focus TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(region_id, review_quarter),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

CREATE TABLE IF NOT EXISTS executive_health_scores (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  work_alignment INTEGER DEFAULT 0,
  capacity_alignment INTEGER DEFAULT 0,
  relationship_alignment INTEGER DEFAULT 0,
  action_alignment INTEGER DEFAULT 0,
  signal_quality_alignment INTEGER DEFAULT 0,
  doctrine_compliance_score INTEGER DEFAULT 0,
  doctrine_category TEXT DEFAULT 'Stable' CHECK(doctrine_category IN ('Critical','Weak','Stable','Strong','Dominant')),
  strongest_alignment TEXT,
  weakest_alignment TEXT,
  top_improvement_area TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(region_id),
  FOREIGN KEY(region_id) REFERENCES regions(id)
);

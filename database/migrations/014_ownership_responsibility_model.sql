CREATE TABLE IF NOT EXISTS owner_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_key TEXT NOT NULL UNIQUE,
  display_name TEXT NOT NULL,
  legacy_owner_value TEXT NOT NULL UNIQUE,
  owner_type TEXT NOT NULL DEFAULT 'person',
  active INTEGER DEFAULT 1,
  sort_order INTEGER DEFAULT 100,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS responsibility_roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  role_key TEXT NOT NULL UNIQUE,
  role_name TEXT NOT NULL,
  role_description TEXT,
  active INTEGER DEFAULT 1,
  sort_order INTEGER DEFAULT 100,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS owner_responsibility_roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_profile_id INTEGER NOT NULL,
  responsibility_role_id INTEGER NOT NULL,
  is_primary INTEGER DEFAULT 0,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(owner_profile_id, responsibility_role_id),
  FOREIGN KEY(owner_profile_id) REFERENCES owner_profiles(id),
  FOREIGN KEY(responsibility_role_id) REFERENCES responsibility_roles(id)
);

CREATE TABLE IF NOT EXISTS region_ownership_defaults (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  region_id INTEGER,
  context_key TEXT NOT NULL DEFAULT 'general',
  primary_owner_profile_id INTEGER NOT NULL,
  secondary_owner_profile_id INTEGER,
  shared_owner_flag INTEGER DEFAULT 0,
  default_reason TEXT,
  active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(region_id, context_key),
  FOREIGN KEY(region_id) REFERENCES regions(id),
  FOREIGN KEY(primary_owner_profile_id) REFERENCES owner_profiles(id),
  FOREIGN KEY(secondary_owner_profile_id) REFERENCES owner_profiles(id)
);

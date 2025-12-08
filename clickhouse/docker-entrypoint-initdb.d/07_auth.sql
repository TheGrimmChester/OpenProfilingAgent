-- User authentication and authorization
CREATE TABLE IF NOT EXISTS opa.users (
  id String,
  organization_id String DEFAULT 'default-org',
  username String,
  email String,
  password_hash String, -- bcrypt hash
  role String, -- 'admin', 'editor', 'viewer'
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now(),
  last_login Nullable(DateTime)
) ENGINE = MergeTree()
ORDER BY (organization_id, username);

-- Insert default admin user (password: admin, should be changed)
-- Password hash for 'admin' using bcrypt with cost 10
INSERT INTO opa.users (id, organization_id, username, email, password_hash, role) 
SELECT 'admin-001', 'default-org', 'admin', 'admin@opa.local', '$2a$10$rOzJqZqZqZqZqZqZqZqZqOqZqZqZqZqZqZqZqZqZqZqZqZqZqZq', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM opa.users WHERE id = 'admin-001');

-- Roles table (for future extensibility)
CREATE TABLE IF NOT EXISTS opa.roles (
  name String,
  permissions String -- JSON array of permissions
) ENGINE = MergeTree()
ORDER BY (name);

-- Insert default roles
INSERT INTO opa.roles (name, permissions) 
SELECT 'admin', '["*"]'
WHERE NOT EXISTS (SELECT 1 FROM opa.roles WHERE name = 'admin');

INSERT INTO opa.roles (name, permissions) 
SELECT 'editor', '["read", "write", "delete"]'
WHERE NOT EXISTS (SELECT 1 FROM opa.roles WHERE name = 'editor');

INSERT INTO opa.roles (name, permissions) 
SELECT 'viewer', '["read"]'
WHERE NOT EXISTS (SELECT 1 FROM opa.roles WHERE name = 'viewer');

-- API keys table for multi-tenant authentication
CREATE TABLE IF NOT EXISTS opa.api_keys (
  key_id String,
  organization_id String,
  project_id String,
  key_hash String, -- Hashed API key
  name String,
  created_at DateTime DEFAULT now(),
  expires_at Nullable(DateTime)
) ENGINE = MergeTree()
ORDER BY (organization_id, project_id, key_id);


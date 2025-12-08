-- Multi-tenancy support: Organizations and Projects
-- This file must run first to set up the foundation

-- Create the opa database if it doesn't exist
CREATE DATABASE IF NOT EXISTS opa;

CREATE TABLE IF NOT EXISTS opa.organizations (
  org_id String,
  name String,
  settings String DEFAULT '{}', -- JSON settings
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (org_id);

-- Insert default organization (if not exists)
INSERT INTO opa.organizations (org_id, name, settings) 
SELECT 'default-org', 'Default Organization', '{}'
WHERE NOT EXISTS (SELECT 1 FROM opa.organizations WHERE org_id = 'default-org');

CREATE TABLE IF NOT EXISTS opa.projects (
  project_id String,
  org_id String,
  name String,
  dsn String, -- Data Source Name for authentication (like Sentry)
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (org_id, project_id);

-- Insert default project (if not exists)
INSERT INTO opa.projects (project_id, org_id, name, dsn) 
SELECT 'default-project', 'default-org', 'Default Project', ''
WHERE NOT EXISTS (SELECT 1 FROM opa.projects WHERE project_id = 'default-project' AND org_id = 'default-org');

-- Add organization_id and project_id to existing tables
-- Note: These ALTER statements will be run after tables are created

-- Add columns to spans_min (will be applied after table creation)
-- ALTER TABLE opa.spans_min ADD COLUMN IF NOT EXISTS organization_id String DEFAULT 'default-org';
-- ALTER TABLE opa.spans_min ADD COLUMN IF NOT EXISTS project_id String DEFAULT 'default-project';
-- ALTER TABLE opa.spans_min MODIFY ORDER BY (organization_id, project_id, service, start_ts);

-- Add columns to spans_full
-- ALTER TABLE opa.spans_full ADD COLUMN IF NOT EXISTS organization_id String DEFAULT 'default-org';
-- ALTER TABLE opa.spans_full ADD COLUMN IF NOT EXISTS project_id String DEFAULT 'default-project';
-- ALTER TABLE opa.spans_full MODIFY ORDER BY (organization_id, project_id, service, trace_id, start_ts);

-- Add columns to traces_full
-- ALTER TABLE opa.traces_full ADD COLUMN IF NOT EXISTS organization_id String DEFAULT 'default-org';
-- ALTER TABLE opa.traces_full ADD COLUMN IF NOT EXISTS project_id String DEFAULT 'default-project';
-- ALTER TABLE opa.traces_full MODIFY ORDER BY (organization_id, project_id, service, start_ts);

-- Add columns to network_metrics
-- ALTER TABLE opa.network_metrics ADD COLUMN IF NOT EXISTS organization_id String DEFAULT 'default-org';
-- ALTER TABLE opa.network_metrics ADD COLUMN IF NOT EXISTS project_id String DEFAULT 'default-project';
-- ALTER TABLE opa.network_metrics MODIFY ORDER BY (organization_id, project_id, service, endpoint, date, hour);

-- Note: users table organization_id column is added in 07_auth.sql

-- Create indexes for efficient filtering
-- Note: ALTER TABLE ADD INDEX must be run separately after table creation


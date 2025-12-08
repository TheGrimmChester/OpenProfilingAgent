-- Browser Monitoring (uses browser_metrics from user_experience)
-- Reuse user_sessions table for browser sessions
-- Additional browser-specific metrics
CREATE TABLE IF NOT EXISTS opa.browser_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  session_id String,
  page_url String,
  page_load_time_ms Float32,
  dom_ready_time_ms Float32,
  resource_load_time_ms Float32,
  first_paint_ms Float32,
  first_contentful_paint_ms Float32,
  user_agent String,
  browser String,
  browser_version String,
  os String,
  device_type String,
  timestamp DateTime
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (organization_id, project_id, session_id, timestamp)
TTL timestamp + INTERVAL 90 DAY;


-- RUM Events table for browser monitoring data
CREATE TABLE IF NOT EXISTS opa.rum_events (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  session_id String,
  page_view_id String,
  page_url String,
  user_agent String,
  navigation_timing String DEFAULT '', -- JSON
  resource_timing String DEFAULT '', -- JSON array
  ajax_requests String DEFAULT '', -- JSON array
  errors String DEFAULT '', -- JSON array
  viewport String DEFAULT '', -- JSON
  occurred_at DateTime
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (organization_id, project_id, session_id, occurred_at)
TTL occurred_at + INTERVAL 90 DAY;


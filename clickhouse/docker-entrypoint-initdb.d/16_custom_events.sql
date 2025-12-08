-- Custom Events and Metrics
CREATE TABLE IF NOT EXISTS opa.custom_events (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  event_id String,
  event_type String,
  event_name String,
  service String,
  timestamp DateTime,
  data String, -- JSON data
  tags String DEFAULT '' -- JSON tags
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (organization_id, project_id, event_type, timestamp)
TTL timestamp + INTERVAL 90 DAY;

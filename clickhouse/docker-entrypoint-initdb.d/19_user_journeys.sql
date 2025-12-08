-- User Journeys
CREATE TABLE IF NOT EXISTS opa.user_journeys (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  journey_id String,
  user_id String,
  session_id String,
  trace_ids String, -- JSON array
  started_at DateTime,
  completed_at Nullable(DateTime)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(started_at)
ORDER BY (organization_id, project_id, user_id, started_at)
TTL started_at + INTERVAL 90 DAY;


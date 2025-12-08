-- User Experience Monitoring
CREATE TABLE IF NOT EXISTS opa.user_sessions (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  session_id String,
  user_id String,
  service String,
  started_at DateTime,
  ended_at Nullable(DateTime),
  duration_ms Float32,
  request_count UInt32,
  error_count UInt32
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(started_at)
ORDER BY (organization_id, project_id, session_id, started_at)
TTL started_at + INTERVAL 90 DAY;

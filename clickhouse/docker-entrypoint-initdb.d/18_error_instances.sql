-- Error Instances
CREATE TABLE IF NOT EXISTS opa.error_instances (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  instance_id String,
  group_id String,
  trace_id String,
  span_id String,
  error_type String,
  error_message String,
  stack_trace String,
  occurred_at DateTime,
  user_context String DEFAULT '', -- JSON
  environment String,
  release String,
  tags String DEFAULT '' -- JSON
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(occurred_at)
ORDER BY (organization_id, project_id, group_id, occurred_at)
TTL occurred_at + INTERVAL 90 DAY;


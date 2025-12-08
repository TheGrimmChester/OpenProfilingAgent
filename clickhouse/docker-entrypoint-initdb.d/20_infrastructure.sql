-- Infrastructure Monitoring
CREATE TABLE IF NOT EXISTS opa.infrastructure_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  host_id String,
  hostname String,
  metric_type String, -- 'cpu', 'memory', 'disk', 'network'
  metric_name String,
  value Float64,
  unit String,
  timestamp DateTime
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (organization_id, project_id, host_id, metric_type, timestamp)
TTL timestamp + INTERVAL 90 DAY;


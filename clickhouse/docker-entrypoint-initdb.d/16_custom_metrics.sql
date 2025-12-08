-- Custom Metrics (time-series)
CREATE TABLE IF NOT EXISTS opa.custom_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  metric_name String,
  metric_type String, -- 'counter', 'gauge', 'histogram'
  service String,
  date Date,
  hour DateTime,
  value Float64,
  count UInt64
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, metric_name, date, hour)
TTL date + INTERVAL 1 YEAR;


-- Full spans table with all details
CREATE TABLE IF NOT EXISTS opa.spans_full (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  trace_id String,
  span_id String,
  parent_id Nullable(String),
  service String,
  name String,
  url_scheme Nullable(String),
  url_host Nullable(String),
  url_path Nullable(String),
  start_ts DateTime64(3),
  end_ts DateTime64(3),
  duration_ms Float32,
  cpu_ms Float32,
  status String,
  net String DEFAULT '',
  sql String DEFAULT '',
  http String DEFAULT '',
  cache String DEFAULT '',
  redis String DEFAULT '',
  stack String DEFAULT '',
  tags String DEFAULT '',
  dumps String DEFAULT ''
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(start_ts)
ORDER BY (organization_id, project_id, service, trace_id, start_ts);

-- Complete traces table (reconstructed)
CREATE TABLE IF NOT EXISTS opa.traces_full (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  trace_id String,
  service String,
  start_ts DateTime64(3),
  end_ts DateTime64(3),
  duration_ms Float32,
  span_count UInt32,
  trace_json String,
  created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(start_ts)
ORDER BY (organization_id, project_id, service, start_ts)
TTL created_at + INTERVAL 7 DAY;

-- Network metrics aggregation table
CREATE TABLE IF NOT EXISTS opa.network_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  service String,
  endpoint String,
  date Date,
  hour DateTime,
  bytes_sent UInt64,
  bytes_received UInt64,
  request_count UInt64,
  avg_latency_ms Float32,
  max_latency_ms Float32
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, service, endpoint, date, hour);


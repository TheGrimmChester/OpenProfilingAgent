-- Key Transactions: Monitor specific important endpoints/transactions
CREATE TABLE IF NOT EXISTS opa.key_transactions (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  transaction_id String,
  name String,
  service String,
  pattern String, -- URL pattern or service:name pattern to match
  description String DEFAULT '',
  enabled UInt8 DEFAULT 1,
  created_at DateTime DEFAULT now(),
  updated_at DateTime DEFAULT now()
) ENGINE = MergeTree()
ORDER BY (organization_id, project_id, transaction_id);

-- Key transaction metrics (aggregated)
CREATE TABLE IF NOT EXISTS opa.key_transaction_metrics (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  transaction_id String,
  date Date,
  hour DateTime,
  request_count UInt64,
  total_duration_ms Float64,
  avg_duration_ms Float32,
  p50_duration_ms Float32,
  p95_duration_ms Float32,
  p99_duration_ms Float32,
  max_duration_ms Float32,
  error_count UInt64,
  error_rate Float32,
  throughput_rpm Float32, -- Requests per minute
  apdex_score Float32
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (organization_id, project_id, transaction_id, date, hour);

-- Indexes
ALTER TABLE opa.key_transactions ADD INDEX IF NOT EXISTS idx_transaction_id transaction_id TYPE bloom_filter GRANULARITY 1;
ALTER TABLE opa.key_transaction_metrics ADD INDEX IF NOT EXISTS idx_transaction_metrics (transaction_id, date) TYPE bloom_filter GRANULARITY 1;


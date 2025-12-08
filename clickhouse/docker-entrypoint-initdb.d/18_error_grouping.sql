-- Error Grouping and Fingerprinting
CREATE TABLE IF NOT EXISTS opa.error_groups (
  organization_id String DEFAULT 'default-org',
  project_id String DEFAULT 'default-project',
  group_id String,
  fingerprint String,
  error_type String,
  error_message String,
  first_seen DateTime,
  last_seen DateTime,
  count UInt64,
  user_count UInt64,
  status String DEFAULT 'unresolved', -- 'unresolved', 'resolved', 'ignored'
  assigned_to Nullable(String)
) ENGINE = ReplacingMergeTree(last_seen)
ORDER BY (organization_id, project_id, group_id);

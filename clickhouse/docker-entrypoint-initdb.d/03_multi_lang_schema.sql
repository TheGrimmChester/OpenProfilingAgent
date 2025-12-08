-- Multi-language support schema extensions
-- This script adds language, framework, and version columns to existing tables

-- Add columns to spans_min table
ALTER TABLE opa.spans_min 
ADD COLUMN IF NOT EXISTS language String DEFAULT 'php',
ADD COLUMN IF NOT EXISTS language_version Nullable(String),
ADD COLUMN IF NOT EXISTS framework Nullable(String),
ADD COLUMN IF NOT EXISTS framework_version Nullable(String);

-- Add columns to spans_full table
ALTER TABLE opa.spans_full 
ADD COLUMN IF NOT EXISTS language String DEFAULT 'php',
ADD COLUMN IF NOT EXISTS language_version Nullable(String),
ADD COLUMN IF NOT EXISTS framework Nullable(String),
ADD COLUMN IF NOT EXISTS framework_version Nullable(String);

-- Add columns to traces_full table
ALTER TABLE opa.traces_full 
ADD COLUMN IF NOT EXISTS language String DEFAULT 'php',
ADD COLUMN IF NOT EXISTS language_version Nullable(String),
ADD COLUMN IF NOT EXISTS framework Nullable(String),
ADD COLUMN IF NOT EXISTS framework_version Nullable(String);

-- Create indexes for performance
ALTER TABLE opa.spans_min ADD INDEX IF NOT EXISTS idx_language language TYPE set(100) GRANULARITY 4;
ALTER TABLE opa.spans_min ADD INDEX IF NOT EXISTS idx_framework framework TYPE set(100) GRANULARITY 4;
ALTER TABLE opa.spans_full ADD INDEX IF NOT EXISTS idx_language language TYPE set(100) GRANULARITY 4;
ALTER TABLE opa.spans_full ADD INDEX IF NOT EXISTS idx_framework framework TYPE set(100) GRANULARITY 4;

-- Update ORDER BY clauses to include language for better query performance
-- Note: This requires recreating the table, so we'll keep the existing ORDER BY
-- but the indexes above will help with filtering


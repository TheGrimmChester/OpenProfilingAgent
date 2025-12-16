-- Add W3C Trace Context columns to spans_full table
-- These columns store the original traceparent and tracestate header values
-- for cross-service trace correlation

ALTER TABLE opa.spans_full
ADD COLUMN IF NOT EXISTS w3c_traceparent Nullable(String) DEFAULT NULL;

ALTER TABLE opa.spans_full
ADD COLUMN IF NOT EXISTS w3c_tracestate Nullable(String) DEFAULT NULL;


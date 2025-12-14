-- Add created_at column to spans_min and spans_full tables
-- This represents when the trace/span was received and stored by the agent

-- Add created_at to spans_min table
ALTER TABLE opa.spans_min 
ADD COLUMN IF NOT EXISTS created_at DateTime64(3) DEFAULT now();

-- Add created_at to spans_full table
ALTER TABLE opa.spans_full 
ADD COLUMN IF NOT EXISTS created_at DateTime64(3) DEFAULT now();

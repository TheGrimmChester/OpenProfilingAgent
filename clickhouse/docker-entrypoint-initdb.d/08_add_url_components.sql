-- Migration: Add URL component columns to existing tables
-- This migration adds url_scheme, url_host, url_path columns to spans_min and spans_full tables

-- Add columns to spans_min if they don't exist
ALTER TABLE opa.spans_min 
ADD COLUMN IF NOT EXISTS url_scheme Nullable(String) AFTER name,
ADD COLUMN IF NOT EXISTS url_host Nullable(String) AFTER url_scheme,
ADD COLUMN IF NOT EXISTS url_path Nullable(String) AFTER url_host;

-- Add columns to spans_full if they don't exist
ALTER TABLE opa.spans_full 
ADD COLUMN IF NOT EXISTS url_scheme Nullable(String) AFTER name,
ADD COLUMN IF NOT EXISTS url_host Nullable(String) AFTER url_scheme,
ADD COLUMN IF NOT EXISTS url_path Nullable(String) AFTER url_host;


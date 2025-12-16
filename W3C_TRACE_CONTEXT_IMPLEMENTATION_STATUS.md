# W3C Trace Context Implementation Status

## Overview
Implementation of W3C Trace Context support for distributed tracing, including parsing `traceparent` and `tracestate` headers in the PHP extension, propagating these headers, generating W3C-compliant headers, and supporting cross-service correlation in the Go agent.

## Implementation Completed

### 1. PHP Extension (`php-extension/src/opa.c`)
- **W3C Context Storage**: Added global variables for W3C trace context:
  - `w3c_trace_id` (32 hex chars from traceparent)
  - `w3c_parent_id` (16 hex chars from traceparent)
  - `w3c_tracestate` (propagated tracestate value)
  - `w3c_sampled` (sampling flag)
  - `w3c_context_mutex` (thread safety)

- **Helper Functions**: Added hex conversion utilities:
  - `is_hex_char()`, `is_valid_hex()`, `pad_hex_to_length()`
  - `hex_to_bytes()`, `bytes_to_hex()`

- **Parsing Functions**:
  - `parse_w3c_traceparent_header()` - Extracts trace_id, parent_id, flags from traceparent header
  - `parse_w3c_tracestate_header()` - Extracts and stores tracestate value

- **Generation Functions**:
  - `generate_w3c_traceparent_header()` - Creates W3C-compliant traceparent header
  - `generate_w3c_tracestate_header()` - Creates tracestate header

- **Lifecycle Integration**:
  - **RSHUTDOWN**: Moved W3C header parsing from RINIT to RSHUTDOWN (headers not available in RINIT for PHP-FPM)
  - Added `parse_w3c_headers_from_request()` function called at start of RSHUTDOWN
  - Parses headers from `PG(http_globals)[TRACK_VARS_SERVER]` and `$_SERVER` symbol table
  - Sets `root_span_trace_id` and `root_span_parent_id` from W3C headers

- **cURL Hook Integration**:
  - Modified `zif_opa_curl_exec` to inject `traceparent` and `tracestate` headers into outgoing cURL requests

- **Debug Logging**: Added extensive debug logging for troubleshooting:
  - SAPI detection logging
  - Server array key listing
  - HTTP-related key search
  - W3C header parsing success/failure
  - Root span ID setting

### 2. Span JSON Generation (`php-extension/src/span.c`)
- Modified `produce_span_json_from_values()` to include W3C headers in JSON payload:
  - `w3c_traceparent` - Generated from stored W3C trace_id and current span_id
  - `w3c_tracestate` - Propagated tracestate value
- Added extern declarations for W3C global variables and generation function

### 3. Go Agent (`agent/main.go` and `agent/types.go`)
- **Type Definitions**: Added W3C fields to structs:
  - `Incoming.W3CTraceParent` and `Incoming.W3CTraceState`
  - `Span.W3CTraceParent` and `Span.W3CTraceState`

- **Span Processing**: Updated to copy W3C headers from `Incoming` to `Span` and include in ClickHouse map

### 4. ClickHouse Schema (`clickhouse/docker-entrypoint-initdb.d/24_add_w3c_trace_context.sql`)
- Added migration to add columns:
  - `w3c_traceparent Nullable(String)`
  - `w3c_tracestate Nullable(String)`

### 5. Documentation (`docs/AGENT_API_CONTRACT.md`)
- Added W3C Trace Context section documenting:
  - Header format and parsing
  - Propagation behavior
  - Mapping to internal IDs

### 6. Symfony Test Controller (`symfony-app/src/Controller/W3CTraceContextTestController.php`)
- Created test endpoints:
  - `/api/test/w3c/validate` - Validates incoming headers and makes outgoing requests
  - `/api/test/w3c/outgoing` - Tests header propagation
  - `/api/test/w3c/clickhouse/{traceId}` - Verifies data in ClickHouse

## Current Issues

### Primary Issue: W3C Fields Not Appearing in ClickHouse
- **Status**: `w3c_traceparent` and `w3c_tracestate` are consistently `null` in ClickHouse
- **Root Cause**: Headers are being received correctly (verified via test endpoint), and W3C trace_id parsing works (trace_id matches first 16 chars of W3C trace_id), but W3C fields are not being included in span JSON
- **Evidence**: 
  - Test endpoint confirms headers are accessible: `traceparent` and `tracestate` are received correctly
  - ClickHouse shows trace_id `4bf92f3577b34da6` matches W3C trace_id `4bf92f3577b34da6a3ce929d0e0e4736` (first 16 chars)
  - All spans show `w3c_traceparent: null` and `w3c_tracestate: null`
- **Hypothesis**: W3C context variables (`w3c_trace_id`, `w3c_tracestate`) may be NULL when `produce_span_json_from_values()` is called, or there's a timing/threading issue

### Debug Logging Observations
- **CLI Requests**: Debug logs show CLI requests (`SAPI=cli`) with environment variables in `$_SERVER`, but no HTTP headers
- **HTTP Requests**: HTTP requests are not appearing in debug logs, suggesting:
  - Different PHP-FPM worker processes may not be writing to the same log file
  - HTTP requests may not be triggering the debug logging path
  - Headers may not be available in `$_SERVER` even during RSHUTDOWN

### Server Array Contents
- Debug logs show server array contains:
  - Environment variables (OPA_LANGUAGE_VERSION, DATABASE_USER, etc.)
  - No HTTP headers (HTTP_TRACEPARENT, HTTP_TRACESTATE, REQUEST_METHOD)
- This suggests the server array being examined is from CLI context, not HTTP context

## Verification Steps Taken

1. ✅ **Fixed parsing logic**: Changed from SAPI-based detection to server_count-based detection
2. ✅ **Added debug logging**: 
   - `$_SERVER` availability check
   - Server key listing (first 20 keys)
   - HTTP-related key search
   - W3C header parsing success/failure
3. ✅ **Enabled debug logging**: Set `opa.debug_log=1` in PHP configuration
4. ✅ **Rebuilt containers**: Multiple rebuilds with `--no-cache` flag
5. ✅ **Tested with curl**: Multiple test requests with traceparent/tracestate headers
6. ✅ **Checked ClickHouse**: Verified columns exist but data is null

## Code Changes Summary

### Key Files Modified
- `php-extension/src/opa.c` - W3C parsing, generation, lifecycle integration
- `php-extension/src/span.c` - W3C fields in JSON payload
- `agent/main.go` - W3C field processing
- `agent/types.go` - W3C field definitions
- `clickhouse/docker-entrypoint-initdb.d/24_add_w3c_trace_context.sql` - Schema migration
- `docs/AGENT_API_CONTRACT.md` - Documentation

### Critical Code Sections

#### W3C Header Parsing (RSHUTDOWN)
```c
static void parse_w3c_headers_from_request(void) {
    // Checks SAPI type (fpm-fcgi, cli, etc.)
    // Initializes $_SERVER via zend_is_auto_global()
    // Searches PG(http_globals)[TRACK_VARS_SERVER] and $_SERVER symbol table
    // Looks for HTTP_TRACEPARENT, HTTP_traceparent, HTTP_Traceparent
    // Looks for HTTP_TRACESTATE, HTTP_tracestate, HTTP_Tracestate
    // Parses traceparent and sets root_span_trace_id/parent_id
}
```

#### W3C Header Generation (span.c)
```c
// In produce_span_json_from_values()
if (w3c_trace_id) {
    char *traceparent = generate_w3c_traceparent_header(w3c_trace_id, span_id, w3c_sampled);
    // Adds to JSON as "w3c_traceparent"
}
if (w3c_tracestate && strlen(w3c_tracestate) > 0) {
    // Adds to JSON as "w3c_tracestate"
}
```

## Next Steps

### Immediate Actions
1. **Verify HTTP Request Processing**:
   - Check if HTTP requests are creating spans (verify spans exist in ClickHouse)
   - Verify PHP-FPM workers are processing requests correctly

2. **Alternative Header Access Methods**:
   - Consider using SAPI hooks (`sapi_getenv()`) instead of `$_SERVER`
   - Check if headers are available via `sapi_module.getenv()` or request headers structure

3. **Debug Logging Investigation**:
   - Check PHP-FPM error logs for segfaults or zval access errors
   - Verify debug log file permissions for PHP-FPM workers
   - Check if multiple log files exist (per-worker logs)

4. **Direct Testing**:
   - Create a simple PHP script that dumps `$_SERVER` during RSHUTDOWN
   - Verify headers are present in `$_SERVER` for HTTP requests
   - Test with different HTTP clients (curl, browser, etc.)

### Potential Solutions
1. **Use SAPI Request Headers**: Access headers directly from SAPI request structure instead of `$_SERVER`
2. **Userland Callback**: Move header parsing to a userland callback after request initialization
3. **Request Hook**: Use a request hook that fires after headers are fully populated
4. **Header Injection**: Test if headers are being stripped by nginx/proxy before reaching PHP-FPM

## Test Commands

### Test HTTP Request with W3C Headers
```bash
curl -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01" \
     -H "tracestate: test=value" \
     http://localhost:8080/api/test/pdo/simple
```

### Check Debug Logs
```bash
docker-compose exec -T symfony-php tail -1000 /tmp/opa_debug.log | grep -E "RSHUTDOWN.*Parsing W3C|RSHUTDOWN.*Found traceparent|RSHUTDOWN.*HTTP-related key"
```

### Check ClickHouse Data
```bash
docker-compose exec -T clickhouse clickhouse-client --query \
  "SELECT trace_id, span_id, name, w3c_traceparent, w3c_tracestate \
   FROM opa.spans_full \
   WHERE trace_id LIKE '4bf92f3577b34d%' \
   AND parent_id IS NULL \
   ORDER BY start_ts DESC LIMIT 1 FORMAT JSONEachRow" | jq .
```

### Enable Debug Logging
```bash
docker-compose exec -T symfony-php sh -c "echo 'opa.debug_log=1' >> /usr/local/etc/php/conf.d/opa.ini"
docker-compose restart symfony-php
```

## Technical Notes

### W3C Trace Context Format
- **traceparent**: `00-{trace-id}-{parent-id}-{flags}`
  - trace-id: 32 hex characters (16 bytes)
  - parent-id: 16 hex characters (8 bytes)
  - flags: 2 hex characters (1 byte, bit 0 = sampled)

- **tracestate**: Key-value pairs separated by commas
  - Format: `key1=value1,key2=value2`
  - Propagated as-is

### ID Conversion
- W3C trace-id (32 hex) → Internal trace-id (16 hex): Uses first 16 characters
- Internal span-id (16 hex) → W3C parent-id (16 hex): Used directly

### Thread Safety
- All W3C context access is protected by `w3c_context_mutex`
- Mutex locked before reading/writing W3C globals
- Mutex locked in `produce_span_json_from_values()` when generating headers

## Files Reference

- PHP Extension: `php-extension/src/opa.c` (line ~5534-5750 for W3C parsing)
- Span Generation: `php-extension/src/span.c` (line ~1010-1040 for W3C fields)
- Agent Types: `agent/types.go` (Incoming and Span structs)
- Agent Processing: `agent/main.go` (span processing logic)
- ClickHouse Schema: `clickhouse/docker-entrypoint-initdb.d/24_add_w3c_trace_context.sql`
- Documentation: `docs/AGENT_API_CONTRACT.md` (W3C Trace Context section)

## Status: IN PROGRESS
- ✅ Code implementation complete
- ✅ Schema migration complete
- ✅ Documentation updated
- ❌ Verification incomplete (HTTP request logs not appearing)
- ❌ W3C fields not appearing in ClickHouse


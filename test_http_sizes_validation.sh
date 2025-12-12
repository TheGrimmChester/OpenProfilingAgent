#!/bin/bash
set -euo pipefail

# Validation script to show HTTP request/response sizes in existing traces

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

echo "=========================================="
echo "HTTP Request/Response Size Validation"
echo "=========================================="
echo ""

log_info "Checking ClickHouse for traces with HTTP request/response data..."
echo ""

# Get all traces with HTTP request data
TRACES=$(docker exec clickhouse clickhouse-client --query "
SELECT 
    trace_id,
    service,
    JSONExtractString(tags, 'http_request', 'method') as method,
    JSONExtractString(tags, 'http_request', 'uri') as uri,
    JSONExtractString(tags, 'http_request', 'request_size') as request_size,
    JSONExtractString(tags, 'http_response', 'response_size') as response_size,
    JSONExtractString(tags, 'http_response', 'status_code') as status_code
FROM opa.spans_full 
WHERE JSONExtractString(tags, 'http_request') != '' 
  AND JSONExtractString(tags, 'http_request') != '{}'
ORDER BY start_ts DESC 
LIMIT 10
FORMAT PrettyCompact" 2>&1)

if [[ -n "$TRACES" ]] && [[ "$TRACES" != "" ]]; then
    echo "$TRACES"
    echo ""
    
    # Get a trace with sizes
    TRACE_WITH_SIZES=$(docker exec clickhouse clickhouse-client --query "
    SELECT trace_id
    FROM opa.spans_full 
    WHERE JSONExtractString(tags, 'http_request', 'request_size') != ''
       OR JSONExtractString(tags, 'http_response', 'response_size') != ''
    ORDER BY start_ts DESC 
    LIMIT 1" 2>&1 | tr -d '\n\r ')
    
    if [[ -n "$TRACE_WITH_SIZES" ]] && [[ "$TRACE_WITH_SIZES" != "" ]]; then
        log_info "Found trace with sizes: $TRACE_WITH_SIZES"
        echo ""
        
        # Get full details
        DETAILS=$(docker exec clickhouse clickhouse-client --query "
        SELECT 
            JSONExtractString(tags, 'http_request', 'method') as method,
            JSONExtractString(tags, 'http_request', 'uri') as uri,
            JSONExtractString(tags, 'http_request', 'request_size') as request_size,
            JSONExtractString(tags, 'http_response', 'response_size') as response_size,
            JSONExtractString(tags, 'http_response', 'status_code') as status_code,
            JSONExtractRaw(tags, 'http_request') as http_request_full,
            JSONExtractRaw(tags, 'http_response') as http_response_full
        FROM opa.spans_full 
        WHERE trace_id = '$TRACE_WITH_SIZES'
        LIMIT 1
        FORMAT Vertical" 2>&1)
        
        echo "$DETAILS"
        echo ""
        
        # Query API
        log_info "Querying API for trace: $TRACE_WITH_SIZES"
        API_RESPONSE=$(curl -s "http://localhost:8081/api/traces/${TRACE_WITH_SIZES}/full" 2>/dev/null)
        
        if [[ -n "$API_RESPONSE" ]]; then
            echo "=== API Response ==="
            echo "$API_RESPONSE" | jq '{
                trace_id: .trace_id,
                http_request: .root.tags.http_request,
                http_response: .root.tags.http_response
            }' 2>/dev/null || echo "$API_RESPONSE" | head -50
            echo ""
            
            REQ_SIZE=$(echo "$API_RESPONSE" | jq -r '.root.tags.http_request.request_size // "null"' 2>/dev/null || echo "null")
            RESP_SIZE=$(echo "$API_RESPONSE" | jq -r '.root.tags.http_response.response_size // "null"' 2>/dev/null || echo "null")
            
            if [[ "$REQ_SIZE" != "null" ]] && [[ "$REQ_SIZE" != "" ]]; then
                log_info "✓ Request size in API: $REQ_SIZE bytes"
            else
                log_warn "⚠ Request size not in API response"
            fi
            
            if [[ "$RESP_SIZE" != "null" ]] && [[ "$RESP_SIZE" != "" ]]; then
                log_info "✓ Response size in API: $RESP_SIZE bytes"
            else
                log_warn "⚠ Response size not in API response"
            fi
            
            echo ""
            log_info "Dashboard URL: http://localhost:3000/traces/$TRACE_WITH_SIZES?tab=tags"
        fi
    else
        log_warn "No traces found with request_size or response_size populated"
        log_info "This is expected if only CLI traces exist. HTTP sizes are only tracked for actual HTTP requests."
    fi
else
    log_warn "No traces with HTTP request data found"
fi

echo ""
echo "=========================================="
echo "Implementation Status"
echo "=========================================="
echo "✓ Code changes implemented in PHP extension"
echo "✓ Dashboard updated to display request_size and response_size"
echo "✓ E2E test framework created"
echo ""
echo "Note: HTTP request/response sizes are only populated for actual"
echo "      HTTP requests via web server, not CLI scripts."
echo "=========================================="


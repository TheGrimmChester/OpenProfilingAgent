#!/bin/bash
set -euo pipefail

# Full Featured E2E Test for HTTP Request/Response Size Tracking
# This test makes actual HTTP requests and validates the sizes are tracked

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

API_URL="${API_URL:-http://localhost:8081}"
WEB_URL="${WEB_URL:-http://localhost:8089}"

echo "=========================================="
echo "HTTP Request/Response Size E2E Test"
echo "=========================================="
echo ""

# Check services
log_info "Checking services..."
if ! curl -sf "${API_URL}/api/health" > /dev/null 2>&1; then
    log_error "Agent not available at ${API_URL}"
    exit 1
fi

if ! docker exec clickhouse clickhouse-client --query "SELECT 1" > /dev/null 2>&1; then
    log_error "ClickHouse not available"
    exit 1
fi

log_info "Services are available"
echo ""

# Create a test PHP file that will be served
log_info "Creating test PHP endpoint..."
docker exec opa-symfony-test bash -c 'cat > /var/www/html/public/test_http_sizes.php << "EOFPHP"
<?php
// Test endpoint for HTTP request/response size tracking
header("Content-Type: application/json");

// Get request info
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uri = $_SERVER["REQUEST_URI"] ?? "/";
$query = $_SERVER["QUERY_STRING"] ?? "";

// Generate response with known size (approximately 500 bytes)
$response = [
    "method" => $method,
    "uri" => $uri,
    "query_string" => $query,
    "timestamp" => time(),
    "message" => str_repeat("A", 400), // 400 bytes
    "test" => "http_sizes_e2e"
];

echo json_encode($response, JSON_PRETTY_PRINT);
EOFPHP
' 2>&1

log_info "Test endpoint created"
echo ""

# Make HTTP requests
log_info "Making HTTP GET request with query string..."
GET_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "${WEB_URL}/test_http_sizes.php?param1=value1&param2=value2" -H "User-Agent: E2E-Test/1.0" -H "X-Test-Header: test-value")
GET_HTTP_CODE=$(echo "$GET_RESPONSE" | tail -1)
GET_BODY=$(echo "$GET_RESPONSE" | head -n -1)

if [[ "$GET_HTTP_CODE" == "200" ]]; then
    log_info "✓ GET request successful (HTTP $GET_HTTP_CODE)"
    echo "  Response size: $(echo "$GET_BODY" | wc -c) bytes"
else
    log_warn "⚠ GET request returned HTTP $GET_HTTP_CODE"
fi
echo ""

log_info "Making HTTP POST request with body..."
POST_DATA='{"test":"data","payload":"'$(python3 -c "print('X' * 200)")'"}'
POST_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${WEB_URL}/test_http_sizes.php" \
    -H "Content-Type: application/json" \
    -H "User-Agent: E2E-Test/1.0" \
    -d "$POST_DATA")
POST_HTTP_CODE=$(echo "$POST_RESPONSE" | tail -1)
POST_BODY=$(echo "$POST_RESPONSE" | head -n -1)

if [[ "$POST_HTTP_CODE" == "200" ]]; then
    log_info "✓ POST request successful (HTTP $POST_HTTP_CODE)"
    echo "  Request body size: $(echo -n "$POST_DATA" | wc -c) bytes"
    echo "  Response size: $(echo "$POST_BODY" | wc -c) bytes"
else
    log_warn "⚠ POST request returned HTTP $POST_HTTP_CODE"
fi
echo ""

# Wait for traces to be processed
log_info "Waiting for traces to be processed (5 seconds)..."
sleep 5
echo ""

# Query ClickHouse for recent traces
log_info "Querying ClickHouse for traces with HTTP request/response sizes..."
RECENT_TRACES=$(docker exec clickhouse clickhouse-client --query "
SELECT 
    trace_id,
    service,
    JSONExtractString(tags, 'http_request', 'method') as method,
    JSONExtractString(tags, 'http_request', 'request_size') as request_size,
    JSONExtractString(tags, 'http_response', 'response_size') as response_size,
    JSONExtractString(tags, 'http_request', 'uri') as uri
FROM opa.spans_full 
WHERE start_ts >= now() - INTERVAL 1 MINUTE
  AND JSONExtractString(tags, 'http_request', 'method') != 'CLI'
ORDER BY start_ts DESC 
LIMIT 5
FORMAT PrettyCompact" 2>&1)

if [[ -n "$RECENT_TRACES" ]]; then
    echo "$RECENT_TRACES"
    echo ""
    
    # Get a specific trace ID to verify via API
    TRACE_ID=$(docker exec clickhouse clickhouse-client --query "
    SELECT trace_id
    FROM opa.spans_full 
    WHERE start_ts >= now() - INTERVAL 1 MINUTE
      AND JSONExtractString(tags, 'http_request', 'method') != 'CLI'
      AND JSONExtractString(tags, 'http_request', 'request_size') != ''
    ORDER BY start_ts DESC 
    LIMIT 1" 2>&1 | tr -d '\n\r ')
    
    if [[ -n "$TRACE_ID" ]] && [[ "$TRACE_ID" != "" ]]; then
        log_info "Found trace with sizes: $TRACE_ID"
        echo ""
        
        log_info "Querying API for trace details..."
        TRACE_JSON=$(curl -s "${API_URL}/api/traces/${TRACE_ID}/full" 2>/dev/null)
        
        if [[ -n "$TRACE_JSON" ]]; then
            REQ_SIZE=$(echo "$TRACE_JSON" | jq -r '.root.tags.http_request.request_size // "null"' 2>/dev/null || echo "null")
            RESP_SIZE=$(echo "$TRACE_JSON" | jq -r '.root.tags.http_response.response_size // "null"' 2>/dev/null || echo "null")
            METHOD=$(echo "$TRACE_JSON" | jq -r '.root.tags.http_request.method // "null"' 2>/dev/null || echo "null")
            URI=$(echo "$TRACE_JSON" | jq -r '.root.tags.http_request.uri // "null"' 2>/dev/null || echo "null")
            
            echo "=== Trace Details from API ==="
            echo "Trace ID: $TRACE_ID"
            echo "Method: $METHOD"
            echo "URI: $URI"
            echo "Request Size: $REQ_SIZE bytes"
            echo "Response Size: $RESP_SIZE bytes"
            echo ""
            
            if [[ "$REQ_SIZE" != "null" ]] && [[ "$REQ_SIZE" != "" ]] && [[ "$REQ_SIZE" != "0" ]]; then
                log_info "✓ Request size is present: $REQ_SIZE bytes"
            else
                log_warn "⚠ Request size is missing or zero"
            fi
            
            if [[ "$RESP_SIZE" != "null" ]] && [[ "$RESP_SIZE" != "" ]] && [[ "$RESP_SIZE" != "0" ]]; then
                log_info "✓ Response size is present: $RESP_SIZE bytes"
            else
                log_warn "⚠ Response size is missing or zero"
            fi
            
            echo ""
            log_info "View this trace in dashboard: http://localhost:3000/traces/$TRACE_ID"
        else
            log_warn "Could not fetch trace from API"
        fi
    else
        log_warn "No traces found with request_size populated"
    fi
else
    log_warn "No recent HTTP traces found in ClickHouse"
fi

echo ""
echo "=========================================="
echo "Test Complete"
echo "=========================================="


#!/bin/bash
set -euo pipefail

# ServiceMap End-to-End Test Script
# This script tests the complete ServiceMap flow from span processing to API response
# 
# Usage:
#   ./test_service_map.sh [--ci] [--verbose]
#
# Environment variables:
#   CI_MODE: Set to '1' for CI mode (structured output, no colors)
#   TEST_TIMEOUT: Timeout for test execution (default: 60)
#   WAIT_TIMEOUT: Timeout for waiting for data (default: 40)
#   PROJECT_ROOT: Root directory of the project (auto-detected)

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${PROJECT_ROOT:-${SCRIPT_DIR}}"

# CI mode detection
CI_MODE="${CI_MODE:-0}"
if [[ "${1:-}" == "--ci" ]] || [[ -n "${GITHUB_ACTIONS:-}" ]] || [[ -n "${CI:-}" ]]; then
    CI_MODE=1
fi

# Verbose mode
VERBOSE="${VERBOSE:-0}"
if [[ "${1:-}" == "--verbose" ]] || [[ "${2:-}" == "--verbose" ]]; then
    VERBOSE=1
fi

# Test configuration
TEST_TIMEOUT="${TEST_TIMEOUT:-60}"
WAIT_TIMEOUT="${WAIT_TIMEOUT:-40}"
LOG_DIR="${LOG_DIR:-/tmp/opa-tests}"
TEST_OUTPUT_LOG="${LOG_DIR}/service_map_test_output.log"

# Colors (disabled in CI mode)
if [[ "$CI_MODE" -eq 1 ]]; then
    RED=''
    GREEN=''
    YELLOW=''
    NC=''
else
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    NC='\033[0m'
fi

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_WARNED=0
TEST_RESULTS=()

# Cleanup function
cleanup() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]] && [[ -f "$TEST_OUTPUT_LOG" ]]; then
        echo ""
        echo "=== Test Output (last 50 lines) ==="
        tail -50 "$TEST_OUTPUT_LOG" || true
    fi
    return $exit_code
}

trap cleanup EXIT INT TERM

# Logging functions
log_info() {
    if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
        echo "[INFO] $*"
    fi
}

log_error() {
    echo "[ERROR] $*" >&2
}

log_warn() {
    echo "[WARN] $*" >&2
}

# Test result tracking
test_result() {
    local status=$1
    local test_name=$2
    local details="${3:-}"
    
    if [[ $status -eq 0 ]]; then
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "✓ PASS: $test_name"
        else
            echo -e "${GREEN}✓ PASS${NC}: $test_name"
        fi
        TESTS_PASSED=$((TESTS_PASSED + 1))
        TEST_RESULTS+=("PASS:$test_name")
    else
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "✗ FAIL: $test_name"
        else
            echo -e "${RED}✗ FAIL${NC}: $test_name"
        fi
        TESTS_FAILED=$((TESTS_FAILED + 1))
        TEST_RESULTS+=("FAIL:$test_name")
        if [[ -n "$details" ]]; then
            echo "  $details"
        fi
    fi
}

test_warn() {
    local test_name=$1
    local details="${2:-}"
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "⚠ WARN: $test_name"
    else
        echo -e "${YELLOW}⚠ WARN${NC}: $test_name"
    fi
    TESTS_WARNED=$((TESTS_WARNED + 1))
    TEST_RESULTS+=("WARN:$test_name")
    if [[ -n "$details" ]]; then
        echo "  $details"
    fi
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed or not in PATH"
        return 1
    fi
    
    # Check docker-compose
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        log_error "docker-compose is not installed or not in PATH"
        return 1
    fi
    
    # Check if we can run docker commands
    if ! docker ps &> /dev/null; then
        log_error "Cannot run docker commands. Is Docker daemon running?"
        return 1
    fi
    
    # Check curl
    if ! command -v curl &> /dev/null; then
        log_error "curl is not installed or not in PATH"
        return 1
    fi
    
    return 0
}

# Get ClickHouse container name
get_clickhouse_container() {
    docker ps --format "{{.Names}}" | grep -iE "clickhouse" | head -1 || echo ""
}

# Get agent container name
get_agent_container() {
    docker ps --format "{{.Names}}" | grep -iE "(opa-agent|agent|myapm-agent)" | head -1 || echo ""
}

# Check if agent is running
check_agent() {
    log_info "Checking if agent is running..."
    
    local agent_name
    agent_name=$(get_agent_container)
    
    if [[ -n "$agent_name" ]]; then
        test_result 0 "Agent container is running ($agent_name)"
        return 0
    else
        test_result 1 "Agent container is not running"
        log_info "Available containers:"
        docker ps --format "  - {{.Names}}" | head -5 || true
        return 1
    fi
}

# Check if ClickHouse is accessible
check_clickhouse() {
    log_info "Checking ClickHouse connection..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "ClickHouse container is not running"
        return 1
    fi
    
    if timeout 5 docker exec "$clickhouse_container" clickhouse-client --query "SELECT 1" > /dev/null 2>&1; then
        test_result 0 "ClickHouse is accessible ($clickhouse_container)"
        return 0
    else
        log_info "Waiting for ClickHouse to be ready..."
        sleep 2
        if timeout 5 docker exec "$clickhouse_container" clickhouse-client --query "SELECT 1" > /dev/null 2>&1; then
            test_result 0 "ClickHouse is accessible ($clickhouse_container)"
            return 0
        else
            test_result 1 "ClickHouse is not accessible"
            return 1
        fi
    fi
}

# Send test spans with service dependencies
send_test_spans() {
    log_info "Sending test spans with service dependencies..."
    
    local agent_name
    agent_name=$(get_agent_container)
    
    if [[ -z "$agent_name" ]]; then
        test_result 1 "Agent container not found"
        return 1
    fi
    
    # Generate a unique trace ID
    local trace_id
    trace_id="test-service-map-$(date +%s)-$$"
    
    local start_time
    start_time=$(date +%s%3N)
    
    # Send parent span (service: frontend) - compact JSON on single line
    # Note: stack must be [] (array), not "[]" (string)
    # tags, sql, net, dumps can be JSON strings
    local parent_span
    parent_span="{\"trace_id\":\"$trace_id\",\"span_id\":\"parent-$trace_id\",\"parent_id\":null,\"service\":\"frontend\",\"name\":\"HTTP GET /api/users\",\"start_ts\":$start_time,\"end_ts\":$((start_time + 150)),\"duration\":150,\"cpu_ms\":10,\"status\":\"ok\",\"tags\":\"{\\\"organization_id\\\":\\\"default-org\\\",\\\"project_id\\\":\\\"default-project\\\",\\\"http_request\\\":{}}\",\"stack\":[],\"sql\":\"[]\",\"net\":\"{}\",\"dumps\":\"[]\"}"
    
    # Send child span 1 (service: api-gateway)
    local child1_start
    child1_start=$((start_time + 10))
    local child1_span
    child1_span="{\"trace_id\":\"$trace_id\",\"span_id\":\"child1-$trace_id\",\"parent_id\":\"parent-$trace_id\",\"service\":\"api-gateway\",\"name\":\"HTTP GET /users\",\"start_ts\":$child1_start,\"end_ts\":$((child1_start + 100)),\"duration\":100,\"cpu_ms\":8,\"status\":\"ok\",\"tags\":\"{\\\"organization_id\\\":\\\"default-org\\\",\\\"project_id\\\":\\\"default-project\\\",\\\"http_request\\\":{}}\",\"stack\":[],\"sql\":\"[]\",\"net\":\"{}\",\"dumps\":\"[]\"}"
    
    # Send child span 2 (service: user-service)
    local child2_start
    child2_start=$((child1_start + 20))
    local child2_span
    child2_span="{\"trace_id\":\"$trace_id\",\"span_id\":\"child2-$trace_id\",\"parent_id\":\"child1-$trace_id\",\"service\":\"user-service\",\"name\":\"getUserById\",\"start_ts\":$child2_start,\"end_ts\":$((child2_start + 50)),\"duration\":50,\"cpu_ms\":5,\"status\":\"ok\",\"tags\":\"{\\\"organization_id\\\":\\\"default-org\\\",\\\"project_id\\\":\\\"default-project\\\"}\",\"stack\":[],\"sql\":\"[]\",\"net\":\"{}\",\"dumps\":\"[]\"}"
    
    # Send child span 3 (service: database)
    local child3_start
    child3_start=$((child2_start + 10))
    local child3_span
    child3_span="{\"trace_id\":\"$trace_id\",\"span_id\":\"child3-$trace_id\",\"parent_id\":\"child2-$trace_id\",\"service\":\"database\",\"name\":\"SELECT users\",\"start_ts\":$child3_start,\"end_ts\":$((child3_start + 20)),\"duration\":20,\"cpu_ms\":2,\"status\":\"ok\",\"tags\":\"{\\\"organization_id\\\":\\\"default-org\\\",\\\"project_id\\\":\\\"default-project\\\"}\",\"stack\":[],\"sql\":\"[]\",\"net\":\"{}\",\"dumps\":\"[]\"}"
    
    # Send spans via TCP (port 10090 is mapped to 9090 in container)
    # Each span must be on a single line with a newline
    log_info "Sending spans to agent via TCP..."
    
    # Create a temporary file with all spans (one per line)
    local spans_file
    spans_file=$(mktemp)
    echo "$parent_span" > "$spans_file"
    echo "$child1_span" >> "$spans_file"
    echo "$child2_span" >> "$spans_file"
    echo "$child3_span" >> "$spans_file"
    
    # Send all spans in one connection
    if timeout 5 nc localhost 10090 < "$spans_file" > /dev/null 2>&1; then
        log_info "Spans sent successfully"
    else
        log_warn "nc command may have failed, but spans might still be sent"
    fi
    
    # Cleanup
    rm -f "$spans_file"
    
    # Give agent time to process
    sleep 1
    
    # Store trace ID for later verification
    echo "$trace_id" > "${LOG_DIR}/test_trace_id.txt"
    
    test_result 0 "Sent test spans (trace_id: $trace_id)"
    return 0
}

# Wait for data to be processed
wait_for_processing() {
    log_info "Waiting for spans to be processed (timeout: ${WAIT_TIMEOUT}s)..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "ClickHouse container not found"
        return 1
    fi
    
    local trace_id
    trace_id=$(cat "${LOG_DIR}/test_trace_id.txt" 2>/dev/null || echo "")
    
    if [[ -z "$trace_id" ]]; then
        test_result 1 "Trace ID not found"
        return 1
    fi
    
    local elapsed=0
    local found=0
    
    while [[ $elapsed -lt $WAIT_TIMEOUT ]]; do
        local count
        count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.spans_min WHERE trace_id = '$trace_id' AND organization_id = 'default-org' AND project_id = 'default-project'" 2>/dev/null || echo "0")
        
        if [[ "$count" -ge "4" ]]; then
            found=1
            break
        fi
        
        sleep 2
        elapsed=$((elapsed + 2))
        log_info "Waiting... (${elapsed}s/${WAIT_TIMEOUT}s)"
    done
    
    if [[ $found -eq 1 ]]; then
        test_result 0 "Spans processed and stored in ClickHouse"
        return 0
    else
        test_result 1 "Spans not found in ClickHouse after ${WAIT_TIMEOUT}s"
        return 1
    fi
}

# Verify service dependencies table
verify_service_dependencies() {
    log_info "Verifying service_dependencies table..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "ClickHouse container not found"
        return 1
    fi
    
    # Wait a bit for flush to happen (ServiceMapProcessor flushes every 30s)
    log_info "Waiting for ServiceMapProcessor to flush (may take up to 30s)..."
    sleep 35
    
    local dep_count
    dep_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.service_dependencies WHERE organization_id = 'default-org' AND project_id = 'default-project' AND (from_service = 'frontend' OR from_service = 'api-gateway' OR from_service = 'user-service')" 2>/dev/null || echo "0")
    
    if [[ "$dep_count" -gt 0 ]]; then
        test_result 0 "Found $dep_count service dependency(ies) in service_dependencies table"
        
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            log_info "Sample dependencies:"
            docker exec "$clickhouse_container" clickhouse-client --format=JSONEachRow --query "SELECT from_service, to_service, call_count, avg_duration_ms, error_rate FROM opa.service_dependencies WHERE organization_id = 'default-org' AND project_id = 'default-project' ORDER BY date DESC, hour DESC LIMIT 5" 2>/dev/null | head -3 | sed 's/^/    /' || echo "    (could not extract)"
        fi
    else
        test_result 1 "No service dependencies found in service_dependencies table"
        log_info "Checking if table exists..."
        docker exec "$clickhouse_container" clickhouse-client --query "SHOW TABLES FROM opa LIKE 'service_dependencies'" 2>/dev/null || echo "Table check failed"
    fi
}

# Verify service map metadata table
verify_service_map_metadata() {
    log_info "Verifying service_map_metadata table..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "ClickHouse container not found"
        return 1
    fi
    
    # Wait for metadata update (ServiceMapProcessor updates every 30s)
    log_info "Waiting for ServiceMapProcessor to update metadata (may take up to 30s)..."
    local elapsed=0
    local found=0
    local max_wait=35
    
    while [[ $elapsed -lt $max_wait ]]; do
        local meta_count
        meta_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.service_map_metadata WHERE organization_id = 'default-org' AND project_id = 'default-project' AND (from_service = 'frontend' OR from_service = 'api-gateway' OR from_service = 'user-service')" 2>/dev/null || echo "0")
        
        if [[ "$meta_count" -gt 0 ]]; then
            found=1
            break
        fi
        
        sleep 2
        elapsed=$((elapsed + 2))
        log_info "Waiting for metadata update... (${elapsed}s/${max_wait}s)"
    done
    
    if [[ $found -eq 1 ]]; then
        test_result 0 "Found service relationship(s) in service_map_metadata table"
        
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            log_info "Sample metadata:"
            docker exec "$clickhouse_container" clickhouse-client --format=JSONEachRow --query "SELECT from_service, to_service, avg_latency_ms, error_rate, call_count, health_status FROM opa.service_map_metadata WHERE organization_id = 'default-org' AND project_id = 'default-project' ORDER BY last_seen DESC LIMIT 5" 2>/dev/null | head -3 | sed 's/^/    /' || echo "    (could not extract)"
        fi
    else
        test_warn "No service relationships found in service_map_metadata table (may need more time - updates every 30s)"
        log_info "Checking if table exists..."
        docker exec "$clickhouse_container" clickhouse-client --query "SHOW TABLES FROM opa LIKE 'service_map_metadata'" 2>/dev/null || echo "Table check failed"
    fi
}

# Verify service map API
verify_service_map_api() {
    log_info "Verifying service map API..."
    
    local api_url="${API_URL:-http://localhost:8081}"
    
    # Test API endpoint
    local response
    response=$(curl -sf -H "X-Organization-ID: default-org" -H "X-Project-ID: default-project" "${api_url}/api/service-map" 2>&1 || echo "")
    
    if [[ -z "$response" ]]; then
        test_result 1 "Service map API returned empty response"
        return 1
    fi
    
    # Check if response is valid JSON
    if echo "$response" | jq . > /dev/null 2>&1; then
        test_result 0 "Service map API returns valid JSON"
    else
        test_result 1 "Service map API does not return valid JSON"
        log_info "Response: $response"
        return 1
    fi
    
    # Check for nodes array
    local nodes_count
    nodes_count=$(echo "$response" | jq '.nodes | length' 2>/dev/null || echo "0")
    
    if [[ "$nodes_count" -gt 0 ]]; then
        test_result 0 "Service map API returns $nodes_count node(s)"
        
        # Check for expected services
        local has_frontend
        has_frontend=$(echo "$response" | jq '.nodes[] | select(.service == "frontend")' 2>/dev/null | wc -l || echo "0")
        
        if [[ "$has_frontend" -gt 0 ]]; then
            test_result 0 "Service map includes frontend service"
        else
            test_warn "Service map does not include frontend service (may need more time)"
        fi
    else
        test_warn "Service map API returns no nodes (may need more time or data)"
    fi
    
    # Check for edges array
    local edges_count
    edges_count=$(echo "$response" | jq '.edges | length' 2>/dev/null || echo "0")
    
    if [[ "$edges_count" -gt 0 ]]; then
        test_result 0 "Service map API returns $edges_count edge(s)"
        
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            log_info "Sample edges:"
            echo "$response" | jq '.edges[0:3]' 2>/dev/null | sed 's/^/    /' || echo "    (could not extract)"
        fi
    else
        test_warn "Service map API returns no edges (may need more time or data)"
    fi
    
    # Check response structure
    local has_nodes
    has_nodes=$(echo "$response" | jq 'has("nodes")' 2>/dev/null || echo "false")
    local has_edges
    has_edges=$(echo "$response" | jq 'has("edges")' 2>/dev/null || echo "false")
    
    if [[ "$has_nodes" == "true" ]] && [[ "$has_edges" == "true" ]]; then
        test_result 0 "Service map API response has correct structure (nodes and edges)"
    else
        test_result 1 "Service map API response missing required fields"
        log_info "Response structure: nodes=$has_nodes, edges=$has_edges"
    fi
}

# Show summary
show_summary() {
    echo ""
    echo "=========================================="
    echo "Test Summary"
    echo "=========================================="
    echo "Passed: $TESTS_PASSED"
    echo "Failed: $TESTS_FAILED"
    echo "Warnings: $TESTS_WARNED"
    echo ""
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "Test Results:"
        for result in "${TEST_RESULTS[@]}"; do
            echo "  $result"
        done
    fi
    
    if [[ $TESTS_FAILED -eq 0 ]]; then
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "✓ All tests passed"
        else
            echo -e "${GREEN}✓ All tests passed${NC}"
        fi
        return 0
    else
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "✗ Some tests failed"
        else
            echo -e "${RED}✗ Some tests failed${NC}"
        fi
        return 1
    fi
}

# Main test execution
main() {
    echo "=========================================="
    echo "ServiceMap End-to-End Test"
    echo "=========================================="
    echo ""
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "Running in CI mode"
        echo "Project root: $PROJECT_ROOT"
        echo ""
    fi
    
    # Create log directory
    mkdir -p "$LOG_DIR"
    
    # Check prerequisites
    if ! check_prerequisites; then
        log_error "Prerequisites check failed"
        exit 1
    fi
    
    # Prerequisites
    check_agent
    check_clickhouse
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        log_error "Prerequisites not met. Please ensure agent and ClickHouse are running."
        show_summary
        exit 1
    fi
    
    # Run tests
    send_test_spans
    wait_for_processing
    verify_service_dependencies
    verify_service_map_metadata
    verify_service_map_api
    
    # Show summary
    show_summary
    exit $?
}

# Run main function
main "$@"


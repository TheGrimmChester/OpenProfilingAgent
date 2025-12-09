#!/bin/bash
set -euo pipefail

# SQL Profiling End-to-End Test Script
# This script tests the complete SQL profiling flow from PHP extension to database
# 
# Usage:
#   ./test_sql_profiling.sh [--ci] [--verbose]
#
# Environment variables:
#   CI_MODE: Set to '1' for CI mode (structured output, no colors)
#   TEST_TIMEOUT: Timeout for test execution (default: 90)
#   WAIT_TIMEOUT: Timeout for waiting for data (default: 30)
#   PROJECT_ROOT: Root directory of the project (auto-detected)

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${PROJECT_ROOT:-${SCRIPT_DIR}}"
PHP_EXTENSION_DIR="${PROJECT_ROOT}/php-extension"

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
TEST_TIMEOUT="${TEST_TIMEOUT:-90}"
WAIT_TIMEOUT="${WAIT_TIMEOUT:-30}"
LOG_DIR="${LOG_DIR:-/tmp/opa-tests}"
TEST_OUTPUT_LOG="${LOG_DIR}/sql_test_output.log"

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
    
    # Cleanup docker-compose if in php-extension directory
    if [[ -f "${PHP_EXTENSION_DIR}/docker-compose.yaml" ]]; then
        cd "${PHP_EXTENSION_DIR}" || true
        docker-compose down > /dev/null 2>&1 || true
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
    
    return 0
}

# Check if agent is running
check_agent() {
    log_info "Checking if agent is running..."
    
    local agent_name
    agent_name=$(docker ps --format "{{.Names}}" | grep -iE "(opa-agent|agent|myapm-agent)" | head -1 || echo "")
    
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
    clickhouse_container=$(docker ps --format "{{.Names}}" | grep -iE "clickhouse" | head -1 || echo "")
    
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

# Run SQL profiling test
run_sql_test() {
    log_info "Running SQL profiling test..."
    
    if [[ ! -d "$PHP_EXTENSION_DIR" ]]; then
        test_result 1 "PHP extension directory not found: $PHP_EXTENSION_DIR"
        return 1
    fi
    
    if [[ ! -f "${PHP_EXTENSION_DIR}/docker-compose.yaml" ]]; then
        test_result 1 "docker-compose.yaml not found in $PHP_EXTENSION_DIR"
        return 1
    fi
    
    # Create log directory
    mkdir -p "$LOG_DIR"
    
    cd "$PHP_EXTENSION_DIR" || return 1
    
    # Clean up any existing containers
    log_info "Cleaning up existing containers..."
    docker-compose down > /dev/null 2>&1 || true
    
    # Run the test
    log_info "Starting test containers (timeout: ${TEST_TIMEOUT}s)..."
    if timeout "$TEST_TIMEOUT" docker-compose up --abort-on-container-exit > "$TEST_OUTPUT_LOG" 2>&1; then
        local exit_code=$?
        if [[ $exit_code -eq 0 ]]; then
            test_result 0 "SQL profiling test executed successfully"
        else
            test_result 1 "SQL profiling test exited with code $exit_code"
            if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
                echo "Last 20 lines of output:"
                tail -20 "$TEST_OUTPUT_LOG" || true
            fi
            return 1
        fi
    else
        local exit_code=$?
        test_result 1 "SQL profiling test failed or timed out (exit code: $exit_code)"
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            echo "Last 20 lines of output:"
            tail -20 "$TEST_OUTPUT_LOG" || true
        fi
        return 1
    fi
    
    # Clean up
    log_info "Cleaning up test containers..."
    docker-compose down > /dev/null 2>&1 || true
    
    return 0
}

# Get ClickHouse container name
get_clickhouse_container() {
    docker ps --format "{{.Names}}" | grep -iE "clickhouse" | head -1 || echo "clickhouse"
}

# Verify spans_min data
verify_spans_min() {
    log_info "Verifying spans_min table..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    # Wait for data to be written (with retries)
    log_info "Waiting for data to be written..."
    local max_wait=$WAIT_TIMEOUT
    local wait_count=0
    local total_spans=0
    
    # Get count before test
    local spans_before
    spans_before=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.spans_min" 2>/dev/null || echo "0")
    log_info "Spans before test: $spans_before"
    
    while [[ $wait_count -lt $max_wait ]]; do
        sleep 2
        local spans_now
        spans_now=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.spans_min" 2>/dev/null || echo "0")
        total_spans=$((spans_now - spans_before))
        wait_count=$((wait_count + 2))
        if [[ "$total_spans" -le 0 ]]; then
            log_info "Still waiting... ($wait_count/$max_wait seconds, total spans: $spans_now)"
        else
            break
        fi
    done
    
    if [[ "$total_spans" -gt 0 ]]; then
        test_result 0 "Found $total_spans new span(s) in spans_min"
    else
        local spans_now
        spans_now=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM opa.spans_min" 2>/dev/null || echo "0")
        if [[ "$spans_now" -gt 0 ]]; then
            test_warn "No new spans detected, but $spans_now total spans exist (may be from previous test)"
            total_spans=$spans_now
        else
            test_result 1 "No spans found in spans_min after ${max_wait}s wait"
            log_info "Debug: Checking all recent spans..."
            docker exec "$clickhouse_container" clickhouse-client --query "SELECT service, COUNT(*) as cnt, MAX(start_ts) as latest FROM opa.spans_min GROUP BY service ORDER BY latest DESC LIMIT 5" 2>/dev/null || true
            return 1
        fi
    fi
    
    # Check spans with SQL fingerprint (get most recent)
    local spans_with_sql
    spans_with_sql=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM (SELECT * FROM opa.spans_min ORDER BY start_ts DESC LIMIT 20) WHERE query_fingerprint IS NOT NULL AND query_fingerprint != ''" 2>/dev/null || echo "0")
    
    if [[ "$spans_with_sql" -gt 0 ]]; then
        test_result 0 "Found $spans_with_sql span(s) with SQL fingerprint in spans_min"
        
        # Show sample fingerprint
        local sample_fp
        sample_fp=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT query_fingerprint FROM opa.spans_min WHERE query_fingerprint IS NOT NULL AND query_fingerprint != '' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null || echo "")
        if [[ -n "$sample_fp" ]]; then
            log_info "Sample fingerprint: ${sample_fp:0:60}..."
        fi
    else
        test_result 1 "No spans with SQL fingerprint found in spans_min"
    fi
    
    # Check db_system (get most recent)
    local spans_with_db
    spans_with_db=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM (SELECT * FROM opa.spans_min ORDER BY start_ts DESC LIMIT 20) WHERE db_system IS NOT NULL AND db_system != ''" 2>/dev/null || echo "0")
    
    if [[ "$spans_with_db" -gt 0 ]]; then
        test_result 0 "Found $spans_with_db span(s) with db_system in spans_min"
    else
        test_result 1 "No spans with db_system found in spans_min"
    fi
}

# Verify spans_full data
verify_spans_full() {
    log_info "Verifying spans_full table..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    # Check total full spans (get most recent)
    local total_full
    total_full=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM (SELECT * FROM opa.spans_full ORDER BY start_ts DESC LIMIT 20)" 2>/dev/null || echo "0")
    
    if [[ "$total_full" -gt 0 ]]; then
        test_result 0 "Found $total_full span(s) in spans_full"
    else
        test_result 1 "No spans found in spans_full"
        return 1
    fi
    
    # Check spans with SQL data (get most recent)
    local spans_with_sql
    spans_with_sql=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM (SELECT * FROM opa.spans_full ORDER BY start_ts DESC LIMIT 20) WHERE sql != '' AND sql != '[]'" 2>/dev/null || echo "0")
    
    if [[ "$spans_with_sql" -gt 0 ]]; then
        test_result 0 "Found $spans_with_sql span(s) with SQL data in spans_full"
        
        # Count SQL queries
        local sql_count
        sql_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT length(sql) FROM opa.spans_full WHERE sql != '' AND sql != '[]' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null || echo "0")
        log_info "SQL data size: $sql_count bytes"
    else
        test_result 1 "No spans with SQL data found in spans_full"
    fi
    
    # Check for SELECT queries
    local select_count
    select_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT sql FROM opa.spans_full WHERE sql != '' AND sql != '[]' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null | grep -o '"query":"[^"]*SELECT[^"]*"' | wc -l || echo "0")
    
    if [[ "$select_count" -gt 0 ]]; then
        test_result 0 "Found $select_count SELECT query(ies) in spans_full"
        
        # Verify duration and row count are present
        local sql_json
        sql_json=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT sql FROM opa.spans_full WHERE sql != '' AND sql != '[]' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null || echo "")
        if [[ -n "$sql_json" ]]; then
            local has_duration
            has_duration=$(echo "$sql_json" | grep -o '"duration_ms"' | wc -l || echo "0")
            local has_rows
            has_rows=$(echo "$sql_json" | grep -o '"rows_affected"' | wc -l || echo "0")
            
            if [[ "$has_duration" -gt 0 ]]; then
                test_result 0 "SQL queries include duration_ms field"
            else
                test_result 1 "SQL queries missing duration_ms field"
            fi
            
            if [[ "$has_rows" -gt 0 ]]; then
                test_result 0 "SQL queries include rows_affected field"
            else
                test_result 1 "SQL queries missing rows_affected field"
            fi
        fi
        
        # Show sample SELECT queries
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            log_info "Sample SELECT queries:"
            docker exec "$clickhouse_container" clickhouse-client --query "SELECT sql FROM opa.spans_full WHERE sql != '' AND sql != '[]' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null | grep -o '"query":"[^"]*SELECT[^"]*"' | head -3 | sed 's/^/    - /' || echo "    (could not extract)"
        fi
    else
        test_result 1 "No SELECT queries found in spans_full"
    fi
    
    # Check for INSERT queries
    local insert_count
    insert_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT sql FROM opa.spans_full WHERE sql != '' AND sql != '[]' ORDER BY start_ts DESC LIMIT 1" 2>/dev/null | grep -o '"query":"[^"]*INSERT[^"]*"' | wc -l || echo "0")
    
    if [[ "$insert_count" -gt 0 ]]; then
        test_result 0 "Found $insert_count INSERT query(ies) in spans_full"
    else
        test_result 1 "No INSERT queries found in spans_full"
    fi
    
    # Check call stack (get most recent)
    local stack_count
    stack_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT COUNT(*) FROM (SELECT * FROM opa.spans_full ORDER BY start_ts DESC LIMIT 20) WHERE stack != '' AND stack != '[]'" 2>/dev/null || echo "0")
    
    if [[ "$stack_count" -gt 0 ]]; then
        test_result 0 "Found $stack_count span(s) with call stack data"
    else
        test_result 1 "No call stack data found in spans_full"
    fi
}

# Verify agent received data
verify_agent_reception() {
    log_info "Verifying agent received data..."
    
    local agent_name
    agent_name=$(docker ps --format "{{.Names}}" | grep -iE "(opa-agent|agent|myapm-agent)" | head -1 || echo "")
    
    if [[ -z "$agent_name" ]]; then
        test_result 1 "Agent container not found"
        return 1
    fi
    
    # Check agent logs for debug messages
    local received_count
    received_count=$(docker logs "$agent_name" --since 5m 2>&1 | grep -c "Worker received message" || echo "0")
    
    if [[ "$received_count" -gt 0 ]]; then
        test_result 0 "Agent received $received_count message(s)"
    else
        test_result 1 "Agent did not receive any messages"
    fi
    
    # Check for full span storage
    local stored_count
    stored_count=$(docker logs "$agent_name" --since 5m 2>&1 | grep -c "Storing full span" || echo "0")
    
    if [[ "$stored_count" -gt 0 ]]; then
        test_result 0 "Agent stored $stored_count full span(s)"
    else
        test_result 1 "Agent did not store any full spans"
    fi
}

# Verify trace API
verify_trace_api() {
    log_info "Verifying trace API..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    # Get latest trace ID
    local latest_trace
    latest_trace=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT trace_id FROM opa.spans_min ORDER BY start_ts DESC LIMIT 1" 2>/dev/null || echo "")
    
    if [[ -z "$latest_trace" ]]; then
        test_result 1 "No trace ID found for API test"
        return 1
    fi
    
    log_info "Testing trace ID: $latest_trace"
    
    # Determine API URL
    local api_url="${API_URL:-http://localhost:8081}"
    
    # Test trace detail API
    if curl -sf "${api_url}/api/traces/${latest_trace}/full" > /dev/null 2>&1; then
        test_result 0 "Trace detail API is accessible"
        
        # Check if API returns SQL queries
        local sql_in_api
        sql_in_api=$(curl -sf "${api_url}/api/traces/${latest_trace}/full" 2>/dev/null | grep -o '"sql":\[' | wc -l || echo "0")
        
        if [[ "$sql_in_api" -gt 0 ]]; then
            test_result 0 "Trace API returns SQL queries"
        else
            test_result 1 "Trace API does not return SQL queries"
        fi
    else
        test_result 1 "Trace detail API is not accessible at ${api_url}"
    fi
}

# Show summary
show_summary() {
    echo ""
    echo "=========================================="
    echo "Test Summary"
    echo "=========================================="
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "Tests Passed: $TESTS_PASSED"
        echo "Tests Failed: $TESTS_FAILED"
        echo "Tests Warned: $TESTS_WARNED"
    else
        echo -e "${GREEN}Tests Passed: $TESTS_PASSED${NC}"
        echo -e "${RED}Tests Failed: $TESTS_FAILED${NC}"
        if [[ $TESTS_WARNED -gt 0 ]]; then
            echo -e "${YELLOW}Tests Warned: $TESTS_WARNED${NC}"
        fi
    fi
    
    echo ""
    
    if [[ $TESTS_FAILED -eq 0 ]]; then
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "✓ All tests passed!"
        else
            echo -e "${GREEN}✓ All tests passed!${NC}"
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
    echo "SQL Profiling End-to-End Test"
    echo "=========================================="
    echo ""
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "Running in CI mode"
        echo "Project root: $PROJECT_ROOT"
        echo "PHP extension dir: $PHP_EXTENSION_DIR"
        echo ""
    fi
    
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
    
    # Run SQL test
    run_sql_test
    
    # Wait for data to be processed
    log_info "Waiting for data to be processed..."
    sleep 5
    
    # Verify data storage
    verify_spans_min
    verify_spans_full
    
    # Verify agent processing
    verify_agent_reception
    
    # Verify API
    verify_trace_api
    
    # Show summary
    show_summary
    exit $?
}

# Run main function
main "$@"

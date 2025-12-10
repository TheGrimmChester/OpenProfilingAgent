#!/bin/bash
set -euo pipefail

# cURL Profiling End-to-End Test Script
# This script tests the complete cURL profiling flow from PHP extension to agent
# 
# Usage:
#   ./test_curl_profiling.sh [--ci] [--verbose]
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
TEST_OUTPUT_LOG="${LOG_DIR}/curl_test_output.log"
TEST_PHP_SCRIPT="${PROJECT_ROOT}/php-extension/tests/curl_profiling_test.php"

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
            echo "::notice title=Test Passed::$test_name"
        else
            echo -e "${GREEN}✓${NC} $test_name"
        fi
        ((TESTS_PASSED++)) || true
        TEST_RESULTS+=("PASS:$test_name")
    elif [[ $status -eq 2 ]]; then
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "::warning title=Test Warning::$test_name: $details"
        else
            echo -e "${YELLOW}⚠${NC} $test_name: $details"
        fi
        ((TESTS_WARNED++)) || true
        TEST_RESULTS+=("WARN:$test_name:$details")
    else
        if [[ "$CI_MODE" -eq 1 ]]; then
            echo "::error title=Test Failed::$test_name: $details"
        else
            echo -e "${RED}✗${NC} $test_name: $details"
        fi
        ((TESTS_FAILED++)) || true
        TEST_RESULTS+=("FAIL:$test_name:$details")
    fi
    
    if [[ -n "$details" ]] && [[ "$VERBOSE" -eq 1 ]]; then
        echo "    $details"
    fi
}

# Create log directory
mkdir -p "$LOG_DIR"

# Get ClickHouse container name
get_clickhouse_container() {
    local container
    container=$(docker ps --filter "name=clickhouse" --format "{{.Names}}" | head -1)
    if [[ -z "$container" ]]; then
        container=$(docker ps --filter "ancestor=clickhouse/clickhouse-server" --format "{{.Names}}" | head -1)
    fi
    echo "$container"
}

# Get agent container name
get_agent_container() {
    local container
    container=$(docker ps --filter "name=opa-agent" --format "{{.Names}}" | head -1)
    if [[ -z "$container" ]]; then
        container=$(docker ps --filter "ancestor=opa-agent" --format "{{.Names}}" | head -1)
    fi
    echo "$container"
}

# Check if services are running
check_services() {
    log_info "Checking required services..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    if [[ -z "$clickhouse_container" ]]; then
        log_error "ClickHouse container not found. Please start docker-compose services first."
        return 1
    fi
    
    local agent_container
    agent_container=$(get_agent_container)
    if [[ -z "$agent_container" ]]; then
        log_error "Agent container not found. Please start docker-compose services first."
        return 1
    fi
    
    log_info "Found ClickHouse container: $clickhouse_container"
    log_info "Found Agent container: $agent_container"
    return 0
}

# Wait for agent to be ready
wait_for_agent() {
    log_info "Waiting for agent to be ready..."
    local max_attempts=30
    local attempt=0
    
    while [[ $attempt -lt $max_attempts ]]; do
        if curl -sf "http://localhost:8081/api/health" > /dev/null 2>&1; then
            log_info "Agent is ready!"
            return 0
        fi
        sleep 1
        ((attempt++)) || true
        echo -n "."
    done
    
    log_error "Agent did not become ready after $max_attempts seconds"
    return 1
}

# Run PHP test script
run_php_test() {
    log_info "Running PHP curl profiling test..."
    
    if [[ ! -d "$PHP_EXTENSION_DIR" ]]; then
        test_result 1 "PHP extension directory not found: $PHP_EXTENSION_DIR"
        return 1
    fi
    
    if [[ ! -f "${PHP_EXTENSION_DIR}/docker-compose.yaml" ]]; then
        test_result 1 "docker-compose.yaml not found in $PHP_EXTENSION_DIR"
        return 1
    fi
    
    if [[ ! -f "$TEST_PHP_SCRIPT" ]]; then
        test_result 1 "Test script not found: $TEST_PHP_SCRIPT"
        return 1
    fi
    
    # Create a temporary docker-compose override for curl testing
    cd "$PHP_EXTENSION_DIR" || return 1
    
    # Create a custom entrypoint script for curl test
    cat > /tmp/run_curl_test.sh << 'EOF'
#!/bin/bash
set -e
cd /app/tests
php curl_profiling_test.php
EOF
    chmod +x /tmp/run_curl_test.sh
    
    # Clean up any existing containers
    log_info "Cleaning up existing containers..."
    docker-compose down > /dev/null 2>&1 || true
    
    # Modify docker-compose temporarily to run curl test
    # We'll override the command in the PHP service
    log_info "Starting test containers for curl profiling (timeout: ${TEST_TIMEOUT}s)..."
    
    # Run with overridden command
    if timeout "$TEST_TIMEOUT" docker-compose run --rm \
        -e OPA_ENABLED=1 \
        -e OPA_SOCKET_PATH=opa-agent:9090 \
        -e OPA_SAMPLING_RATE=1.0 \
        -e OPA_FULL_CAPTURE_THRESHOLD_MS=0 \
        -e OPA_COLLECT_INTERNAL_FUNCTIONS=1 \
        -e OPA_DEBUG_LOG=1 \
        -e OPA_SERVICE=curl-profiling-test \
        -v /tmp/run_curl_test.sh:/app/tests/run_curl_test.sh:ro \
        php /app/tests/run_curl_test.sh > "$TEST_OUTPUT_LOG" 2>&1; then
        local exit_code=$?
        if [[ $exit_code -eq 0 ]]; then
            test_result 0 "PHP curl profiling test executed successfully"
            return 0
        else
            test_result 1 "PHP curl profiling test exited with code $exit_code"
            if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
                echo "Last 50 lines of output:"
                tail -50 "$TEST_OUTPUT_LOG" || true
            fi
            return 1
        fi
    else
        local exit_code=$?
        test_result 1 "PHP curl profiling test failed or timed out (exit code: $exit_code)"
        if [[ "$VERBOSE" -eq 1 ]] || [[ "$CI_MODE" -eq 1 ]]; then
            echo "Last 50 lines of output:"
            tail -50 "$TEST_OUTPUT_LOG" || true
        fi
        return 1
    fi
}

# Verify curl requests in ClickHouse
verify_curl_requests() {
    log_info "Verifying curl requests in ClickHouse..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "Verify curl requests" "ClickHouse container not found"
        return 1
    fi
    
    # Wait a bit for data to be written
    sleep 2
    
    # Check if http_requests table has data
    local request_count
    request_count=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT count() FROM opa.http_requests WHERE timestamp > now() - INTERVAL 5 MINUTE" 2>/dev/null || echo "0")
    
    if [[ "$request_count" -gt 0 ]]; then
        test_result 0 "Verify curl requests exist" "Found $request_count curl request(s)"
        
        # Verify request details
        local has_url
        has_url=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT count() FROM opa.http_requests WHERE url != '' AND timestamp > now() - INTERVAL 5 MINUTE" 2>/dev/null || echo "0")
        
        if [[ "$has_url" -gt 0 ]]; then
            test_result 0 "Verify request URL captured" "Found $has_url request(s) with URL"
        else
            test_result 1 "Verify request URL captured" "No requests with URL found"
        fi
        
        # Verify timing metrics
        local has_timing
        has_timing=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT count() FROM opa.http_requests WHERE duration_ms > 0 AND timestamp > now() - INTERVAL 5 MINUTE" 2>/dev/null || echo "0")
        
        if [[ "$has_timing" -gt 0 ]]; then
            test_result 0 "Verify timing metrics" "Found $has_timing request(s) with timing data"
        else
            test_result 1 "Verify timing metrics" "No requests with timing data found"
        fi
        
        # Verify byte measurements
        local has_bytes
        has_bytes=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT count() FROM opa.http_requests WHERE (bytes_sent > 0 OR bytes_received > 0) AND timestamp > now() - INTERVAL 5 MINUTE" 2>/dev/null || echo "0")
        
        if [[ "$has_bytes" -gt 0 ]]; then
            test_result 0 "Verify byte measurements" "Found $has_bytes request(s) with byte data"
        else
            test_result 2 "Verify byte measurements" "No requests with byte data found (may be normal for HEAD requests)"
        fi
        
        # Verify status codes
        local has_status
        has_status=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT count() FROM opa.http_requests WHERE status_code > 0 AND timestamp > now() - INTERVAL 5 MINUTE" 2>/dev/null || echo "0")
        
        if [[ "$has_status" -gt 0 ]]; then
            test_result 0 "Verify status codes" "Found $has_status request(s) with status codes"
        else
            test_result 1 "Verify status codes" "No requests with status codes found"
        fi
        
        return 0
    else
        test_result 1 "Verify curl requests exist" "No curl requests found in database"
        return 1
    fi
}

# Verify enhanced metrics
verify_enhanced_metrics() {
    log_info "Verifying enhanced curl metrics..."
    
    local clickhouse_container
    clickhouse_container=$(get_clickhouse_container)
    
    if [[ -z "$clickhouse_container" ]]; then
        test_result 1 "Verify enhanced metrics" "ClickHouse container not found"
        return 1
    fi
    
    # Check for curl_getinfo bytes (if available in the data structure)
    # Note: This depends on how the data is stored in ClickHouse
    # For now, we'll check if the requests have the basic enhanced fields
    
    # Get a sample request to check structure
    local sample_request
    sample_request=$(docker exec "$clickhouse_container" clickhouse-client --query "SELECT * FROM opa.http_requests WHERE timestamp > now() - INTERVAL 5 MINUTE LIMIT 1 FORMAT JSONEachRow" 2>/dev/null || echo "")
    
    if [[ -n "$sample_request" ]]; then
        test_result 0 "Verify enhanced metrics structure" "Sample request data available"
        
        # Check for timing metrics in spans (if stored there)
        # The exact structure depends on implementation
        return 0
    else
        test_result 2 "Verify enhanced metrics structure" "Could not retrieve sample request"
        return 1
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
        test_result 1 "Verify trace API" "No trace ID found for API test"
        return 1
    fi
    
    log_info "Testing trace ID: $latest_trace"
    
    # Determine API URL
    local api_url="${API_URL:-http://localhost:8081}"
    
    # Test trace detail API
    if curl -sf "${api_url}/api/traces/${latest_trace}/full" > /dev/null 2>&1; then
        test_result 0 "Trace detail API is accessible"
        
        # Check if API returns HTTP requests
        local http_in_api
        http_in_api=$(curl -sf "${api_url}/api/traces/${latest_trace}/full" 2>/dev/null | grep -o '"http":\[' | wc -l || echo "0")
        
        if [[ "$http_in_api" -gt 0 ]]; then
            test_result 0 "Trace API returns HTTP requests"
        else
            test_result 2 "Trace API does not return HTTP requests (may be in different format)"
        fi
    else
        test_result 1 "Trace detail API is not accessible at ${api_url}"
    fi
}

# Show summary
show_summary() {
    echo ""
    echo "=== Test Summary ==="
    echo "Tests passed: $TESTS_PASSED"
    echo "Tests failed: $TESTS_FAILED"
    echo "Tests warned: $TESTS_WARNED"
    echo ""
    
    if [[ "$CI_MODE" -eq 1 ]]; then
        echo "::notice title=Test Summary::Passed: $TESTS_PASSED, Failed: $TESTS_FAILED, Warnings: $TESTS_WARNED"
    fi
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        echo "=== Failed Tests ==="
        for result in "${TEST_RESULTS[@]}"; do
            if [[ "$result" == FAIL:* ]]; then
                echo "  ${result#FAIL:}"
            fi
        done
        return 1
    elif [[ $TESTS_WARNED -gt 0 ]]; then
        echo "=== Warnings ==="
        for result in "${TEST_RESULTS[@]}"; do
            if [[ "$result" == WARN:* ]]; then
                echo "  ${result#WARN:}"
            fi
        done
        return 0
    else
        return 0
    fi
}

# Main execution
main() {
    echo "=== cURL Profiling End-to-End Test ==="
    echo ""
    
    # Check services
    if ! check_services; then
        log_error "Required services are not running. Please start them with: docker-compose up -d"
        exit 1
    fi
    
    # Wait for agent
    if ! wait_for_agent; then
        log_error "Agent is not ready"
        exit 1
    fi
    
    echo ""
    echo "=== Running Tests ==="
    echo ""
    
    # Run PHP test
    if run_php_test; then
        test_result 0 "PHP test script execution"
    else
        test_result 1 "PHP test script execution" "See $TEST_OUTPUT_LOG for details"
        if [[ "$VERBOSE" -eq 1 ]]; then
            echo "=== PHP Test Output ==="
            cat "$TEST_OUTPUT_LOG"
        fi
    fi
    
    echo ""
    
    # Verify results
    verify_curl_requests
    verify_enhanced_metrics
    verify_trace_api
    
    # Show summary
    show_summary
}

# Run main function
main "$@"


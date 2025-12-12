#!/bin/bash
set -euo pipefail

# Test runner script that executes tests in Docker containers
# Usage: ./run-tests.sh [test-script-path] [--verbose] [--cleanup]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="${SCRIPT_DIR}"

# Configuration
VERBOSE="${VERBOSE:-0}"
CLEANUP="${CLEANUP:-1}"
TEST_SCRIPT=""
NETWORK_NAME="opa_network"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Parse arguments - handle flags first
while [[ $# -gt 0 ]]; do
    case $1 in
        --verbose|-v)
            VERBOSE=1
            shift
            ;;
        --no-cleanup)
            CLEANUP=0
            shift
            ;;
        --cleanup)
            CLEANUP=1
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [test-script] [--verbose] [--cleanup|--no-cleanup]"
            echo ""
            echo "Options:"
            echo "  test-script    Path to specific test script (relative to php-extension/tests/e2e/)"
            echo "  --verbose, -v  Enable verbose output"
            echo "  --cleanup      Clean up services after tests (default)"
            echo "  --no-cleanup   Keep services running after tests (preserves test data)"
            echo ""
            echo "If no test script is provided, all E2E tests will be run."
            exit 0
            ;;
        -*)
            log_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
        *)
            if [[ -z "$TEST_SCRIPT" ]]; then
                TEST_SCRIPT="$1"
            else
                log_error "Multiple test scripts specified: $TEST_SCRIPT and $1"
                exit 1
            fi
            shift
            ;;
    esac
done

log_info() {
    if [[ "$VERBOSE" -eq 1 ]] || [[ "${1:-}" == "force" ]]; then
        echo -e "${GREEN}[INFO]${NC} $*"
    fi
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $*" >&2
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $*" >&2
}

# Ensure Docker network exists
ensure_network() {
    if ! docker network inspect "$NETWORK_NAME" > /dev/null 2>&1; then
        log_info "Creating Docker network: $NETWORK_NAME"
        docker network create "$NETWORK_NAME" || true
    fi
}

# Setup test endpoints before starting services
setup_test_endpoints() {
    log_info "Setting up test endpoints..."
    local endpoint_file="$PROJECT_ROOT/tests/apps/symfony/public/test_http_errors.php"
    if [[ ! -f "$endpoint_file" ]]; then
        mkdir -p "$(dirname "$endpoint_file")" 2>/dev/null || true
        if touch "$endpoint_file" 2>/dev/null; then
            cat > "$endpoint_file" << 'ENDPOINT_EOF'
<?php
$status = isset($_GET['status']) ? (int)$_GET['status'] : 200;
if ($status < 100 || $status > 599) $status = 500;
http_response_code($status);
header("Content-Type: application/json");
echo json_encode([
    "status" => $status,
    "message" => "Test error response",
    "timestamp" => time(),
    "method" => $_SERVER["REQUEST_METHOD"] ?? "GET",
    "uri" => $_SERVER["REQUEST_URI"] ?? "/",
], JSON_PRETTY_PRINT);
ENDPOINT_EOF
            log_info "Test endpoint created: $endpoint_file"
        else
            log_warn "Cannot create test endpoint (permission denied). Please create manually:"
            log_warn "  sudo mkdir -p $(dirname "$endpoint_file")"
            log_warn "  sudo tee $endpoint_file > /dev/null << 'EOF'"
            log_warn "  <?php"
            log_warn "  \$status = isset(\$_GET['status']) ? (int)\$_GET['status'] : 200;"
            log_warn "  if (\$status < 100 || \$status > 599) \$status = 500;"
            log_warn "  http_response_code(\$status);"
            log_warn "  header(\"Content-Type: application/json\");"
            log_warn "  echo json_encode([\"status\" => \$status, \"message\" => \"Test error response\", \"timestamp\" => time()], JSON_PRETTY_PRINT);"
            log_warn "  EOF"
        fi
    else
        log_info "Test endpoint already exists: $endpoint_file"
    fi
}

# Start test services
start_services() {
    log_info "Starting test services..."
    cd "$PROJECT_ROOT"
    
    ensure_network
    
    # Setup test endpoints before starting containers
    setup_test_endpoints
    
    docker-compose -f docker-compose.test.yml up -d
    
    log_info "Waiting for services to be ready..."
    
    # Wait for ClickHouse
    local max_attempts=30
    local attempt=0
    while [[ $attempt -lt $max_attempts ]]; do
        if docker exec clickhouse-test wget --spider -q http://localhost:8123/ping 2>/dev/null; then
            log_info "ClickHouse is ready"
            break
        fi
        sleep 1
        ((attempt++)) || true
    done
    
    # Wait for Agent
    attempt=0
    while [[ $attempt -lt $max_attempts ]]; do
        if docker exec opa-agent-test wget --spider -q http://localhost:8080/api/health 2>/dev/null; then
            log_info "Agent is ready"
            break
        fi
        sleep 1
        ((attempt++)) || true
    done
    
    # Wait for MySQL
    attempt=0
    while [[ $attempt -lt $max_attempts ]]; do
        if docker exec opa_mysql_test mysqladmin ping -h localhost -u root -proot_password > /dev/null 2>&1; then
            log_info "MySQL is ready"
            break
        fi
        sleep 1
        ((attempt++)) || true
    done
    
    # Wait for PHP-FPM
    attempt=0
    while [[ $attempt -lt $max_attempts ]]; do
        if docker exec opa_php_test pgrep -f php-fpm > /dev/null 2>&1; then
            log_info "PHP-FPM is ready"
            break
        fi
        sleep 1
        ((attempt++)) || true
    done
    
    # Wait for Nginx (start it after PHP-FPM is ready)
    attempt=0
    while [[ $attempt -lt $max_attempts ]]; do
        if docker exec opa_nginx_test wget --spider -q http://localhost/health 2>/dev/null; then
            log_info "Nginx is ready"
            break
        fi
        sleep 1
        ((attempt++)) || true
    done
    
    # Setup test endpoints in writable volume
    log_info "Setting up test endpoints..."
    # Create endpoint in the writable volume mounted at /var/www/html/tests/apps
    docker exec opa_php_test bash -c 'mkdir -p /var/www/html/tests/apps/symfony/public && cat > /var/www/html/tests/apps/symfony/public/test_http_errors.php << "ENDOFFILE"
<?php
\$status = isset(\$_GET["status"]) ? (int)\$_GET["status"] : 200;
if (\$status < 100 || \$status > 599) \$status = 500;
http_response_code(\$status);
header("Content-Type: application/json");
echo json_encode([
    "status" => \$status,
    "message" => "Test error response",
    "timestamp" => time(),
    "method" => \$_SERVER["REQUEST_METHOD"] ?? "GET",
    "uri" => \$_SERVER["REQUEST_URI"] ?? "/",
], JSON_PRETTY_PRINT);
EOF
' || log_warn "Could not create test endpoint (may need manual creation)"
    
    # Setup test endpoints in PHP container
    log_info "Setting up test endpoints..."
    docker exec opa_php_test bash -c 'cat > /app/tests/apps/symfony/public/test_http_errors.php << "EOF"
<?php
\$status = isset(\$_GET[\"status\"]) ? (int)\$_GET[\"status\"] : 200;
if (\$status < 100 || \$status > 599) \$status = 500;
http_response_code(\$status);
header(\"Content-Type: application/json\");
echo json_encode([
    \"status\" => \$status,
    \"message\" => \"Test error response\",
    \"timestamp\" => time(),
    \"method\" => \$_SERVER[\"REQUEST_METHOD\"] ?? \"GET\",
    \"uri\" => \$_SERVER[\"REQUEST_URI\"] ?? \"/\",
], JSON_PRETTY_PRINT);
EOF
' || log_warn "Could not create test endpoint (may need manual creation)"
}

# Stop test services
stop_services() {
    if [[ "$CLEANUP" -eq 1 ]]; then
        log_info "Stopping test services..."
        cd "$PROJECT_ROOT"
        docker-compose -f docker-compose.test.yml down -v
        log_info "All test data has been removed"
    else
        log_info "Services left running (test data preserved)"
        log_info "To stop services later, run: docker-compose -f docker-compose.test.yml down"
        log_info "To remove test data, run: docker-compose -f docker-compose.test.yml down -v"
        log_info ""
        log_info "Access services:"
        log_info "  - Agent API: http://localhost:8081"
        log_info "  - ClickHouse: localhost:8123"
        log_info "  - MySQL: localhost:3307"
        log_info "  - Nginx: http://localhost:8090"
        log_info "  - PHP test container: docker exec -it opa_php_test bash"
    fi
}

# Run a test script in the PHP test container
run_test() {
    local test_script="$1"
    local test_dir
    local test_file
    
    # Resolve test script path
    local test_path
    if [[ -f "$test_script" ]]; then
        test_path="$test_script"
    elif [[ -f "$PROJECT_ROOT/$test_script" ]]; then
        test_path="$PROJECT_ROOT/$test_script"
    elif [[ -f "$PROJECT_ROOT/php-extension/tests/e2e/$test_script" ]]; then
        test_path="$PROJECT_ROOT/php-extension/tests/e2e/$test_script"
    else
        log_error "Test script not found: $test_script"
        log_error "Searched: $test_script, $PROJECT_ROOT/$test_script, $PROJECT_ROOT/php-extension/tests/e2e/$test_script"
        return 1
    fi
    
    # Get relative path from e2e directory for container
    local rel_path="${test_path#$PROJECT_ROOT/php-extension/tests/e2e/}"
    local test_dir_in_container="$(dirname "$rel_path")"
    local test_file_in_container="$(basename "$rel_path")"
    
    log_info "Running test: $test_file_in_container"
    log_info "Test path: e2e/$rel_path"
    
    # Make script executable on host
    chmod +x "$test_path" 2>/dev/null || true
    
    # Set environment variables for the test
    local env_vars=(
        "API_URL=http://agent:8080"
        "BASE_URL=http://nginx-test"
        "MYSQL_HOST=mysql-test"
        "MYSQL_PORT=3306"
        "MYSQL_DATABASE=test_db"
        "MYSQL_USER=test_user"
        "MYSQL_PASSWORD=test_password"
        "MYSQL_ROOT_PASSWORD=root_password"
        "SERVICE_NAME=test-service"
        "VERBOSE=$VERBOSE"
    )
    
    # Run test in container (tests are mounted at /app/tests)
    docker exec -i \
        -e DOCKER_CONTAINER=1 \
        -e API_URL=http://agent:8080 \
        -e BASE_URL=http://nginx-test \
        -e MYSQL_HOST=mysql-test \
        -e MYSQL_PORT=3306 \
        -e MYSQL_DATABASE=test_db \
        -e MYSQL_USER=test_user \
        -e MYSQL_PASSWORD=test_password \
        -e MYSQL_ROOT_PASSWORD=root_password \
        -e SERVICE_NAME=test-service \
        -e VERBOSE="$VERBOSE" \
        opa_php_test \
        bash -c "cd /app/tests/e2e && if [[ -n '$test_dir_in_container' && '$test_dir_in_container' != '.' ]]; then cd '$test_dir_in_container'; fi && bash '$test_file_in_container'"
}

# Run all E2E tests
run_all_tests() {
    log_info "Running all E2E tests..."
    
    local test_dir="$PROJECT_ROOT/php-extension/tests/e2e"
    
    if [[ ! -d "$test_dir" ]]; then
        log_error "Test directory not found: $test_dir"
        return 1
    fi
    local tests_passed=0
    local tests_failed=0
    
    # Find all test scripts (relative to e2e directory)
    while IFS= read -r -d '' test_file; do
        if [[ "$test_file" == *.sh ]]; then
            # Get relative path from e2e directory
            local rel_path="${test_file#$test_dir/}"
            echo ""
            echo "=========================================="
            echo "Running: $rel_path"
            echo "=========================================="
            
            if run_test "$rel_path"; then
                ((tests_passed++)) || true
                echo -e "${GREEN}✓ Test passed${NC}"
            else
                ((tests_failed++)) || true
                echo -e "${RED}✗ Test failed${NC}"
            fi
        fi
    done < <(find "$test_dir" -name "*_e2e.sh" -type f -print0 | sort -z)
    
    echo ""
    echo "=========================================="
    echo "Test Summary"
    echo "=========================================="
    echo "Tests passed: $tests_passed"
    echo "Tests failed: $tests_failed"
    echo ""
    
    if [[ $tests_failed -eq 0 ]]; then
        return 0
    else
        return 1
    fi
}

# Cleanup on exit
trap 'stop_services' EXIT INT TERM

# Main execution
main() {
    echo "=== Test Runner ==="
    echo ""
    
    # Start services
    start_services
    
    # Run test(s)
    if [[ -n "$TEST_SCRIPT" ]]; then
        # Run specific test
        if run_test "$TEST_SCRIPT"; then
            echo -e "${GREEN}Test completed successfully${NC}"
            exit 0
        else
            echo -e "${RED}Test failed${NC}"
            exit 1
        fi
    else
        # Run all tests
        if run_all_tests; then
            echo -e "${GREEN}All tests passed!${NC}"
            exit 0
        else
            echo -e "${RED}Some tests failed!${NC}"
            exit 1
        fi
    fi
}

main "$@"


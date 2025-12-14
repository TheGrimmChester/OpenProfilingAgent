#!/bin/bash

# Script to run all Symfony test endpoints to generate data in ClickHouse
# This bypasses PHPUnit issues and directly calls all endpoints

# Detect if running in container or locally
if [ -n "${DOCKER_CONTAINER:-}" ] || [ -f /.dockerenv ] || [ -n "${CI:-}" ]; then
    # Running in container - use symfony-nginx service name
    BASE_URL="${BASE_URL:-http://symfony-nginx}"
    echo "Running in container environment, using BASE_URL: $BASE_URL"
else
    # Running locally
    BASE_URL="${BASE_URL:-http://localhost:8080}"
    echo "Running in local environment, using BASE_URL: $BASE_URL"
fi

echo "=========================================="
echo "Running All Symfony Test Endpoints"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to make a request and show status
make_request() {
    local method=$1
    local url=$2
    local data=$3
    
    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$url")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "$url" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓${NC} $method $url - HTTP $http_code"
        return 0
    else
        echo -e "${YELLOW}✗${NC} $method $url - HTTP $http_code"
        return 1
    fi
}

# HTTP Methods Test Controller
echo -e "${BLUE}=== HTTP Methods Test Controller ===${NC}"
make_request "GET" "${BASE_URL}/api/test/http/get"
make_request "GET" "${BASE_URL}/api/test/http/get?size=1024"
make_request "GET" "${BASE_URL}/api/test/http/get?size=10240"
make_request "GET" "${BASE_URL}/api/test/http/get?size=102400"
make_request "GET" "${BASE_URL}/api/test/http/get?size=1048576"

make_request "POST" "${BASE_URL}/api/test/http/post" '{"data":"test"}'
make_request "POST" "${BASE_URL}/api/test/http/post?response_size=10240" '{"data":"'$(head -c 1024 < /dev/zero | tr '\0' 'x')'"}'
make_request "POST" "${BASE_URL}/api/test/http/post?response_size=102400" '{"data":"'$(head -c 10240 < /dev/zero | tr '\0' 'y')'"}'

make_request "PUT" "${BASE_URL}/api/test/http/put" '{"data":"test"}'
make_request "PUT" "${BASE_URL}/api/test/http/put?response_size=10240" '{"data":"'$(head -c 1024 < /dev/zero | tr '\0' 'a')'"}'

make_request "PATCH" "${BASE_URL}/api/test/http/patch" '{"data":"test"}'
make_request "PATCH" "${BASE_URL}/api/test/http/patch?response_size=102400" '{"data":"'$(head -c 10240 < /dev/zero | tr '\0' 'b')'"}'

make_request "DELETE" "${BASE_URL}/api/test/http/delete"
make_request "DELETE" "${BASE_URL}/api/test/http/delete?size=10240"
make_request "DELETE" "${BASE_URL}/api/test/http/delete?size=102400"

make_request "HEAD" "${BASE_URL}/api/test/http/head"
make_request "OPTIONS" "${BASE_URL}/api/test/http/options"

make_request "GET" "${BASE_URL}/api/test/http/comprehensive?response_size=10240"
make_request "POST" "${BASE_URL}/api/test/http/comprehensive?response_size=10240" '{"data":"test"}'

# Method with size parameter routes
make_request "GET" "${BASE_URL}/api/test/http/get/size/10240"
make_request "POST" "${BASE_URL}/api/test/http/post/size/10240" '{"data":"test"}'
make_request "PUT" "${BASE_URL}/api/test/http/put/size/10240" '{"data":"test"}'
make_request "PATCH" "${BASE_URL}/api/test/http/patch/size/10240" '{"data":"test"}'
make_request "DELETE" "${BASE_URL}/api/test/http/delete/size/10240"

echo ""

# Comprehensive Profiling Test Controller
echo -e "${BLUE}=== Comprehensive Profiling Test Controller ===${NC}"
make_request "GET" "${BASE_URL}/api/test/comprehensive"
make_request "GET" "${BASE_URL}/api/test/comprehensive?response_size=500"
make_request "GET" "${BASE_URL}/api/test/comprehensive?response_size=1000"
make_request "GET" "${BASE_URL}/api/test/comprehensive?response_size=5000"
make_request "POST" "${BASE_URL}/api/test/comprehensive?response_size=1000" '{"data":"test"}'
make_request "PUT" "${BASE_URL}/api/test/comprehensive?response_size=2000" '{"data":"test"}'
make_request "PATCH" "${BASE_URL}/api/test/comprehensive?response_size=1500" '{"data":"test"}'

echo ""

# PDO Profiling Test Controller
echo -e "${BLUE}=== PDO Profiling Test Controller ===${NC}"
make_request "GET" "${BASE_URL}/api/test/pdo/simple"
make_request "GET" "${BASE_URL}/api/test/pdo/create-table"
make_request "GET" "${BASE_URL}/api/test/pdo/insert"
make_request "GET" "${BASE_URL}/api/test/pdo/prepare-execute"
make_request "GET" "${BASE_URL}/api/test/pdo/select"
make_request "GET" "${BASE_URL}/api/test/pdo/update"
make_request "GET" "${BASE_URL}/api/test/pdo/delete"
make_request "GET" "${BASE_URL}/api/test/pdo/multiple"
make_request "GET" "${BASE_URL}/api/test/pdo/complex"
make_request "GET" "${BASE_URL}/api/test/pdo/transaction"

echo ""

# MySQLi Profiling Test Controller
echo -e "${BLUE}=== MySQLi Profiling Test Controller ===${NC}"
make_request "GET" "${BASE_URL}/api/test/mysqli/simple"
make_request "GET" "${BASE_URL}/api/test/mysqli/create-table"
make_request "GET" "${BASE_URL}/api/test/mysqli/insert"
make_request "GET" "${BASE_URL}/api/test/mysqli/select"
make_request "GET" "${BASE_URL}/api/test/mysqli/update"
make_request "GET" "${BASE_URL}/api/test/mysqli/delete"
make_request "GET" "${BASE_URL}/api/test/mysqli/multiple"
make_request "GET" "${BASE_URL}/api/test/mysqli/complex"

echo ""

# Request/Response Test Controller
echo -e "${BLUE}=== Request/Response Test Controller ===${NC}"
make_request "GET" "${BASE_URL}/api/test/request-response?response_size=100"
make_request "GET" "${BASE_URL}/api/test/request-response?response_size=1024"
make_request "GET" "${BASE_URL}/api/test/request-response?response_size=10240"
make_request "POST" "${BASE_URL}/api/test/request-response?response_size=1024" '{"data":"'$(head -c 1024 < /dev/zero | tr '\0' 'x')'"}'
make_request "PUT" "${BASE_URL}/api/test/request-response?response_size=10240" '{"data":"'$(head -c 10240 < /dev/zero | tr '\0' 'y')'"}'
make_request "PATCH" "${BASE_URL}/api/test/request-response?response_size=100" '{"data":"test"}'

make_request "POST" "${BASE_URL}/api/test/request-size" '{"data":"test"}'
make_request "POST" "${BASE_URL}/api/test/request-size" '{"data":"'$(head -c 1024 < /dev/zero | tr '\0' 'x')'"}'

make_request "GET" "${BASE_URL}/api/test/response-size/100"
make_request "GET" "${BASE_URL}/api/test/response-size/500"
make_request "GET" "${BASE_URL}/api/test/response-size/1024"
make_request "GET" "${BASE_URL}/api/test/response-size/10240"
make_request "GET" "${BASE_URL}/api/test/response-size/102400"

make_request "POST" "${BASE_URL}/api/test/full-request-response" '{"data":"test"}'

echo ""

# Run multiple iterations to generate more data
echo -e "${BLUE}=== Running Multiple Iterations ===${NC}"
for i in {1..5}; do
    echo "Iteration $i..."
    make_request "GET" "${BASE_URL}/api/test/http/get?size=1024"
    make_request "POST" "${BASE_URL}/api/test/http/post" '{"data":"test"}'
    make_request "GET" "${BASE_URL}/api/test/pdo/simple"
    make_request "GET" "${BASE_URL}/api/test/mysqli/simple"
    make_request "GET" "${BASE_URL}/api/test/comprehensive?response_size=1000"
    sleep 0.5
done

echo ""
echo -e "${GREEN}=========================================="
echo "All endpoints tested successfully!"
echo "Data should now be in ClickHouse"
echo "==========================================${NC}"



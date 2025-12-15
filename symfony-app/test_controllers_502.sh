#!/bin/bash

# Comprehensive test script to call all Symfony controllers and verify no 502 errors
# This ensures the PHP extension doesn't cause crashes or gateway errors

set -e

BASE_URL="${BASE_URL:-http://localhost:8080}"
MAX_RETRIES=3
RETRY_DELAY=2
FAILED_TESTS=0
TOTAL_TESTS=0
PASSED_TESTS=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to test an endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    shift 4
    local extra_headers=("$@")
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -n "  Testing $description... "
    
    local response_code
    local response_body
    local retry_count=0
    
    while [ $retry_count -lt $MAX_RETRIES ]; do
        local curl_cmd=()
        curl_cmd+=("curl" "-s" "-o" "/tmp/response_body.json" "-w" "%{http_code}")
        curl_cmd+=("-X" "$method")
        curl_cmd+=("-H" "Accept: application/json")
        
        # Add extra headers if provided
        for header in "${extra_headers[@]}"; do
            curl_cmd+=("-H" "$header")
        done
        
        if [ -n "$data" ]; then
            curl_cmd+=("-H" "Content-Type: application/json")
            curl_cmd+=("-d" "$data")
        fi
        
        curl_cmd+=("$BASE_URL$endpoint")
        
        response_code=$("${curl_cmd[@]}" 2>/dev/null || echo "000")
        
        response_body=$(cat /tmp/response_body.json 2>/dev/null || echo "")
        
        # Check for 502 Bad Gateway (PHP-FPM crash/error)
        if [ "$response_code" = "502" ]; then
            echo -e "${RED}FAILED${NC} - Got 502 Bad Gateway (PHP-FPM may have crashed)"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo "    Response: $response_body"
            return 1
        fi
        
        # Check for 503 Service Unavailable
        if [ "$response_code" = "503" ]; then
            echo -e "${RED}FAILED${NC} - Got 503 Service Unavailable"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo "    Response: $response_body"
            return 1
        fi
        
        # Check for connection errors (000)
        if [ "$response_code" = "000" ]; then
            if [ $retry_count -lt $((MAX_RETRIES - 1)) ]; then
                echo -n "(retry $((retry_count + 1))/$MAX_RETRIES)... "
                sleep $RETRY_DELAY
                retry_count=$((retry_count + 1))
                continue
            else
                echo -e "${RED}FAILED${NC} - Connection error"
                FAILED_TESTS=$((FAILED_TESTS + 1))
                return 1
            fi
        fi
        
        # Success (200-299) or expected client errors (400-499)
        if [ "$response_code" -ge 200 ] && [ "$response_code" -lt 500 ]; then
            echo -e "${GREEN}PASS${NC} (HTTP $response_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        fi
        
        # Server errors (500-599) except 502/503 which we already checked
        if [ "$response_code" -ge 500 ]; then
            echo -e "${RED}FAILED${NC} - Got HTTP $response_code"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo "    Response: $response_body"
            return 1
        fi
        
        break
    done
    
    echo -e "${YELLOW}UNKNOWN${NC} - Got HTTP $response_code"
    return 1
}

# Function to check PHP-FPM status
check_phpfpm_status() {
    echo "Checking PHP-FPM status..."
    if docker exec symfony-php pgrep -f php-fpm > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PHP-FPM is running${NC}"
        return 0
    else
        echo -e "${RED}✗ PHP-FPM is not running!${NC}"
        return 1
    fi
}

echo "=========================================="
echo "Symfony Controller 502 Error Test Suite"
echo "=========================================="
echo "Base URL: $BASE_URL"
echo ""

# Check PHP-FPM status before starting
if ! check_phpfpm_status; then
    echo "Error: PHP-FPM is not running. Cannot proceed with tests."
    exit 1
fi

echo ""
echo "=========================================="
echo "Testing IP Address Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/ip-address" "" "IP Address Test (GET)"
test_endpoint "GET" "/api/test/ip-address" "" "IP Address Test with X-Forwarded-For" "X-Forwarded-For: 192.168.1.100"
test_endpoint "GET" "/api/test/ip-address" "" "IP Address Test with X-Real-IP" "X-Real-IP: 203.0.113.45"

echo ""
echo "=========================================="
echo "Testing Request/Response Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/request-response" "" "Request/Response Test (GET)"
test_endpoint "POST" "/api/test/request-response" '{"test":"data"}' "Request/Response Test (POST)"
test_endpoint "GET" "/api/test/request-size" "" "Request Size Test"
test_endpoint "POST" "/api/test/request-size" '{"data":"test"}' "Request Size Test (POST)"
test_endpoint "GET" "/api/test/response-size/100" "" "Response Size Test (100 bytes)"
test_endpoint "GET" "/api/test/response-size/1024" "" "Response Size Test (1KB)"
test_endpoint "POST" "/api/test/full-request-response" '{"test":"full"}' "Full Request/Response Test"

echo ""
echo "=========================================="
echo "Testing HTTP Methods Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/http/get" "" "HTTP GET Test"
test_endpoint "POST" "/api/test/http/post" '{"method":"POST"}' "HTTP POST Test"
test_endpoint "PUT" "/api/test/http/put" '{"method":"PUT"}' "HTTP PUT Test"
test_endpoint "PATCH" "/api/test/http/patch" '{"method":"PATCH"}' "HTTP PATCH Test"
test_endpoint "DELETE" "/api/test/http/delete" "" "HTTP DELETE Test"
test_endpoint "HEAD" "/api/test/http/head" "" "HTTP HEAD Test"
test_endpoint "OPTIONS" "/api/test/http/options" "" "HTTP OPTIONS Test"
test_endpoint "GET" "/api/test/http/comprehensive" "" "HTTP Comprehensive Test"

echo ""
echo "=========================================="
echo "Testing Redis Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/redis/simple" "" "Redis Simple Test"
test_endpoint "GET" "/api/test/redis/set-get" "" "Redis SET/GET Test"
test_endpoint "GET" "/api/test/redis/delete" "" "Redis DELETE Test"
test_endpoint "GET" "/api/test/redis/exists" "" "Redis EXISTS Test"
test_endpoint "GET" "/api/test/redis/incr-decr" "" "Redis INCR/DECR Test"
test_endpoint "GET" "/api/test/redis/hash" "" "Redis Hash Test"
test_endpoint "GET" "/api/test/redis/list" "" "Redis List Test"
test_endpoint "GET" "/api/test/redis/set" "" "Redis Set Test"
test_endpoint "GET" "/api/test/redis/expire" "" "Redis Expire Test"
test_endpoint "GET" "/api/test/redis/multiple" "" "Redis Multiple Operations"
test_endpoint "GET" "/api/test/redis/comprehensive" "" "Redis Comprehensive Test"

echo ""
echo "=========================================="
echo "Testing MySQLi Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/mysqli/simple" "" "MySQLi Simple Query"
test_endpoint "GET" "/api/test/mysqli/create-table" "" "MySQLi Create Table"
test_endpoint "GET" "/api/test/mysqli/insert" "" "MySQLi Insert"
test_endpoint "GET" "/api/test/mysqli/select" "" "MySQLi Select"
test_endpoint "GET" "/api/test/mysqli/update" "" "MySQLi Update"
test_endpoint "GET" "/api/test/mysqli/delete" "" "MySQLi Delete"
test_endpoint "GET" "/api/test/mysqli/multiple" "" "MySQLi Multiple Queries"
test_endpoint "GET" "/api/test/mysqli/complex" "" "MySQLi Complex Query"

echo ""
echo "=========================================="
echo "Testing PDO Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/pdo/simple" "" "PDO Simple Query"
test_endpoint "GET" "/api/test/pdo/create-table" "" "PDO Create Table"
test_endpoint "GET" "/api/test/pdo/insert" "" "PDO Insert"
test_endpoint "GET" "/api/test/pdo/prepare-execute" "" "PDO Prepared Statement"
test_endpoint "GET" "/api/test/pdo/select" "" "PDO Select"
test_endpoint "GET" "/api/test/pdo/update" "" "PDO Update"
test_endpoint "GET" "/api/test/pdo/delete" "" "PDO Delete"
test_endpoint "GET" "/api/test/pdo/multiple" "" "PDO Multiple Queries"
test_endpoint "GET" "/api/test/pdo/complex" "" "PDO Complex Query"
test_endpoint "GET" "/api/test/pdo/transaction" "" "PDO Transaction"

echo ""
echo "=========================================="
echo "Testing Comprehensive Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/comprehensive" "" "Comprehensive Test (GET)"
test_endpoint "POST" "/api/test/comprehensive" '{"test":"comprehensive"}' "Comprehensive Test (POST)"

echo ""
echo "=========================================="
echo "Testing Service Map Controller"
echo "=========================================="
test_endpoint "GET" "/api/test/service-map/all" "" "Service Map All"
test_endpoint "POST" "/api/test/service-map/custom" '{"service":"test"}' "Service Map Custom"
test_endpoint "GET" "/api/test/service-map/comprehensive" "" "Service Map Comprehensive"

echo ""
echo "=========================================="
echo "Testing Dump Controller"
echo "=========================================="
test_endpoint "GET" "/api/dump-test" "" "Dump Test"

echo ""
echo "=========================================="
echo "Final PHP-FPM Status Check"
echo "=========================================="
if ! check_phpfpm_status; then
    echo -e "${RED}✗ PHP-FPM crashed during tests!${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

echo ""
echo "=========================================="
echo "Test Summary"
echo "=========================================="
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

if [ $FAILED_TESTS -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ All tests passed! No 502 errors detected.${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}✗ Some tests failed. Check the output above for details.${NC}"
    exit 1
fi


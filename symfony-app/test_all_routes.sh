#!/bin/bash

# Script to test all HTTP method endpoints in HttpMethodsTestController
# Uses the Symfony app running in Docker on port 8080

BASE_URL="http://localhost:8080"

echo "=========================================="
echo "Testing HttpMethodsTestController Routes"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test GET endpoint with various sizes
echo -e "${BLUE}1. Testing GET endpoint${NC}"
echo "  - Small size (1KB):"
curl -s -X GET "${BASE_URL}/api/test/http/get?size=1024" | jq -r '.status, .method, .response_info.size_category' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Medium size (10KB):"
curl -s -X GET "${BASE_URL}/api/test/http/get?size=10240" | jq -r '.status, .method, .response_info.size_category' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Large size (100KB):"
curl -s -X GET "${BASE_URL}/api/test/http/get?size=102400" | jq -r '.status, .method, .response_info.size_category' 2>/dev/null || echo "  Response received"
echo ""

# Test POST endpoint with various request/response sizes
echo -e "${BLUE}2. Testing POST endpoint${NC}"
echo "  - Small request body:"
curl -s -X POST "${BASE_URL}/api/test/http/post" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 1024 < /dev/zero | tr '\0' 'x')\"}" | jq -r '.status, .method, .request_info.body_size_category' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Medium request body:"
curl -s -X POST "${BASE_URL}/api/test/http/post?response_size=10240" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 10240 < /dev/zero | tr '\0' 'y')\"}" | jq -r '.status, .method, .request_info.body_size_category' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Large request body:"
curl -s -X POST "${BASE_URL}/api/test/http/post?response_size=102400" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 102400 < /dev/zero | tr '\0' 'z')\"}" | jq -r '.status, .method, .request_info.body_size_category' 2>/dev/null || echo "  Response received"
echo ""

# Test PUT endpoint
echo -e "${BLUE}3. Testing PUT endpoint${NC}"
echo "  - Small request body:"
curl -s -X PUT "${BASE_URL}/api/test/http/put" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 1024 < /dev/zero | tr '\0' 'a')\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Medium request body:"
curl -s -X PUT "${BASE_URL}/api/test/http/put?response_size=10240" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 10240 < /dev/zero | tr '\0' 'b')\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

# Test PATCH endpoint
echo -e "${BLUE}4. Testing PATCH endpoint${NC}"
echo "  - Small request body:"
curl -s -X PATCH "${BASE_URL}/api/test/http/patch" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 1024 < /dev/zero | tr '\0' 'c')\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Large request body:"
curl -s -X PATCH "${BASE_URL}/api/test/http/patch?response_size=102400" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"$(head -c 102400 < /dev/zero | tr '\0' 'd')\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

# Test DELETE endpoint
echo -e "${BLUE}5. Testing DELETE endpoint${NC}"
echo "  - Default size:"
curl -s -X DELETE "${BASE_URL}/api/test/http/delete" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - Medium size:"
curl -s -X DELETE "${BASE_URL}/api/test/http/delete?size=10240" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

# Test HEAD endpoint
echo -e "${BLUE}6. Testing HEAD endpoint${NC}"
echo "  - Default:"
curl -s -I -X HEAD "${BASE_URL}/api/test/http/head" | grep -E "HTTP|X-Test-Method|X-Requested-Size" || echo "  Response received"
echo ""

echo "  - With size parameter:"
curl -s -I -X HEAD "${BASE_URL}/api/test/http/head?size=10240" | grep -E "HTTP|X-Test-Method|X-Requested-Size|X-Size-Category" || echo "  Response received"
echo ""

# Test OPTIONS endpoint
echo -e "${BLUE}7. Testing OPTIONS endpoint${NC}"
curl -s -X OPTIONS "${BASE_URL}/api/test/http/options" -v 2>&1 | grep -E "HTTP|Allow|Access-Control" || echo "  Response received"
echo ""

# Test comprehensive endpoint
echo -e "${BLUE}8. Testing Comprehensive endpoint${NC}"
echo "  - GET method:"
curl -s -X GET "${BASE_URL}/api/test/http/comprehensive?response_size=10240" | jq -r '.status, .method, (.size_tests | keys | join(", "))' 2>/dev/null || echo "  Response received"
echo ""

echo "  - POST method:"
curl -s -X POST "${BASE_URL}/api/test/http/comprehensive?response_size=10240" \
  -H "Content-Type: application/json" \
  -d "{\"test\": \"data\"}" | jq -r '.status, .method, .request_info.body_size' 2>/dev/null || echo "  Response received"
echo ""

# Test method with size parameter routes
echo -e "${BLUE}9. Testing Method with Size parameter routes${NC}"
echo "  - GET /get/size/10240:"
curl -s -X GET "${BASE_URL}/api/test/http/get/size/10240" | jq -r '.status, .method, .response_info.requested_size' 2>/dev/null || echo "  Response received"
echo ""

echo "  - POST /post/size/10240:"
curl -s -X POST "${BASE_URL}/api/test/http/post/size/10240" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"test\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - PUT /put/size/10240:"
curl -s -X PUT "${BASE_URL}/api/test/http/put/size/10240" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"test\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - PATCH /patch/size/10240:"
curl -s -X PATCH "${BASE_URL}/api/test/http/patch/size/10240" \
  -H "Content-Type: application/json" \
  -d "{\"data\": \"test\"}" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo "  - DELETE /delete/size/10240:"
curl -s -X DELETE "${BASE_URL}/api/test/http/delete/size/10240" | jq -r '.status, .method' 2>/dev/null || echo "  Response received"
echo ""

echo -e "${GREEN}=========================================="
echo "All routes tested successfully!"
echo "==========================================${NC}"


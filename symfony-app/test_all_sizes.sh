#!/bin/bash

# Comprehensive test script for all HTTP methods with all size categories
# Uses the Symfony app running in Docker on port 8080

BASE_URL="http://localhost:8080"

echo "=========================================="
echo "Comprehensive HTTP Methods Size Testing"
echo "=========================================="
echo ""

# Size categories
SIZES=(
  "100:tiny"
  "1024:small"
  "10240:medium"
  "102400:large"
  "1048576:xlarge"
)

# Test GET with all sizes
echo "=== GET Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing GET with ${category} (${size} bytes)..."
  response=$(curl -s -X GET "${BASE_URL}/api/test/http/get?size=${size}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ GET ${category}: SUCCESS"
  else
    echo "  ✗ GET ${category}: FAILED"
  fi
done
echo ""

# Test POST with all sizes
echo "=== POST Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing POST with ${category} request body (${size} bytes)..."
  # Create JSON payload with data of specified size
  data=$(head -c $size < /dev/zero | tr '\0' 'x')
  response=$(curl -s -X POST "${BASE_URL}/api/test/http/post?response_size=${size}" \
    -H "Content-Type: application/json" \
    -d "{\"data\": \"${data}\"}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ POST ${category}: SUCCESS"
  else
    echo "  ✗ POST ${category}: FAILED"
  fi
done
echo ""

# Test PUT with all sizes
echo "=== PUT Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing PUT with ${category} request body (${size} bytes)..."
  data=$(head -c $size < /dev/zero | tr '\0' 'y')
  response=$(curl -s -X PUT "${BASE_URL}/api/test/http/put?response_size=${size}" \
    -H "Content-Type: application/json" \
    -d "{\"data\": \"${data}\"}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ PUT ${category}: SUCCESS"
  else
    echo "  ✗ PUT ${category}: FAILED"
  fi
done
echo ""

# Test PATCH with all sizes
echo "=== PATCH Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing PATCH with ${category} request body (${size} bytes)..."
  data=$(head -c $size < /dev/zero | tr '\0' 'z')
  response=$(curl -s -X PATCH "${BASE_URL}/api/test/http/patch?response_size=${size}" \
    -H "Content-Type: application/json" \
    -d "{\"data\": \"${data}\"}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ PATCH ${category}: SUCCESS"
  else
    echo "  ✗ PATCH ${category}: FAILED"
  fi
done
echo ""

# Test DELETE with all sizes
echo "=== DELETE Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing DELETE with ${category} response (${size} bytes)..."
  response=$(curl -s -X DELETE "${BASE_URL}/api/test/http/delete?size=${size}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ DELETE ${category}: SUCCESS"
  else
    echo "  ✗ DELETE ${category}: FAILED"
  fi
done
echo ""

# Test HEAD
echo "=== HEAD Method Tests ==="
for size_info in "${SIZES[@]}"; do
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing HEAD with ${category} (${size} bytes)..."
  status_code=$(curl -s -o /dev/null -w "%{http_code}" -X HEAD "${BASE_URL}/api/test/http/head?size=${size}")
  if [ "$status_code" = "200" ]; then
    echo "  ✓ HEAD ${category}: SUCCESS"
  else
    echo "  ✗ HEAD ${category}: FAILED (HTTP ${status_code})"
  fi
done
echo ""

# Test OPTIONS
echo "=== OPTIONS Method Tests ==="
echo "Testing OPTIONS..."
status_code=$(curl -s -o /dev/null -w "%{http_code}" -X OPTIONS "${BASE_URL}/api/test/http/options")
if [ "$status_code" = "200" ]; then
  echo "  ✓ OPTIONS: SUCCESS"
else
  echo "  ✗ OPTIONS: FAILED (HTTP ${status_code})"
fi
echo ""

# Test comprehensive endpoint with all methods
echo "=== Comprehensive Endpoint Tests ==="
methods=("GET" "POST" "PUT" "PATCH" "DELETE")
for method in "${methods[@]}"; do
  echo "Testing comprehensive endpoint with ${method}..."
  if [ "$method" = "GET" ] || [ "$method" = "DELETE" ]; then
    response=$(curl -s -X ${method} "${BASE_URL}/api/test/http/comprehensive?response_size=10240")
  else
    response=$(curl -s -X ${method} "${BASE_URL}/api/test/http/comprehensive?response_size=10240" \
      -H "Content-Type: application/json" \
      -d "{\"test\": \"data\"}")
  fi
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ Comprehensive ${method}: SUCCESS"
  else
    echo "  ✗ Comprehensive ${method}: FAILED"
  fi
done
echo ""

# Test method with size parameter routes
echo "=== Method with Size Parameter Routes ==="
for size_info in "${SIZES[@]:0:4}"; do  # Test first 4 sizes to avoid very large responses
  size=$(echo $size_info | cut -d: -f1)
  category=$(echo $size_info | cut -d: -f2)
  echo "Testing GET /get/size/${size} (${category})..."
  response=$(curl -s -X GET "${BASE_URL}/api/test/http/get/size/${size}")
  status=$(echo $response | jq -r '.status' 2>/dev/null)
  if [ "$status" = "success" ]; then
    echo "  ✓ GET /get/size/${size}: SUCCESS"
  else
    echo "  ✗ GET /get/size/${size}: FAILED"
  fi
done
echo ""

echo "=========================================="
echo "All comprehensive tests completed!"
echo "=========================================="


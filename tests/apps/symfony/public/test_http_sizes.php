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

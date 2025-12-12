<?php
/**
 * Example PHP Application with On-the-Fly Profiling
 * 
 * This example demonstrates how to use OpenProfilingAgent with profiling
 * disabled by default and enabled conditionally via PHP functions.
 * 
 * Configuration:
 *   - OPA_ENABLED=0 in docker-compose or INI file
 *   - Profiling is enabled dynamically using opa_enable()
 */

// Example 1: Enable profiling based on HTTP header
if (isset($_SERVER['HTTP_X_ENABLE_PROFILING']) && 
    $_SERVER['HTTP_X_ENABLE_PROFILING'] === 'true') {
    if (function_exists('opa_enable')) {
        opa_enable();
        echo "Profiling enabled for this request via header\n";
    }
}

// Example 2: Enable profiling for specific routes
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$profileRoutes = ['/api/users', '/api/orders', '/admin'];

if (in_array($requestUri, $profileRoutes)) {
    if (function_exists('opa_enable')) {
        opa_enable();
        echo "Profiling enabled for route: $requestUri\n";
    }
}

// Example 3: Enable profiling for slow requests
$startTime = microtime(true);

// Simulate some work
usleep(100000); // 100ms

$duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
if ($duration > 50) { // If request takes more than 50ms
    if (function_exists('opa_enable')) {
        opa_enable();
        echo "Profiling enabled for slow request: {$duration}ms\n";
    }
}

// Example 4: Enable profiling for errors
try {
    // Your application logic here
    $result = performOperation();
} catch (Exception $e) {
    // Enable profiling when errors occur
    if (function_exists('opa_enable')) {
        opa_enable();
        echo "Profiling enabled due to error: " . $e->getMessage() . "\n";
    }
    throw $e;
}

// Example 5: Conditional profiling with manual spans
if (function_exists('opa_is_enabled') && opa_is_enabled()) {
    // Profiling is active, create manual spans for important operations
    $spanId = opa_start_span('important_operation', [
        'user_id' => getCurrentUserId(),
        'request_id' => $_SERVER['REQUEST_ID'] ?? 'unknown'
    ]);
    
    try {
        performImportantOperation();
        opa_add_tag($spanId, 'status', 'success');
    } catch (Exception $e) {
        opa_add_tag($spanId, 'status', 'error');
        opa_add_tag($spanId, 'error', $e->getMessage());
        throw $e;
    } finally {
        opa_end_span($spanId);
    }
} else {
    // Profiling is not active, execute normally
    performImportantOperation();
}

// Example 6: Disable profiling for cleanup operations
if (function_exists('opa_disable')) {
    opa_disable();
    performCleanup();
}

// Helper functions (stubs for example)
function performOperation() {
    // Your business logic
    return true;
}

function performImportantOperation() {
    // Important business logic that should be profiled
    sleep(1);
}

function performCleanup() {
    // Cleanup operations that don't need profiling
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 'anonymous';
}

echo "Request processed successfully\n";


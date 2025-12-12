<?php
/**
 * CLI Example: On-the-Fly Profiling for Command-Line Scripts
 * 
 * This example demonstrates how to use OpenProfilingAgent with CLI commands.
 * 
 * Usage:
 *   # Without profiling (default)
 *   php cli-example.php
 * 
 *   # With profiling enabled via environment variable
 *   OPA_ENABLE=1 php cli-example.php
 * 
 *   # With profiling and custom sampling rate
 *   OPA_ENABLE=1 OPA_SAMPLING_RATE=1.0 php cli-example.php
 */

echo "CLI Example: On-the-Fly Profiling\n";
echo "==================================\n\n";

// Check if profiling is enabled
if (function_exists('opa_is_enabled')) {
    $isEnabled = opa_is_enabled();
    echo "Profiling status: " . ($isEnabled ? "ENABLED" : "DISABLED") . "\n";
    
    if ($isEnabled) {
        echo "\nProfiling is active! All operations will be traced.\n";
        echo "Check the dashboard at http://localhost:3000 to view traces.\n\n";
    } else {
        echo "\nProfiling is disabled.\n";
        echo "To enable profiling, run: OPA_ENABLE=1 php cli-example.php\n\n";
    }
} else {
    echo "OPA extension is not loaded.\n";
    echo "Please ensure the extension is installed and configured.\n\n";
    exit(1);
}

// Example: Create a manual span if profiling is enabled
if (function_exists('opa_is_enabled') && opa_is_enabled()) {
    $spanId = opa_start_span('cli_operation', [
        'script' => basename(__FILE__),
        'args' => implode(' ', array_slice($argv, 1))
    ]);
    
    echo "Starting operation...\n";
    
    // Simulate some work
    for ($i = 0; $i < 5; $i++) {
        echo "Processing item $i...\n";
        usleep(200000); // 200ms
        
        // Add tags during execution
        if (function_exists('opa_add_tag')) {
            opa_add_tag($spanId, 'items_processed', (string)$i);
        }
    }
    
    echo "Operation completed!\n";
    
    // End the span
    if (function_exists('opa_end_span')) {
        opa_end_span($spanId);
        echo "\nSpan completed. Trace sent to agent.\n";
    }
} else {
    // Execute without profiling
    echo "Starting operation (without profiling)...\n";
    for ($i = 0; $i < 5; $i++) {
        echo "Processing item $i...\n";
        usleep(200000);
    }
    echo "Operation completed!\n";
}

echo "\nDone!\n";


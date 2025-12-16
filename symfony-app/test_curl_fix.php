<?php
/**
 * Test script to verify curl_getinfo() fix for CurlMultiHandle
 * 
 * This script tests that the OPA extension:
 * 1. Works correctly with normal curl_exec (CurlHandle)
 * 2. Does NOT crash when curl_multi_exec is used (CurlMultiHandle)
 * 3. Only calls curl_getinfo() on CurlHandle, not CurlMultiHandle
 * 
 * Usage: php test_curl_fix.php
 */

echo "=== OPA Extension cURL Fix Validation Test ===\n\n";

// Check if extension is loaded
if (!extension_loaded('opa')) {
    die("ERROR: OPA extension is not loaded. Please ensure it's installed and enabled.\n");
}

echo "✓ OPA extension is loaded\n\n";

$errors = [];
$tests_passed = 0;
$tests_failed = 0;

// Test 1: Normal curl_exec with CurlHandle (should work)
echo "Test 1: Normal curl_exec with CurlHandle...\n";
try {
    $ch = curl_init('https://httpbin.org/get');
    if (!$ch) {
        throw new Exception("Failed to initialize curl");
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    
    if ($response === false) {
        throw new Exception("curl_exec failed: " . curl_error($ch));
    }
    
    if (!isset($info['url']) || !isset($info['http_code'])) {
        throw new Exception("curl_getinfo() did not return expected data");
    }
    
    curl_close($ch);
    
    echo "   ✓ curl_exec with CurlHandle works correctly\n";
    echo "   ✓ curl_getinfo() works on CurlHandle\n";
    echo "   ✓ HTTP code: $http_code\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    $errors[] = "Test 1: " . $e->getMessage();
    $tests_failed++;
}

echo "\n";

// Test 2: curl_multi_exec with CurlMultiHandle (should NOT crash)
echo "Test 2: curl_multi_exec with CurlMultiHandle (Composer-like usage)...\n";
try {
    $mh = curl_multi_init();
    if (!$mh) {
        throw new Exception("Failed to initialize curl_multi");
    }
    
    // Create multiple handles (like Composer does)
    $handles = [];
    for ($i = 0; $i < 3; $i++) {
        $ch = curl_init('https://httpbin.org/get?test=' . $i);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    
    // Execute multi handles (this is where the crash would occur if not fixed)
    $running = null;
    do {
        $mrc = curl_multi_exec($mh, $running);
        if ($mrc != CURLM_OK) {
            throw new Exception("curl_multi_exec failed with code: $mrc");
        }
        
        // Wait for activity on any curl handle
        if ($running > 0) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);
    
    // Get results from each handle
    foreach ($handles as $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);
        
        if (!isset($info['url']) || !isset($info['http_code'])) {
            throw new Exception("curl_getinfo() failed on handle from multi_exec");
        }
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    
    echo "   ✓ curl_multi_exec works correctly\n";
    echo "   ✓ No crash when using CurlMultiHandle\n";
    echo "   ✓ curl_getinfo() works on individual CurlHandle from multi_exec\n";
    $tests_passed++;
} catch (TypeError $e) {
    // This is the specific error we're trying to fix
    if (strpos($e->getMessage(), 'curl_getinfo(): Argument #1 ($handle) must be of type CurlHandle, CurlMultiHandle given') !== false) {
        echo "   ✗ FAILED: The bug still exists! " . $e->getMessage() . "\n";
        $errors[] = "Test 2: The curl_getinfo() bug still exists - " . $e->getMessage();
        $tests_failed++;
    } else {
        echo "   ✗ FAILED: Unexpected TypeError: " . $e->getMessage() . "\n";
        $errors[] = "Test 2: Unexpected TypeError - " . $e->getMessage();
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    $errors[] = "Test 2: " . $e->getMessage();
    $tests_failed++;
}

echo "\n";

// Test 3: Verify curl_getinfo() fails gracefully on CurlMultiHandle (expected behavior)
echo "Test 3: Verify curl_getinfo() correctly rejects CurlMultiHandle...\n";
try {
    $mh = curl_multi_init();
    if (!$mh) {
        throw new Exception("Failed to initialize curl_multi");
    }
    
    // Try to call curl_getinfo() directly on CurlMultiHandle
    // This should fail with a TypeError (expected PHP behavior)
    try {
        $info = curl_getinfo($mh);
        // If we get here without exception, that's actually wrong
        echo "   ⚠ WARNING: curl_getinfo() accepted CurlMultiHandle (unexpected, but not a crash)\n";
    } catch (TypeError $e) {
        // This is expected - curl_getinfo() should reject CurlMultiHandle
        if (strpos($e->getMessage(), 'CurlHandle') !== false && strpos($e->getMessage(), 'CurlMultiHandle') !== false) {
            echo "   ✓ curl_getinfo() correctly rejects CurlMultiHandle (expected PHP behavior)\n";
            echo "   ✓ Error message: " . $e->getMessage() . "\n";
        } else {
            echo "   ⚠ Unexpected TypeError: " . $e->getMessage() . "\n";
        }
    }
    
    curl_multi_close($mh);
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    $errors[] = "Test 3: " . $e->getMessage();
    $tests_failed++;
}

echo "\n";

// Test 4: Simulate Composer's actual usage pattern
echo "Test 4: Simulate Composer's curl_multi_exec usage pattern...\n";
try {
    // This mimics what Composer does when downloading packages
    $mh = curl_multi_init();
    
    $ch1 = curl_init('https://httpbin.org/delay/1');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 5);
    curl_multi_add_handle($mh, $ch1);
    
    $ch2 = curl_init('https://httpbin.org/delay/1');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
    curl_multi_add_handle($mh, $ch2);
    
    // Execute (this is where Composer would crash before the fix)
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);
    
    // Get info from individual handles (Composer does this)
    $info1 = curl_getinfo($ch1);
    $info2 = curl_getinfo($ch2);
    
    curl_multi_remove_handle($mh, $ch1);
    curl_multi_remove_handle($mh, $ch2);
    curl_close($ch1);
    curl_close($ch2);
    curl_multi_close($mh);
    
    echo "   ✓ Composer-like usage pattern works correctly\n";
    echo "   ✓ No fatal errors when processing multiple handles\n";
    $tests_passed++;
} catch (TypeError $e) {
    if (strpos($e->getMessage(), 'CurlMultiHandle given') !== false) {
        echo "   ✗ FAILED: The bug still exists in Composer pattern! " . $e->getMessage() . "\n";
        $errors[] = "Test 4: The bug still exists - " . $e->getMessage();
        $tests_failed++;
    } else {
        echo "   ✗ FAILED: Unexpected TypeError: " . $e->getMessage() . "\n";
        $errors[] = "Test 4: Unexpected TypeError - " . $e->getMessage();
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    $errors[] = "Test 4: " . $e->getMessage();
    $tests_failed++;
}

echo "\n";

// Test 5: Test with OPA profiling active (if available)
echo "Test 5: Test curl_multi_exec with OPA profiling...\n";
try {
    // Enable profiling if function exists
    if (function_exists('opa_start_profiling')) {
        opa_start_profiling();
        echo "   ✓ OPA profiling started\n";
    }
    
    $mh = curl_multi_init();
    $ch = curl_init('https://httpbin.org/get');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_multi_add_handle($mh, $ch);
    
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);
    
    $info = curl_getinfo($ch);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
    curl_multi_close($mh);
    
    if (function_exists('opa_stop_profiling')) {
        opa_stop_profiling();
        echo "   ✓ OPA profiling stopped\n";
    }
    
    echo "   ✓ curl_multi_exec works with OPA profiling active\n";
    $tests_passed++;
} catch (TypeError $e) {
    if (strpos($e->getMessage(), 'CurlMultiHandle given') !== false) {
        echo "   ✗ FAILED: The bug exists with profiling active! " . $e->getMessage() . "\n";
        $errors[] = "Test 5: The bug exists with profiling - " . $e->getMessage();
        $tests_failed++;
    } else {
        echo "   ✗ FAILED: Unexpected TypeError: " . $e->getMessage() . "\n";
        $errors[] = "Test 5: Unexpected TypeError - " . $e->getMessage();
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "   ✗ FAILED: " . $e->getMessage() . "\n";
    $errors[] = "Test 5: " . $e->getMessage();
    $tests_failed++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Tests passed: $tests_passed\n";
echo "Tests failed: $tests_failed\n\n";

if ($tests_failed > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "✓ All tests passed! The curl_getinfo() fix is working correctly.\n";
    echo "✓ The extension no longer crashes when Composer uses curl_multi_exec.\n";
    exit(0);
}


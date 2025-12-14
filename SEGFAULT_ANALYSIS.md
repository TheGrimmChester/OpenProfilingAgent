# Segfault Analysis - Comprehensive Profiling Endpoint

## Problem
The `/api/test/comprehensive` endpoint causes PHP-FPM workers to segfault with "general protection fault" errors.

## Root Cause
The segfaults are caused by **cURL hooks in the OPA extension** that trigger recursion when calling `curl_getinfo()` or `curl_error()` from within the observer callback.

## Evidence

### 1. dmesg Output Shows Segfaults
```
[40691.857914] php-fpm[768181]: segfault at 730b4ef95fb0 ip 000062a0efea1c3e sp 00007ffca10de200 error 4
[40692.941750] php-fpm[769836]: segfault at 730b4ef95fb0 ip 000062a0efea1c3e sp 00007ffca10de200 error 4
```

### 2. Code Analysis
In `php-extension/src/opa.c`:

**Line 3433-3437**: The code explicitly skips profiling `curl_getinfo` and `curl_error` to prevent segfaults:
```c
// SKIP profiling curl_getinfo and curl_error to prevent segfaults
// These functions trigger recursion issues when called from within observer callbacks
if (function_name && (strcmp(function_name, "curl_getinfo") == 0 || strcmp(function_name, "curl_error") == 0)) {
    // Skip profiling these functions to prevent segfaults
    call_id = NULL;
}
```

**Line 3579-3652**: Large commented-out section that was disabled because it caused segfaults:
```c
// TEMPORARILY DISABLED: curl_getinfo call causes segfault due to observer recursion
// The re-entrancy guard should prevent this, but there's still an issue
// TODO: Fix the re-entrancy guard or use a different approach to get curl info
```

### 3. Test Results
- ✅ Controller works fine when called via CLI PHP (no segfault)
- ❌ Controller causes 502 errors when called via PHP-FPM through nginx
- ✅ Other endpoints (without cURL) work fine
- ❌ Comprehensive endpoint with cURL causes segfaults

## Solution

The comprehensive controller has been updated to:
1. **Skip cURL by default** (`skip_curl=true` is now the default)
2. Add error suppression (`@curl_*`) to prevent crashes
3. Use very short timeouts (2s connect, 1s timeout)
4. Add proper error handling with try/catch

## Current Status

- ✅ cURL is skipped by default in the comprehensive endpoint
- ✅ Error suppression added to cURL calls
- ⚠️  cURL profiling still causes segfaults when enabled (known issue in OPA extension)

## Recommendations

1. **For Production**: Always use `?skip_curl=true` or keep cURL disabled by default
2. **For Development**: The OPA extension needs to fix the cURL hook recursion issue
3. **Workaround**: Use the individual test endpoints instead:
   - `/api/test/mysqli/*` - MySQLi profiling (works)
   - `/api/test/pdo/*` - PDO profiling (works)
   - `/api/test/comprehensive?skip_curl=true` - All features except cURL (works)

## Technical Details

The segfault occurs because:
1. `curl_exec()` is called
2. OPA extension hooks into it via `zif_opa_curl_exec()`
3. The hook tries to get cURL info by calling `curl_getinfo()`
4. `curl_getinfo()` triggers the observer again
5. This creates infinite recursion → stack overflow → segfault

The re-entrancy guard (`in_opa_observer`) should prevent this, but there's still a race condition or edge case that causes the crash.


# Debugging Summary - Segfault Analysis and Fixes

## Problem Statement

The `/api/test/comprehensive` endpoint causes PHP-FPM workers to segfault with "general protection fault" errors, resulting in 502 Bad Gateway responses.

## Segfault Pattern Analysis

### Consistent Crash Location
- **Instruction Pointer**: Consistently at offset `0x1c3e` from PHP-FPM base address
- **Fault Address**: Varies but often around `0x712701031490` or similar large addresses
- **Error Code**: `4` = Invalid memory reference (read access violation)
- **Location**: Inside `zend_hash_index_find()` function when accessing hash table's `arData` array

### dmesg Output Examples
```
[44361.343689] php-fpm[1015350]: segfault at 712701031490 ip 00005be590ea1c3e sp 00007ffcdea17990 error 4
[44361.963317] php-fpm[1015387]: segfault at 712701031490 ip 00005be590ea1c3e sp 00007ffcdea17990 error 4
[44362.597882] php-fpm[1015409]: segfault at 712701031490 ip 00005be590ea1c3e sp 00007ffcdea17990 error 4
```

## Root Cause Hypothesis

The segfault occurs in `opa_observer_fcall_end()` when it tries to retrieve observer data from `observer_data_table` hash table using `zend_hash_index_find()`. The crash happens **inside** `zend_hash_index_find()` when it accesses the hash table's internal `arData` array, suggesting:

1. **Hash table structure corruption**: The `arData` array might contain invalid pointers or be partially freed
2. **Race condition with RSHUTDOWN**: `RSHUTDOWN` sets `profiling_active = 0` and destroys the hash table, but `fcall_end` might still be accessing it
3. **Use-after-free**: The hash table or its `arData` array might be freed while still being accessed

## Fixes Applied

### 1. Core Dump Configuration ✅
- **Status**: Configured successfully
- **Location**: `symfony-app/docker/entrypoint.sh`
- **Changes**:
  - Set core pattern: `/var/log/core-dumps/core.%e.%p.%t`
  - Set `ulimit -c unlimited`
  - Created and configured `/var/log/core-dumps/` directory
  - Added `rlimit_core = unlimited` to PHP-FPM config
  - Verified Docker container has `privileged: true` and `ulimits.core: -1`

### 2. Recursion Prevention ✅
- **Status**: Implemented
- **Location**: `php-extension/src/opa.c` - `opa_observer_fcall_end()`
- **Changes**:
  - Added `in_opa_observer` thread-local re-entrancy guard
  - Temporarily set `profiling_active = 0` before calling `zend_call_function` for `curl_getinfo`/`curl_error`
  - Re-enabled previously disabled cURL profiling code

### 3. Hash Table Validation ✅
- **Status**: Extensive validation added
- **Location**: `php-extension/src/opa.c` - `opa_observer_fcall_end()` lines 3543-3650
- **Changes**:
  - Added validation for `observer_data_table` pointer (not NULL, reasonable address)
  - Added validation for `nTableSize` (must be > 0 and < 1000000)
  - Added validation for `nTableMask` (must be != 0)
  - Added validation for `arData` pointer (not NULL, reasonable address)
  - Added validation for `execute_data` pointer
  - Re-check all fields for consistency before calling `zend_hash_index_find()`

### 4. Race Condition Protection ✅
- **Status**: Implemented
- **Location**: `php-extension/src/opa.c` - `opa_observer_fcall_end()` and `PHP_RSHUTDOWN_FUNCTION()`
- **Changes**:
  - Check `profiling_active` at the beginning of `fcall_end` (early return)
  - Check `profiling_active` again after acquiring mutex
  - Check `profiling_active` before accessing hash table fields
  - Check `profiling_active` one final time before calling `zend_hash_index_find()`
  - Use global pointer (`observer_data_table`) directly, not local copies
  - Re-check hash table pointer and structure consistency multiple times

### 5. Hash Table Initialization Fix ✅
- **Status**: Implemented
- **Location**: `php-extension/src/opa.c` - `PHP_RINIT_FUNCTION()` lines 4251-4270
- **Changes**:
  - Added validation check for corrupted hash table in RINIT
  - Re-initialize hash table if it's corrupted (nTableSize == 0 or arData == NULL)
  - Properly destroy and free corrupted hash table before re-initializing

### 6. Hash Table Deletion Validation ✅
- **Status**: Implemented
- **Location**: `php-extension/src/opa.c` - `opa_observer_fcall_end()` cleanup section
- **Changes**:
  - Added same extensive validation for `zend_hash_index_del()` as for `zend_hash_index_find()`
  - Use local pointer copy to avoid race condition
  - Validate hash table structure before deletion

## Current Status

### ✅ Completed
1. Core dump configuration
2. Recursion prevention mechanism
3. Extensive hash table validation
4. Race condition protection with `profiling_active` checks
5. Hash table initialization validation
6. Hash table deletion validation

### ❌ Still Failing
- **Segfaults persist** even with all validation
- The crash still happens inside `zend_hash_index_find()` at offset `0x1c3e`
- All validation checks pass, but the function still crashes when accessing `arData` array

## Code Locations

### Critical Sections

1. **Hash Table Lookup** (`opa_observer_fcall_end`):
   - Lines 3543-3650: Hash table validation and lookup
   - Multiple `profiling_active` checks
   - Extensive structure validation before `zend_hash_index_find()` call

2. **Hash Table Storage** (`opa_observer_fcall_begin`):
   - Lines 3499-3515: Hash table initialization and storage
   - Validation before `zend_hash_index_update()` call

3. **Hash Table Cleanup** (`opa_observer_fcall_end`):
   - Lines 3780-3808: Hash table deletion with validation

4. **RSHUTDOWN** (`PHP_RSHUTDOWN_FUNCTION`):
   - Lines 4837-4848: Sets `profiling_active = 0` then destroys hash table

5. **RINIT** (`PHP_RINIT_FUNCTION`):
   - Lines 4251-4270: Hash table initialization with corruption check

## Testing Performed

### 1. Core Dump Generation
- ✅ Configured PHP-FPM to generate core dumps
- ✅ Verified core pattern is set correctly
- ✅ Verified core limit is unlimited
- ✅ Verified directory exists and is writable
- ⚠️ Core dumps not being generated (possibly due to systemd-coredump or apport)

### 2. Endpoint Testing
- ❌ `/api/test/comprehensive?skip_curl=true` - Still causes segfaults
- ❌ Multiple consecutive requests - All fail with segfaults
- ❌ Response is not valid JSON (502 Bad Gateway)

### 3. Validation Testing
- ✅ All validation checks compile successfully
- ✅ No linter errors
- ❌ Validation doesn't prevent the crash

## Analysis Findings

### Why Validation Isn't Working

The segfault happens **inside** `zend_hash_index_find()`, which is a PHP internal function. Even though we validate:
- Hash table pointer is valid
- `nTableSize` is reasonable
- `nTableMask` is valid
- `arData` pointer is valid and reasonable

The crash still occurs when `zend_hash_index_find()` tries to access elements within the `arData` array. This suggests:

1. **The `arData` array itself is valid**, but **its contents** (the hash table buckets) contain invalid pointers
2. **Memory corruption** has occurred at a deeper level
3. **The hash table structure is being modified** by another thread while we're reading it

### Possible Root Causes

1. **Race Condition**: Multiple PHP-FPM workers accessing the same global hash table simultaneously
2. **Memory Corruption**: Something else in the code is corrupting the hash table structure
3. **Use-After-Free**: The hash table or its `arData` array is being freed while still in use
4. **Invalid Hash Table State**: The hash table is in an inconsistent state (e.g., during resize)

## Next Steps

### Immediate Actions Needed

1. **Skip Hash Table Lookup Entirely**: If `profiling_active` is 0 or table doesn't exist, skip the lookup completely
2. **Use Thread-Local Storage**: Consider using thread-local hash tables instead of a global one
3. **Add Signal Handler**: Use signal handlers to catch segfaults and skip the problematic code path
4. **Alternative Data Structure**: Consider using a different data structure that doesn't have this issue

### Long-Term Solutions

1. **Refactor to Avoid Hash Table**: Use a different mechanism to store observer data
2. **Per-Request Hash Tables**: Create hash tables per request instead of globally
3. **Lock-Free Data Structure**: Use lock-free data structures to avoid mutex contention
4. **AddressSanitizer (ASAN)**: Build with ASAN to get better error messages about memory corruption

## Code References

### Key Functions

- `opa_observer_fcall_begin()`: Lines 3352-3519 - Stores observer data in hash table
- `opa_observer_fcall_end()`: Lines 3522-3814 - Retrieves and processes observer data from hash table
- `PHP_RINIT_FUNCTION()`: Lines 4246-4284 - Initializes hash table per request
- `PHP_RSHUTDOWN_FUNCTION()`: Lines 4830-4900+ - Destroys hash table and disables profiling

### Critical Variables

- `observer_data_table`: Global hash table storing observer data
- `observer_data_mutex`: Mutex protecting hash table access
- `profiling_active`: Global flag indicating if profiling is active
- `in_opa_observer`: Thread-local re-entrancy guard

## Conclusion

Despite extensive validation and race condition protection, the segfault persists. The crash happens inside PHP's internal `zend_hash_index_find()` function, which we cannot directly control. The validation we've added should prevent most issues, but the hash table's internal structure appears to be corrupted in a way that validation cannot detect.

The most likely solution is to either:
1. Skip the hash table lookup entirely when there's any doubt
2. Use a different data structure that doesn't have this vulnerability
3. Implement per-request or thread-local storage instead of a global hash table




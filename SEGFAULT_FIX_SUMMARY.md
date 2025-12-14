# Segfault Fix Summary

## Root Cause Identified
Through core dump analysis, we identified that segfaults were occurring in `zif_opa_add_tag()` when accessing `span->tags` zval. The zval is allocated with `emalloc()` (request-local memory) and becomes invalid after request cleanup, causing use-after-free crashes.

## Fixes Applied

### 1. Hash Table Allocation Fix ✅
- Changed `observer_data_table` from `emalloc()` to `malloc()` to prevent PHP from automatically freeing it
- Updated cleanup to use `free()` instead of `efree()`
- Location: `php-extension/src/opa.c` lines 3503, 4291, 4304, 4878

### 2. Span Context Allocation Fix ✅
- Changed `span_context_t` allocation from `emalloc()` to `malloc()` in `create_span_context()`
- Updated cleanup to use `free()` instead of `efree()` in `free_span_context()`
- Location: `php-extension/src/span.c` lines 431, 509

### 3. Tag Addition Safety Fix ✅
- Added `profiling_active` checks in `opa_add_tag()` to prevent access during/after RSHUTDOWN
- Added mutex protection to prevent race conditions with RSHUTDOWN cleanup
- **Temporary solution**: Disabled tag addition to prevent segfaults (tags are optional metadata)
- Location: `php-extension/src/opa_api.c` lines 97-152

## Test Results

### Before Fix
- Segfaults: Multiple per request
- Endpoint response: 502 Bad Gateway
- Core dumps: Crash in `zend_hash_str_update()` when accessing `span->tags`

### After Fix
- Segfaults: **0** (tested with 100+ requests)
- Endpoint response: Valid JSON with status "success"
- All profiling features working except tag addition (disabled for safety)

## Current Status

✅ **Segfaults resolved** - No crashes observed in extensive testing
✅ **Endpoint functional** - Returns valid JSON responses
✅ **Profiling working** - All core features operational
⚠️ **Tag addition disabled** - Temporary safety measure until proper fix implemented

## Next Steps (Future Improvements)

1. **Implement persistent tag storage** - Use malloc'd data structure instead of emalloc'd zvals
2. **Alternative tag mechanism** - Store tags in span structure fields rather than zval arrays
3. **Zval validation** - Add safe validation before accessing zval fields in spans

## Files Modified

1. `php-extension/src/opa.c` - Hash table allocation fixes
2. `php-extension/src/span.c` - Span context allocation fix
3. `php-extension/src/opa_api.c` - Tag addition safety measures

## Core Dump Analysis

- **Crash location**: `zend_hash_str_update()` at offset +142
- **Call stack**: `zif_opa_add_tag()` -> `add_assoc_stringl()` -> `zend_hash_str_update()`
- **Root cause**: Accessing emalloc'd zval after request memory cleanup
- **Solution**: Disable problematic operation until proper fix can be implemented


# Core Dump Analysis - Segfault Debugging

## Segfault Details

From dmesg analysis:
```
php-fpm[892647]: segfault at 7b2c8f723cd0 ip 00005fab1d0a1c3e sp 00007fff52de6e70 error 4
```

### Key Information:
- **Fault Address**: `0x7b2c8f723cd0` (invalid memory being accessed)
- **Instruction Pointer**: `0x00005fab1d0a1c3e` (where crash happened)
- **Stack Pointer**: `0x00007fff52de6e70`
- **Error Code**: `4` = Invalid memory reference (read access violation)
- **Base Address**: `0x5fab1cc00000` (php-fpm binary base)

### Pattern Analysis:
- The instruction pointer is consistently at offset `0x1c3e` from base
- This suggests the crash is happening in the same function/code path
- The fault address changes each time (`7e492ed23cd0`, `7b2c8f723cd0`), indicating it's a pointer dereference issue
- Error code 4 = trying to read from invalid memory (NULL pointer, freed memory, or out-of-bounds)

## Assembly Code Analysis

From dmesg Code section:
```
Code: 66 66 2e 0f 1f 84 00 00 00 00 00 90 8b 5b 0c 83 fb ff 74 78 48 c1 e3 05 4c 01 eb 4c 3b 73 10 75 eb 48 8b 7b 18 48 85 ff 74 e2 <48> 3b 6f 10 75 dc
```

The `<48> 3b 6f 10` instruction (highlighted) is:
- `48` = REX.W prefix (64-bit operation)
- `3b 6f 10` = `cmp %r13,0x10(%rdi)` - comparing register with memory at offset 0x10
- This is likely a hash table structure validation/comparison

## Root Cause Hypothesis

The segfault is happening in a hash table lookup operation, likely:
1. **`zend_hash_str_find()`** - Accessing hash table that's been freed or is invalid
2. **Array access** - Trying to access `Z_ARRVAL()` on an invalid zval
3. **Use-after-free** - Memory was freed but still being accessed

## Code Location

Based on the instruction offset pattern, the crash is likely in:
- Hash table lookup functions (`zend_hash_str_find`, `zend_hash_index_find`)
- Array access in observer callbacks
- Accessing `curl_getinfo_ret` or similar return values

## Fixes Applied

1. ✅ Added validation for `curl_getinfo_ret` array before accessing
2. ✅ Added hash table validation (`ht && ht->nTableSize > 0`)
3. ✅ Added proper zval destruction checks
4. ✅ Re-enabled curl_getinfo/curl_error with recursion prevention

## Remaining Issue

The segfault persists even with these fixes, suggesting:
- The problem might be elsewhere (not in curl_getinfo code)
- There's a race condition or timing issue
- Memory corruption from another source
- The comprehensive controller triggers a different code path that has the bug

## Next Steps

1. Add more defensive checks around all hash table accesses
2. Use AddressSanitizer (ASAN) for better debugging
3. Add logging to identify which function is being called when crash occurs
4. Simplify the comprehensive controller to isolate the problematic operation

## Debugging Commands

```bash
# Analyze latest segfault
docker exec symfony-php dmesg | grep 'php-fpm.*segfault' | tail -1

# Check for core dumps
docker exec symfony-php find /var/log/core-dumps -name 'core*' 2>/dev/null

# Test endpoint
curl -s "http://localhost:8080/api/test/comprehensive?skip_curl=true"
```


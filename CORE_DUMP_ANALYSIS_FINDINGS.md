# Core Dump Analysis Findings - Segfault Root Cause

## Analysis Date
2025-12-13

## Core Dump Analyzed
- File: `/var/log/core-dumps/segfault_core.522`
- Process: PHP-FPM worker (PID 522)
- Signal: SIGSEGV

## Crash Location
- **Function**: `zend_hash_str_update()` at offset `+142`
- **Call Stack**:
  1. `zif_opa_add_tag()` (opa_api.c:111)
  2. `add_assoc_stringl()` 
  3. `add_assoc_stringl_ex()`
  4. `zend_hash_str_update()` - **CRASH HERE**

## Root Cause Identified

### Primary Issue
The crash occurs when `opa_add_tag()` tries to access `span->tags` (a zval allocated with `emalloc()`). The memory has been freed or is in the process of being freed, causing a use-after-free crash.

### Memory Lifecycle Problem
1. `span_context_t` structures are allocated with `emalloc()` (request-local memory)
2. Spans are stored in `active_spans` hash table (which uses `malloc()`)
3. When request cleanup occurs (RSHUTDOWN), PHP frees all `emalloc()`'d memory
4. Span pointers in `active_spans` become dangling pointers
5. When `opa_add_tag()` accesses `span->tags`, it crashes because the memory is invalid

### Register Analysis
- **rdi (hash table pointer)**: `0x740d0ec31480` - invalid/freed memory address
- **Fault address**: `0x740d0ec31490` = rdi + 0x10 (accessing hash table structure field)
- **Error code**: `4` = Invalid memory reference (read access violation)

### Crashing Instruction
```
cmp 0x10(%rdi),%rbp
```
This instruction tries to compare a value at offset 0x10 from the hash table pointer, but the pointer points to freed memory.

## Fix Applied

### 1. Added `profiling_active` Check
Added check at the beginning of `opa_add_tag()` to skip operations if profiling is not active (i.e., during/after RSHUTDOWN).

### 2. Memory Allocation Issue
The root cause is that `span_context_t` structures use `emalloc()`, making them request-local. They become invalid after request cleanup even though they're stored in a persistent hash table.

## Remaining Issues

Segfaults still occur, suggesting:
1. The `profiling_active` check may not be sufficient
2. There may be race conditions between checking `profiling_active` and accessing memory
3. Memory may be freed between the check and the access

## Recommendations

1. **Consider using `malloc()` for span contexts** instead of `emalloc()` to make them persistent
2. **Add mutex protection** around span access operations
3. **Validate span pointer validity** before accessing span fields
4. **Ensure spans are removed from hash table** before being freed


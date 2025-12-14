#!/bin/bash
# Debug script to analyze segfaults from dmesg

echo "=== Segfault Analysis ==="
echo ""

# Get latest segfault
SEGFAULT=$(docker exec symfony-php dmesg | grep 'php-fpm.*segfault' | tail -1)

if [ -z "$SEGFAULT" ]; then
    echo "No segfault found in dmesg"
    exit 1
fi

echo "Latest segfault:"
echo "$SEGFAULT"
echo ""

# Extract addresses
FAULT_ADDR=$(echo "$SEGFAULT" | grep -oP 'segfault at \K[0-9a-f]+')
IP_ADDR=$(echo "$SEGFAULT" | grep -oP 'ip \K[0-9a-f]+')
SP_ADDR=$(echo "$SEGFAULT" | grep -oP 'sp \K[0-9a-f]+')
ERROR_CODE=$(echo "$SEGFAULT" | grep -oP 'error \K[0-9]+')
BASE_ADDR=$(echo "$SEGFAULT" | grep -oP '\[0x\K[0-9a-f]+')

echo "=== Addresses ==="
echo "Fault address (invalid memory access): 0x$FAULT_ADDR"
echo "Instruction pointer (where crash happened): 0x$IP_ADDR"
echo "Stack pointer: 0x$SP_ADDR"
echo "Error code: $ERROR_CODE (4 = Invalid memory reference - read access violation)"
echo "Base address: 0x$BASE_ADDR"
echo ""

# Calculate offset
if [ -n "$IP_ADDR" ] && [ -n "$BASE_ADDR" ]; then
    OFFSET=$(python3 -c "print(hex(0x$IP_ADDR - 0x$BASE_ADDR))" 2>/dev/null)
    echo "Offset from base: $OFFSET"
    echo ""
fi

# Try to get symbol info
echo "=== Symbol Analysis ==="
docker exec symfony-php gdb -batch \
    -ex "file /usr/local/sbin/php-fpm" \
    -ex "info symbol 0x$IP_ADDR" \
    -ex "x/10i 0x$IP_ADDR" \
    -ex "quit" 2>&1 | head -20

echo ""
echo "=== Error Code Meaning ==="
echo "Error 4 = Invalid memory reference (read access violation)"
echo "This typically means:"
echo "  - Trying to read from NULL pointer"
echo "  - Trying to read from freed memory"
echo "  - Trying to read from uninitialized pointer"
echo "  - Buffer overrun/underrun"
echo ""
echo "=== Analysis ==="
echo "The segfault is happening at instruction 0x$IP_ADDR"
echo "Trying to access memory at 0x$FAULT_ADDR (which is invalid)"
echo ""
echo "This suggests:"
echo "  1. A pointer is NULL or invalid"
echo "  2. Memory was freed but still being accessed"
echo "  3. Array/struct access out of bounds"
echo "  4. Use-after-free bug"


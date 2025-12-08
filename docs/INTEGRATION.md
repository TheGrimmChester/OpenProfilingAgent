# OpenProfilingAgent Multi-Language Integration Guide

Guide for integrating OpenProfilingAgent with applications written in various programming languages.

## Table of Contents

- [Protocol Overview](#protocol-overview)
- [PHP Integration](#php-integration)
- [Go Integration](#go-integration)
- [Python Integration](#python-integration)
- [Node.js Integration](#nodejs-integration)
- [SDK Development](#sdk-development)
- [Best Practices](#best-practices)

## Protocol Overview

OpenProfilingAgent uses a simple JSON protocol over Unix sockets, TCP/IP, or HTTP. Each span is sent as a single line of ND-JSON.

### Connection Methods

1. **Unix Socket** (Recommended for local communication): `/var/run/opa.sock`
2. **TCP/IP** (For remote communication): `host:port` (e.g., `agent:9090` or `127.0.0.1:9090`)
3. **HTTP**: `POST http://agent:8080/api/spans`

**Note**: Transport type is auto-detected. Paths starting with `/` are Unix sockets, otherwise TCP/IP (format `host:port`).

### Span Format

See [TECHNICAL.md](TECHNICAL.md) for complete protocol specification.

## PHP Integration

### Using Extension

```php
<?php
// Automatic instrumentation (no code needed)
// Just install extension and configure

// Manual spans
$spanId = opa_start_span('operation', ['tag' => 'value']);
// ... do work ...
opa_end_span($spanId);
```

### Using Helper Library

```php
<?php
require 'vendor/autoload.php';
use OpenProfilingAgent\Client;

// Unix socket
$client = new Client('/var/run/opa.sock');

// Or TCP/IP
$client = new Client('agent:9090');  // host:port format

$span = $client->createSpan('operation');
$client->endSpan($span);
```

See [User Guide](GUIDE.md) for detailed PHP examples.

## Go Integration

### Installation

```bash
go get github.com/TheGrimmChester/OpenProfilingAgent/agent
```

### Usage

```go
package main

import (
    "github.com/TheGrimmChester/OpenProfilingAgent/agent"
    "time"
)

func main() {
    client := opa.NewClient("/var/run/opa.sock")
    defer client.Close()
    
    span := client.StartSpan("operation", map[string]string{
        "service": "my-service",
    })
    defer span.End()
    
    // Do work
    time.Sleep(100 * time.Millisecond)
    
    span.AddTag("result", "success")
}
```

### Example SDK

See `docs/sdks/go-sdk/example.go` for complete example.

## Python Integration

### Installation

```bash
pip install opa-python
```

### Usage

```python
from opa import Client

client = Client('/var/run/opa.sock')

with client.span('operation', tags={'service': 'my-service'}) as span:
    # Do work
    time.sleep(0.1)
    span.add_tag('result', 'success')
```

### Example SDK

See `docs/sdks/python-sdk/opa_client.py` for complete example.

## Node.js Integration

### Installation

```bash
npm install opa-nodejs
```

### Usage

```javascript
const { Client } = require('opa-nodejs');

const client = new Client('/var/run/opa.sock');

const span = client.startSpan('operation', {
    service: 'my-service'
});

// Do work
await doWork();

span.addTag('result', 'success');
span.end();
```

### Example SDK

See `docs/sdks/nodejs-sdk/opa-client.js` for complete example.

## SDK Development

### Creating a New SDK

1. **Choose Language**: Select target language
2. **Implement Client**: Socket/HTTP communication
3. **Span Management**: Start, end, tag operations
4. **Error Handling**: Graceful degradation
5. **Documentation**: Usage examples

### Required Functions

- `startSpan(name, tags)` - Create new span
- `endSpan(spanId)` - End span
- `addTag(spanId, key, value)` - Add tag
- `setParent(spanId, parentId)` - Set parent

### Protocol Implementation

```python
import json
import socket

def send_span(socket_path, span_data):
    sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    sock.connect(socket_path)
    
    # Send as ND-JSON
    line = json.dumps(span_data) + '\n'
    sock.sendall(line.encode())
    sock.close()
```

## Best Practices

### Sampling

Implement client-side sampling:

```python
import random

def should_sample(rate):
    return random.random() < rate

if should_sample(0.1):  # 10% sampling
    span = client.startSpan('operation')
```

### Batching

Batch spans for efficiency:

```python
spans = []
for operation in operations:
    spans.append(create_span(operation))
    
# Send all at once
client.send_batch(spans)
```

### Error Handling

Handle connection failures gracefully:

```python
try:
    client.send_span(span)
except ConnectionError:
    # Log and continue
    logger.warning("Failed to send span")
```

### Metadata

Include language and framework info:

```python
span = client.startSpan('operation', tags={
    'language': 'python',
    'language_version': '3.11',
    'framework': 'django',
    'framework_version': '4.2'
})
```

## Next Steps

- See [INSTALLATION.md](INSTALLATION.md) for setup
- See [User Guide](GUIDE.md) for usage examples
- See [TECHNICAL.md](TECHNICAL.md) for protocol details


# WebSocket Transport Architecture

## Overview

Figurate supports WebSocket communication in **two modes**: Server and Client. This allows bidirectional, real-time communication both as a broadcaster (Server mode) and as a consumer (Client mode).

---

## Mode 1: Server Mode (Broadcasting)

**Figurate hosts WebSocket server → Clients connect IN**

### Use Cases
- Real-time UI updates for frontend applications
- Mobile app push notifications
- Dashboard subscriptions
- Multi-user collaboration features
- Live chat/messaging

### Architecture

```
┌─────────────────────────────────────┐
│  Figurate (Laravel Reverb)          │
│                                     │
│  Thread Post Created                │
│         ↓                           │
│  WebSocketServerTransport           │
│         ↓                           │
│  Broadcast::channel()->send()       │
│         ↓                           │
│  Laravel Reverb Server              │
└─────────┬───────────────────────────┘
          │ WebSocket Connections
          ↓
┌─────────────────────────────────────┐
│  Connected Clients                  │
│  • Browser (JS WebSocket)           │
│  • Mobile App (WebSocket client)    │
│  • External systems listening       │
└─────────────────────────────────────┘
```

### Configuration

```php
Channel::create([
    'driver' => 'websocket',
    'direction' => 'outbound', // or 'bidirectional'
    'config' => [
        'mode' => 'server',
        'channel_name' => 'thread.{thread_uuid}', // Broadcasting channel
    ],
]);
```

### Implementation Status
✅ **Implemented** via `WebSocketServerTransport`
✅ Uses Laravel Broadcasting/Reverb (already installed)
✅ Ready to use

---

## Mode 2: Client Mode (Outbound Connections)

**Figurate connects OUT to remote WebSocket servers**

### Use Cases
- Connecting to Slack Real-Time Messaging API
- Discord Gateway integration
- Custom WebSocket APIs (trading platforms, live feeds)
- IoT device communication
- Real-time data streaming services

### Architecture

```
┌─────────────────────────────────────┐
│  External WebSocket Server          │
│  (Slack, Discord, Custom API)       │
│         ↑                           │
│         │ Persistent WS Connection  │
│         ↓                           │
└─────────┬───────────────────────────┘
          │
┌─────────┴───────────────────────────┐
│  Figurate (WebSocket Client)        │
│                                     │
│  Thread Post Created                │
│         ↓                           │
│  WebSocketClientTransport           │
│         ↓                           │
│  WebSocketConnectionManager         │
│    • Connection Pool                │
│    • Reconnection Logic             │
│    • Heartbeat/Ping                 │
│         ↓                           │
│  WebSocketConnection                │
│    • Persistent connection          │
│    • Send/Receive messages          │
└─────────────────────────────────────┘
```

### Configuration

```php
Channel::create([
    'driver' => 'websocket',
    'endpoint_url' => 'wss://api.example.com/realtime',
    'direction' => 'outbound',
    'config' => [
        'mode' => 'client',
    ],
    'credentials' => [
        'headers' => [
            'Authorization' => 'Bearer token',
        ],
    ],
]);
```

### Implementation Status
✅ **Implemented** (interface complete)
⚠️ **TODO**: Add actual WebSocket client library (e.g., `textalk/websocket` or `ratchetphp/pawl`)
⚠️ **TODO**: Implement reconnection logic with exponential backoff
⚠️ **TODO**: Add heartbeat/ping mechanism

---

## Automatic Mode Selection

The driver automatically selects the appropriate mode based on:

1. **Explicit config**: `config.mode` set to `'client'` or `'server'`
2. **Channel direction**:
   - `direction: 'outbound'` → defaults to **client mode** (connect out)
   - `direction: 'inbound'` or `'bidirectional'` → defaults to **server mode** (broadcast)
3. **Per-binding override**: Can override mode in `ChannelRelation.config.mode`

---

## Inbound Message Handling

Both modes can receive messages (bidirectional):

### Server Mode Inbound
- Clients connect to Reverb
- Send messages to specific routes
- Laravel receives via Broadcasting events
- Create `Post` records from incoming messages

### Client Mode Inbound
- `WebSocketConnection::receive()` polls for messages
- Background worker listens to connections
- Normalize incoming messages
- Create `Post` records from received data

---

## Connection Lifecycle (Client Mode)

```
1. Channel Created
     ↓
2. First message triggers connection
     ↓
3. WebSocketConnectionManager::getOrCreateConnection()
     ↓
4. WebSocketConnection::connect()
     ↓
5. Connection stored in pool
     ↓
6. Subsequent messages reuse connection
     ↓
7. Reconnection on disconnect (auto)
     ↓
8. Heartbeat keeps connection alive
```

---

## Integration with Outbox System

```
Post Created
  → EnqueueThreadMessageOutbox
  → Outbox record created
  → DeliverOutboxMessage job
  → ChannelOutboundMessageSender
  → WebsocketChannelDriver::send()
  → [Mode Resolution]
     ├─ Server Mode → WebSocketServerTransport → Broadcast::send()
     └─ Client Mode → WebSocketClientTransport → connection->send()
```

---

## Next Steps for Full Implementation

### Priority 1: Server Mode (Already Working)
✅ Uses Laravel Reverb (already installed)
✅ `WebSocketServerTransport` complete
✅ Can broadcast immediately

### Priority 2: Client Mode (Ready to Activate)

1. **Add WebSocket client dependency** (maintained fork):
   ```bash
   composer require phrity/websocket
   ```

   **Why phrity/websocket?**
   - ✅ Actively maintained (textalk/websocket is abandoned)
   - ✅ Built-in middleware support (CloseHandler, PingResponder)
   - ✅ Event listeners (onText, onBinary)
   - ✅ Better error handling
   - ✅ PHP 8.x support

   Sources: [Phrity WebSocket Documentation](https://phrity.sirn.se/websocket), [GitHub Repository](https://github.com/sirn-se/websocket-php)

2. **Implementation is already complete** - Just run composer install!

3. **Reconnection logic** - ✅ Already implemented with exponential backoff

4. **Heartbeat mechanism** - ✅ Built-in via `PingResponder` middleware

5. **Create inbound message listener** (for receiving messages):
   ```php
   php artisan make:command WebSocket:Listen
   ```

---

## Configuration Examples

### Example 1: Broadcast to Web UI (Server Mode)
```php
Channel::create([
    'driver' => 'websocket',
    'name' => 'Reverb Broadcast',
    'direction' => 'outbound',
    'config' => [
        'mode' => 'server',
        'channel_name' => 'thread.{thread_uuid}',
    ],
]);
```

### Example 2: Connect to Slack (Client Mode)
```php
Channel::create([
    'driver' => 'websocket',
    'name' => 'Slack RTM',
    'endpoint_url' => 'wss://slack.com/websocket',
    'direction' => 'bidirectional',
    'config' => [
        'mode' => 'client',
    ],
    'credentials' => [
        'headers' => [
            'Authorization' => 'Bearer xoxb-your-token',
        ],
    ],
]);
```

### Example 3: Discord Gateway (Client Mode)
```php
Channel::create([
    'driver' => 'websocket',
    'name' => 'Discord Gateway',
    'endpoint_url' => 'wss://gateway.discord.gg/?v=10&encoding=json',
    'direction' => 'bidirectional',
    'config' => [
        'mode' => 'client',
    ],
]);
```

---

## Testing

```php
// Test server mode
$channel = Channel::factory()->create([
    'driver' => 'websocket',
    'config' => ['mode' => 'server'],
]);

$transport = app(WebSocketServerTransport::class);
$result = $transport->deliver($channel, $thread, $post, $config);

assert($result['mode'] === 'server');
assert($result['transport'] === 'websocket-server');

// Test client mode
$channel = Channel::factory()->create([
    'driver' => 'websocket',
    'endpoint_url' => 'wss://example.com/ws',
    'config' => ['mode' => 'client'],
]);

$transport = app(WebSocketClientTransport::class);
$result = $transport->deliver($channel, $thread, $post, $config);

assert($result['mode'] === 'client');
assert($result['transport'] === 'websocket-client');
```

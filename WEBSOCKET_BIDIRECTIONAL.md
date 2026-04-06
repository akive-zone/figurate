# Bidirectional WebSocket Communication

## Overview

Figurate supports **full bidirectional WebSocket communication** using two complementary systems:

1. **Server → Client** (Outbound) via **Laravel Reverb** (Pusher protocol)
2. **Client → Server** (Inbound) via **phrity/websocket Server**

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     Figurate Server                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌───────────────────────────────────────────────┐    │
│  │  Laravel Reverb (Pusher Protocol)             │    │
│  │  Port: 8080 (default)                         │    │
│  │  Direction: Server → Client (Broadcasting)    │    │
│  └────────────────┬──────────────────────────────┘    │
│                   ↓                                     │
│         Broadcasts to clients                          │
│                                                         │
│  ┌───────────────────────────────────────────────┐    │
│  │  phrity/websocket Server                      │    │
│  │  Port: 8090 (customizable)                    │    │
│  │  Direction: Client → Server (Receiving)       │    │
│  └────────────────┬──────────────────────────────┘    │
│                   ↑                                     │
│         Receives from clients                          │
│                                                         │
└─────────────────┬───────────────────▲───────────────────┘
                  │                   │
                  ↓ Broadcast         ↑ Send Messages
         ┌────────────────────────────────────┐
         │         Client Application          │
         │  (Browser, Mobile App, etc.)        │
         │                                     │
         │  • Connect to Reverb (ws://8080)   │
         │  • Connect to Server (ws://8090)   │
         └────────────────────────────────────┘
```

---

## Message Protocol

### Client → Server (Inbound)

**JSON Format:**
```json
{
  "id": "unique-message-id",
  "type": "message",
  "thread_uuid": "thread-uuid-here",
  "sender": "user-uuid-or-id",
  "text": "Message content",
  "timestamp": "2026-04-06T12:00:00Z"
}
```

**Required Fields:**
- `thread_uuid` or `thread` - Thread identifier
- `text` or `message` - Message content

**Optional Fields:**
- `id` - Client-generated message ID for tracking
- `type` - Message type (default: "message")
- `sender` - User identifier (UUID or ID)
- `timestamp` - Client timestamp
- Any custom fields in `data`

**Response:**
```json
{
  "status": "success",
  "message_id": "unique-message-id",
  "post_id": 12345,
  "post_ulid": "01H1X2Y3Z4...",
  "thread_uuid": "thread-uuid-here",
  "timestamp": "2026-04-06T12:00:01Z"
}
```

### Server → Client (Outbound)

**Via Laravel Reverb (Pusher Protocol):**
```json
{
  "event": "thread.post.created",
  "channel": "thread.{uuid}",
  "data": {
    "post": {
      "id": 12345,
      "ulid": "01H1X2Y3Z4...",
      "text": "Message content",
      "sender": {
        "id": 1,
        "name": "John Doe"
      }
    },
    "thread": {
      "uuid": "thread-uuid"
    }
  }
}
```

---

## Setup Instructions

### 1. Start Laravel Reverb (Server → Client)

```bash
# Already configured in your project
php artisan reverb:start

# Or via Supervisor for production
```

**Config:** `config/broadcasting.php`
```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],
],
```

### 2. Start phrity/websocket Server (Client → Server)

```bash
# Start WebSocket inbound server
php artisan websocket:serve --port=8090

# With specific channel binding
php artisan websocket:serve --port=8090 --channel=CHANNEL_UUID

# Custom host/port
php artisan websocket:serve --host=0.0.0.0 --port=8091
```

**Supervisor Config** (`websocket-server.conf`):
```ini
[program:websocket-server]
command=php artisan websocket:serve --port=8090
directory=/path/to/figurate
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/websocket-server.log
stderr_logfile=/var/log/websocket-server-error.log
```

---

## Client Integration

### JavaScript/TypeScript Client

**Install Dependencies:**
```bash
npm install laravel-echo pusher-js
npm install ws  # For direct WebSocket connection
```

**Dual Connection Setup:**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Setup Echo for Server → Client (Reverb)
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.REVERB_APP_KEY,
    wsHost: process.env.REVERB_HOST,
    wsPort: process.env.REVERB_PORT ?? 8080,
    forceTLS: process.env.REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Listen for incoming messages from server
window.Echo.channel('thread.' + threadUuid)
    .listen('.thread.post.created', (event) => {
        console.log('New message:', event.data.post.text);
        // Update UI
    });

// Setup WebSocket for Client → Server (Inbound)
const inboundWs = new WebSocket('ws://localhost:8090');

inboundWs.onopen = () => {
    console.log('Connected to inbound WebSocket server');
};

inboundWs.onmessage = (event) => {
    const response = JSON.parse(event.data);
    console.log('Server response:', response);

    if (response.status === 'success') {
        console.log('Message delivered:', response.post_ulid);
    } else {
        console.error('Message failed:', response.error);
    }
};

// Send message to server
function sendMessage(threadUuid, text, senderUuid) {
    const message = {
        id: crypto.randomUUID(),
        type: 'message',
        thread_uuid: threadUuid,
        sender: senderUuid,
        text: text,
        timestamp: new Date().toISOString(),
    };

    inboundWs.send(JSON.stringify(message));
}
```

### React Component Example

```tsx
import { useEffect, useState, useRef } from 'react';
import Echo from 'laravel-echo';

function ChatComponent({ threadUuid, userUuid }) {
    const [messages, setMessages] = useState([]);
    const [inputText, setInputText] = useState('');
    const wsRef = useRef(null);

    useEffect(() => {
        // Listen for server broadcasts (Reverb)
        const echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        });

        echo.channel(`thread.${threadUuid}`)
            .listen('.thread.post.created', (event) => {
                setMessages(prev => [...prev, event.data.post]);
            });

        // Connect to inbound server
        const ws = new WebSocket('ws://localhost:8090');

        ws.onopen = () => {
            console.log('Connected to chat server');
        };

        ws.onmessage = (event) => {
            const response = JSON.parse(event.data);
            if (response.status === 'success') {
                // Message acknowledged
                console.log('Message sent:', response.post_ulid);
            }
        };

        wsRef.current = ws;

        return () => {
            echo.leaveChannel(`thread.${threadUuid}`);
            ws.close();
        };
    }, [threadUuid]);

    const sendMessage = () => {
        if (!inputText.trim()) return;

        const message = {
            id: crypto.randomUUID(),
            type: 'message',
            thread_uuid: threadUuid,
            sender: userUuid,
            text: inputText,
            timestamp: new Date().toISOString(),
        };

        wsRef.current.send(JSON.stringify(message));
        setInputText('');
    };

    return (
        <div>
            <div className="messages">
                {messages.map(msg => (
                    <div key={msg.id}>{msg.text}</div>
                ))}
            </div>
            <input
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                onKeyPress={(e) => e.key === 'Enter' && sendMessage()}
            />
            <button onClick={sendMessage}>Send</button>
        </div>
    );
}
```

---

## Testing

### Test Inbound Messages with wscat

```bash
# Install wscat
npm install -g wscat

# Connect to inbound server
wscat -c ws://localhost:8090

# Send test message
> {"id":"test-1","type":"message","thread_uuid":"your-thread-uuid","sender":"user-uuid","text":"Hello from client!"}

# Response:
< {"status":"success","message_id":"test-1","post_id":123,"post_ulid":"01H...","thread_uuid":"your-thread-uuid","timestamp":"2026-04-06T12:00:01Z"}
```

### Test Outbound Broadcasts

```php
// In tinker or controller
$thread = Thread::where('uuid', 'your-thread-uuid')->first();

$post = $thread->posts()->create([
    'text' => 'Test broadcast message',
    'type' => 'message',
]);

// If channel is bound to thread, message automatically broadcasts via Reverb
```

---

## Production Deployment

### Ports

- **Reverb (Outbound):** 8080 (or configured port)
- **Inbound Server:** 8090 (or custom port)

### Nginx Configuration

```nginx
# Reverb (Laravel Broadcasting)
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}

# Inbound WebSocket Server
location /ws {
    proxy_pass http://127.0.0.1:8090;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_cache_bypass $http_upgrade;
}
```

### Supervisor Configuration

```ini
[program:reverb]
command=php artisan reverb:start
directory=/path/to/figurate
autostart=true
autorestart=true
user=www-data

[program:websocket-inbound]
command=php artisan websocket:serve --port=8090
directory=/path/to/figurate
autostart=true
autorestart=true
user=www-data
```

---

## Use Cases

### Real-Time Chat

- **Outbound:** Server broadcasts new messages to all participants
- **Inbound:** Users send messages to server, which creates Posts

### Collaborative Editing

- **Outbound:** Server broadcasts document changes
- **Inbound:** Users send their edits to server

### Live Notifications

- **Outbound:** Server pushes notifications to users
- **Inbound:** Users acknowledge or respond to notifications

### Gaming/Interactive Apps

- **Outbound:** Server broadcasts game state updates
- **Inbound:** Users send actions/commands

---

## Protocol Summary

| Direction | System | Port | Protocol | Use Case |
|-----------|--------|------|----------|----------|
| Server → Client | Laravel Reverb | 8080 | Pusher | Broadcasting |
| Client → Server | phrity/websocket | 8090 | JSON | Receiving |

**Sources:**
- [Phrity WebSocket Documentation](https://phrity.sirn.se/websocket)
- [Phrity WebSocket Server Examples](https://phrity.sirn.se/websocket/3.3/examples)
- [GitHub Repository](https://github.com/sirn-se/websocket-php)
- [Laravel Reverb Documentation](https://laravel.com/docs/11.x/reverb)

---

## Message Flow

```
┌─────────────┐                    ┌──────────────┐
│   Client    │                    │   Figurate   │
└─────────────┘                    └──────────────┘
       │                                   │
       │ 1. Send Message (JSON)            │
       ├──────────────────────────────────>│
       │    ws://localhost:8090            │
       │                                   │
       │                     2. Create Post│
       │                     3. Process    │
       │                                   │
       │ 4. Acknowledgment                 │
       │<──────────────────────────────────┤
       │    {"status":"success"}           │
       │                                   │
       │                     5. Broadcast  │
       │<──────────────────────────────────┤
       │    via Reverb:8080                │
       │    thread.post.created event      │
       │                                   │
```

This gives you **complete bidirectional WebSocket communication** with separate, purpose-built systems for each direction! 🚀

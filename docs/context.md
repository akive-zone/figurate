# Context

Context is the engine of Figurate.

Context is how external systems, humans, agents, and tools become durable work inside Fig. The main primitives are:

- `Channel`: an integration surface or delivery boundary.
- `Space`: a long-lived work context.
- `Thread`: an active session or workstream inside a context.
- `Post`: a durable message, event, artifact, review, decision, or update.

Graph edges can link these primitives, but Context is where the actual work is created and carried.

## Mental Model

```text
External system / chat / CRM / ERP / human input
        -> Channel
        -> Route
        -> Address
        -> Space
        -> Thread
        -> Posts
        -> agent review / action / callback
```

For the CRM-review product shape:

```text
CRM conversation JSON
        -> resolve/create CRM review Space
        -> POST /spaces/{space}/posts
        -> source conversation post
        -> review thread
        -> reviewer agent output
        -> review post
        -> optional CRM callback
```

Conversation ingestion belongs to Context, not Graph. The external system first resolves or creates the relevant Space, then posts the conversation packet into that Space.

## Channels

Channels describe integration surfaces and delivery routes.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/channels` | `auth:sanctum,passport` | List channels. |
| `POST` | `/api/channels` | `auth:sanctum,passport` | Create a channel. |
| `PATCH` | `/api/channels/{channel}` | `auth:sanctum,passport` | Update a channel. |
| `DELETE` | `/api/channels/{channel}` | `auth:sanctum,passport` | Delete a channel. |
| `POST` | `/api/channels/{channel}/skills` | `auth:sanctum,passport` | Attach skill media/context to a channel. |

## Channel Connections

Connections bind channels to owners or contexts.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/channels/{channel}/connections` | `auth:sanctum,passport` | List channel connections. |
| `POST` | `/api/channels/{channel}/connections` | `auth:sanctum,passport` | Create a channel connection. |
| `PATCH` | `/api/channels/{channel}/connections/{connection}` | `auth:sanctum,passport` | Update a channel connection. |
| `DELETE` | `/api/channels/{channel}/connections/{connection}` | `auth:sanctum,passport` | Delete a channel connection. |

## Channel Routes

Routes define how channel traffic maps into Fig context.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/channels/{channel}/routes` | `auth:sanctum,passport` | List channel routes. |
| `POST` | `/api/channels/{channel}/routes` | `auth:sanctum,passport` | Create a channel route. |
| `PATCH` | `/api/channels/{channel}/routes/{route}` | `auth:sanctum,passport` | Update a channel route. |
| `DELETE` | `/api/channels/{channel}/routes/{route}` | `auth:sanctum,passport` | Delete a channel route. |
| `POST` | `/api/channels/{channel}/routes/{route}/skills` | `auth:sanctum,passport` | Attach skill media/context to a route. |

## Channel Addresses

Addresses represent route-specific external identities, destinations, or addressable contexts.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/channels/{channel}/routes/{route}/addresses` | `auth:sanctum,passport` | List route addresses. |
| `POST` | `/api/channels/{channel}/routes/{route}/addresses` | `auth:sanctum,passport` | Create a route address. |
| `PATCH` | `/api/channels/{channel}/routes/{route}/addresses/{address}` | `auth:sanctum,passport` | Update a route address. |
| `DELETE` | `/api/channels/{channel}/routes/{route}/addresses/{address}` | `auth:sanctum,passport` | Delete a route address. |
| `POST` | `/api/channels/{channel}/routes/{route}/addresses/{address}/skills` | `auth:sanctum,passport` | Attach skill media/context to an address. |

## Channel Ingress

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/channel-routes/{route}/inbound` | Spatie webhook signature/config | Receive inbound external traffic for a channel route. |

This route is explicitly prefixed with `/api` in `routes/webhook.php`.

## Spaces

Spaces are long-lived work contexts.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/spaces` | `auth:sanctum,passport` | List spaces available to the authenticated user. |
| `GET` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | List posts in a space. |
| `POST` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | Create a post in a space. |
| `GET` | `/api/spaces/{space}/threads` | `auth:sanctum,passport` | List threads in a space. |
| `POST` | `/api/spaces/{space}/threads` | `auth:sanctum,passport` | Create a thread in a space. |

## Threads

Threads are active sessions or workstreams.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/threads/{thread}` | `auth:sanctum,passport` | Read a thread. |
| `GET` | `/api/threads/{thread}/posts` | `auth:sanctum,passport` | List posts in a thread. |
| `POST` | `/api/threads/{thread}/posts` | `auth:sanctum,passport` | Create a post in a thread. |
| `GET` | `/api/threads/{thread}/posts/{post}/turns` | `auth:sanctum,passport` | Read projected assistant turns for a thread post. |

## Posts

Posts are durable context artifacts. They currently appear through space/thread APIs and graph relations rather than a standalone public `POST /posts` endpoint.

Current post-facing routes:

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | List posts in a space. |
| `POST` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | Store arbitrary context data as a post in a space. |
| `GET` | `/api/threads/{thread}/posts` | `auth:sanctum,passport` | List posts in a thread. |
| `POST` | `/api/threads/{thread}/posts` | `auth:sanctum,passport` | Store arbitrary context data as a post in a thread. |
| `GET` | `/api/threads/{thread}/posts/{post}/turns` | `auth:sanctum,passport` | Read assistant turns projected from a thread post. |
| `GET` | `/api/posts/{post}` | `auth:sanctum,passport` | Read a post by ULID or database id. |
| `GET` | `/api/posts/{post}/turns` | `auth:sanctum,passport` | Read assistant turns projected from a thread post. |

### Creating Space Posts

`POST /spaces/{space}/posts` is the ingestion point for external context once the caller knows which Space should hold the work.

`POST /threads/{thread}/posts` accepts the same request shape when the caller already knows the review or workstream Thread.

The post accepts any JSON object shape. Fig treats these fields specially when present:

| Field | Purpose |
| --- | --- |
| `type` | Optional post type such as `crm.conversation`; defaults to `context`. |
| `tag` | Optional caller-defined grouping label. |
| `status` | Optional post status; defaults to `active`. |
| `text` | Optional human-readable summary, also copied into the stored payload. |
| `payload` | Optional explicit payload. If omitted, Fig stores the remaining request body as payload. |
| `meta` | Optional Fig-side metadata object. |
| `occurred_at` | Optional source event timestamp; defaults to ingestion time. |

Example:

```json
{
  "type": "crm.conversation",
  "text": "Customer asked for refund after failed fulfilment.",
  "source": {
    "system": "crm",
    "conversation_id": "crm-conv-1001"
  },
  "conversation": {
    "messages": [
      {
        "sender": "customer",
        "body": "The order never arrived."
      }
    ]
  },
  "meta": {
    "review_requested": true
  }
}
```

## Broadcast Context Channels

These are realtime authorization channels, not HTTP endpoints.

| Channel | Guards | Purpose |
| --- | --- | --- |
| `threads.{threadUuid}` | `web`, `sanctum`, `passport` | Authorize realtime thread updates. |
| `spaces.{spaceUuid}` | `web`, `sanctum`, `passport` | Authorize realtime space updates. |
| `users.{userUuid}.notifications` | `web`, `sanctum`, `passport` | Authorize user notification stream. |

## CRM Review Flow

1. CRM sends or resolves a stable external conversation identity.
2. Fig resolves or creates the relevant `Space`.
3. CRM posts the conversation packet to `POST /spaces/{space}/posts`.
4. Fig stores the source conversation as a durable `Post`.
5. Fig links related `Space`, `Thread`, and `Post` records with Graph edges when needed.
6. Fig queues or invokes an agent review.
7. Fig stores the review result as another post.
8. Fig optionally sends a callback to the CRM.

See [Agent Invocation Use Cases](./agent-invocation-use-cases.md) for the explicit invocation and task-tracking workflow across CRM review, generic external artifacts, and service fulfillment.

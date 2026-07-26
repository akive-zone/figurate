# Endpoint Catalog

This document captures the primary product surface of Figurate: the APIs and protocols used by third-party systems and first-party clients.

Laravel is configured with `apiPrefix: 'api'` in `bootstrap/app.php`, so product API routes are exposed under `/api`.

The bundled web workspace and control panel are supporting interfaces. Integrations should depend on the authenticated API and protocol contracts documented here rather than UI routes.

Grouped docs:

- [Auth](./auth.md)
- [Context](./context.md)
- [Graph](./graph.md)
- [Form](./form.md)

Generated from:

```text
php artisan route:list --except-vendor
php artisan route:list --path=a2a
php artisan route:list --path=mcp
php artisan route:list --path=webhooks
php artisan route:list --path=channel-routes
```

## Auth

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `POST` | `/api/auth/register` | public | unnamed | Register a user. |
| `POST` | `/api/auth/login` | public | unnamed | Log in and issue auth/session state. |
| `POST` | `/api/auth/logout` | `auth:sanctum,passport` | unnamed | Log out the authenticated user. |
| `POST` | `/api/auth/broadcasting` | `auth:sanctum,passport` | unnamed | Authenticate private/presence broadcast channels. |
| `GET` | `/api/auth/user` | `auth:sanctum,passport` | `api.auth.user.show` | Read the current User and effective abilities. |
| `PATCH` | `/api/auth/user` | `auth:sanctum,passport` | `api.auth.user.update` | Update the current User's public fields. |
| `GET` | `/api/auth/passkeys` | `auth:sanctum,passport` | `api.passkeys.index` | List passkeys for the authenticated user. |
| `POST` | `/api/auth/passkeys/options` | public | `api.passkeys.register-options` | Generate passkey registration options. |
| `POST` | `/api/auth/passkeys` | public | `api.passkeys.store` | Store/register a passkey. |
| `DELETE` | `/api/auth/passkeys/{passkey}` | `auth:sanctum,passport` | `api.passkeys.destroy` | Delete a passkey. |
| `POST` | `/api/users` | `auth:sanctum,passport`, `EnsureTransportUser:subject` | `api.users.store` | Provision a delegated User with scoped abilities. |

## Spaces, Threads, And Posts

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/spaces` | `auth:sanctum,passport` | `api.spaces.index` | List spaces available to the authenticated user. |
| `GET` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | `api.spaces.posts.index` | List posts in a space. |
| `POST` | `/api/spaces/{space}/posts` | `auth:sanctum,passport` | `api.spaces.posts.store` | Store arbitrary context data as a post in a space. |
| `GET` | `/api/spaces/{space}/threads` | `auth:sanctum,passport` | `api.spaces.threads.index` | List threads in a space. |
| `POST` | `/api/spaces/{space}/threads` | `auth:sanctum,passport` | `api.spaces.threads.store` | Create a thread in a space. |
| `GET` | `/api/threads/{thread}` | `auth:sanctum,passport` | `api.threads.show` | Read a thread. |
| `POST` | `/api/threads/{thread}/posts` | `auth:sanctum,passport` | `api.threads.posts.store` | Store arbitrary context data as a post in a thread. |
| `GET` | `/api/threads/{thread}/posts/{post}/turns` | `auth:sanctum,passport` | `api.threads.posts.turns.index` | Read projected assistant turns for a thread post. |
| `GET` | `/api/posts/{post}` | `auth:sanctum,passport` | `api.posts.show` | Read a post by ULID or database id. |
| `GET` | `/api/posts/{post}/turns` | `auth:sanctum,passport` | `api.posts.turns.index` | Read projected assistant turns for a thread post. |

## Graph

The graph API links existing Fig nodes. It is not the conversation-ingestion endpoint.

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/graph/edges` | `auth:sanctum,passport` | `api.graph.edges.index` | Explore graph edges from a `space`, `thread`, or `post`. |
| `POST` | `/api/graph/edges` | `auth:sanctum,passport` | `api.graph.edges.store` | Create an edge between existing `space`, `thread`, or `post` nodes. |

See [Graph](./graph.md) for request/response details.

## Channels

Channels describe integration surfaces and delivery routes.

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/channels` | `auth:sanctum,passport` | `api.channels.index` | List channels. |
| `POST` | `/api/channels` | `auth:sanctum,passport` | `api.channels.store` | Create a channel. |
| `PATCH` | `/api/channels/{channel}` | `auth:sanctum,passport` | `api.channels.update` | Update a channel. |
| `DELETE` | `/api/channels/{channel}` | `auth:sanctum,passport` | `api.channels.destroy` | Delete a channel. |
| `POST` | `/api/channels/{channel}/skills` | `auth:sanctum,passport` | `api.channels.skills.store` | Attach skill media/context to a channel. |

## Channel Connections

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/channels/{channel}/connections` | `auth:sanctum,passport` | `api.channels.connections.index` | List channel connections. |
| `POST` | `/api/channels/{channel}/connections` | `auth:sanctum,passport` | `api.channels.connections.store` | Create a channel connection. |
| `PATCH` | `/api/channels/{channel}/connections/{connection}` | `auth:sanctum,passport` | `api.channels.connections.update` | Update a channel connection. |
| `DELETE` | `/api/channels/{channel}/connections/{connection}` | `auth:sanctum,passport` | `api.channels.connections.destroy` | Delete a channel connection. |

## Channel Routes And Addresses

Routes define how channel traffic maps into Fig context. Addresses define route-specific external identities or destinations.

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/channels/{channel}/routes` | `auth:sanctum,passport` | `api.channels.routes.index` | List channel routes. |
| `POST` | `/api/channels/{channel}/routes` | `auth:sanctum,passport` | `api.channels.routes.store` | Create a channel route. |
| `PATCH` | `/api/channels/{channel}/routes/{route}` | `auth:sanctum,passport` | `api.channels.routes.update` | Update a channel route. |
| `DELETE` | `/api/channels/{channel}/routes/{route}` | `auth:sanctum,passport` | `api.channels.routes.destroy` | Delete a channel route. |
| `POST` | `/api/channels/{channel}/routes/{route}/skills` | `auth:sanctum,passport` | `api.channels.routes.skills.store` | Attach skill media/context to a route. |
| `GET` | `/api/channels/{channel}/routes/{route}/addresses` | `auth:sanctum,passport` | `api.channels.routes.addresses.index` | List route addresses. |
| `POST` | `/api/channels/{channel}/routes/{route}/addresses` | `auth:sanctum,passport` | `api.channels.routes.addresses.store` | Create a route address. |
| `PATCH` | `/api/channels/{channel}/routes/{route}/addresses/{address}` | `auth:sanctum,passport` | `api.channels.routes.addresses.update` | Update a route address. |
| `DELETE` | `/api/channels/{channel}/routes/{route}/addresses/{address}` | `auth:sanctum,passport` | `api.channels.routes.addresses.destroy` | Delete a route address. |
| `POST` | `/api/channels/{channel}/routes/{route}/addresses/{address}/skills` | `auth:sanctum,passport` | `api.channels.routes.addresses.skills.store` | Attach skill media/context to an address. |

## Channel Webhooks

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `POST` | `/api/channel-routes/{route}/inbound` | Spatie webhook signature/config | `webhook-client-channel_route_inbound` | Receive inbound external traffic for a channel route. |

This route is explicitly prefixed with `/api` in `routes/webhook.php`.

## Form

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `POST` | `/api/form` | `auth:sanctum,passport` | `api.form.store` | Generic form submission endpoint. |

## ACP

ACP routes are authenticated with `auth:sanctum,passport`, require a resolved transport user, and require the `acp:use` token ability.

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/acp/sessions` | ACP auth middleware | `api.acp.sessions.index` | List ACP sessions. |
| `POST` | `/api/acp/sessions` | ACP auth middleware | `api.acp.sessions.store` | Create an ACP session. |
| `GET` | `/api/acp/sessions/{session}` | ACP auth middleware | `api.acp.sessions.show` | Read an ACP session. |
| `POST` | `/api/acp/sessions/{session}/prompt` | ACP auth middleware | `api.acp.sessions.prompt` | Prompt an ACP session. |
| `GET` | `/api/acp/tasks/{task}` | ACP auth middleware | `api.acp.tasks.show` | Read ACP task state. |
| `POST` | `/api/acp/tasks/{task}/cancel` | ACP auth middleware | `api.acp.tasks.cancel` | Cancel an ACP task. |

## A2A

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/.well-known/agent-card` | public API route | `a2a.agent-card` | Publish the local A2A agent card. |
| `POST` | `/api/a2a/rpc` | A2A auth/ability middleware | `api.a2a.rpc` | JSON-RPC A2A endpoint for task/message methods. |
| `POST` | `/api/a2a/stream` | A2A auth/ability middleware | `api.a2a.stream` | Streaming A2A endpoint. |
| `POST` | `/api/a2a/webhooks/push` | Spatie webhook signature/config | `webhook-client-a2a_push` | Receive A2A push webhook callbacks. |

## MCP

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/mcp` | `auth:sanctum,passport`, `mcp:use` ability | vendor route | MCP server transport. |
| `POST` | `/api/mcp` | `auth:sanctum,passport`, `mcp:use` ability | vendor route | MCP server transport. |

MCP OAuth discovery/registration routes are provided by Laravel MCP and Passport:

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/.well-known/oauth-authorization-server/{path?}` | MCP OAuth authorization-server metadata. |
| `GET` | `/.well-known/oauth-protected-resource/{path?}` | MCP OAuth protected-resource metadata. |
| `POST` | `/oauth/register` | Dynamic OAuth client registration for MCP. |

## Accounts

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/accounts/current` | package-defined | `api.accounts.current` | Read current account context from the account-manager module. |

This route is package/module-provided and keeps its `/api` prefix.

## Web UI

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/` | web/session context | `chat.index` | Main chat/space index. |
| `GET` | `/create` | web/session context | `chat.create` | Chat/space creation entrypoint. |
| `GET` | `/c/{space}` | web/session context | `chat.show` | Show a space. |
| `GET` | `/c/{space}/t/{thread}` | web/session context | `chat.thread` | Show a specific thread in a space. |

## Control Panel

The optional `control-panel` extension owns these Filament routes. They require Filament authentication and `EnsurePanelUser`.

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| `GET` | `/p/context-servers` | `filament.server-control.resources.context-servers.index` | List context servers. |
| `GET` | `/p/context-servers/create` | `filament.server-control.resources.context-servers.create` | Create context server page. |
| `GET` | `/p/context-servers/{record}/edit` | `filament.server-control.resources.context-servers.edit` | Edit context server page. |

## Framework And Package Routes

These are installed by Laravel packages and are not primary product endpoints, but they are present in `route:list`.

| Method | Path | Source | Purpose |
| --- | --- | --- | --- |
| `GET` | `/up` | Laravel health route | Health check. |
| `GET|POST` | `/broadcasting/auth` | Laravel broadcasting | Broadcast auth endpoint. |
| `POST` | `/livewire/update` | Livewire | Livewire update transport. |
| `POST` | `/livewire/upload-file` | Livewire | Livewire upload transport. |
| `GET` | `/oauth/authorize` | Passport | OAuth authorization screen. |
| `POST` | `/oauth/authorize` | Passport | Approve OAuth authorization. |
| `DELETE` | `/oauth/authorize` | Passport | Deny OAuth authorization. |
| `POST` | `/oauth/token` | Passport | Issue OAuth token. |
| `POST` | `/oauth/token/refresh` | Passport | Refresh OAuth token. |
| `GET` | `/oauth/device` | Passport | Device authorization screen. |
| `POST` | `/oauth/device/code` | Passport | Issue device code. |
| `GET` | `/oauth/device/authorize` | Passport | Device authorization screen. |
| `POST` | `/oauth/device/authorize` | Passport | Approve device authorization. |
| `DELETE` | `/oauth/device/authorize` | Passport | Deny device authorization. |
| `GET` | `/passkeys/authentication-options` | Spatie Passkeys | Passkey authentication options. |
| `POST` | `/passkeys/authenticate` | Spatie Passkeys | Passkey authentication. |

## Broadcast Channels

These are not HTTP endpoints, but they are part of the realtime API surface.

| Channel | Guards | Purpose |
| --- | --- | --- |
| `threads.{threadUuid}` | `web`, `sanctum`, `passport` | Authorize realtime thread updates. |
| `spaces.{spaceUuid}` | `web`, `sanctum`, `passport` | Authorize realtime space updates. |
| `users.{userUuid}.notifications` | `web`, `sanctum`, `passport` | Authorize user notification stream. |

## Current Gap: Conversation Ingestion

Conversation ingestion should use Context posts, not Graph:

```text
CRM conversation JSON -> Space -> POST /spaces/{space}/posts -> agent review -> review artifact
```

The graph endpoint can link the resulting `Space`, `Thread`, and `Post` records, but it should not be overloaded as the ingestion contract.

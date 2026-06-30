# Auth

Auth endpoints establish the user, machine, and transport identity used by the rest of the system.

Most product APIs use `auth:sanctum,passport`. Some interop APIs add token ability checks such as `mcp:use`, `acp:use`, or A2A task/message abilities.

## Core Auth Endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/auth/register` | public | Register a user. |
| `POST` | `/auth/login` | public | Log in and issue auth/session state. |
| `POST` | `/auth/logout` | `auth:sanctum,passport` | Log out the authenticated user. |
| `POST` | `/auth/broadcasting` | `auth:sanctum,passport` | Authenticate private/presence broadcast channels. |
| `POST` | `/auth/robots` | `auth:sanctum,passport`, `EnsureTransportUser:subject` | Provision a robot/system user. |

## Passkeys

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/auth/passkeys` | `auth:sanctum,passport` | List passkeys for the authenticated user. |
| `POST` | `/auth/passkeys/options/register` | public | Generate passkey registration options. |
| `POST` | `/auth/passkeys` | public | Store/register a passkey. |
| `DELETE` | `/auth/passkeys/{passkey}` | `auth:sanctum,passport` | Delete a passkey. |

Package passkey routes are also present:

| Method | Path | Source | Purpose |
| --- | --- | --- | --- |
| `GET` | `/passkeys/authentication-options` | Spatie Passkeys | Passkey authentication options. |
| `POST` | `/passkeys/authenticate` | Spatie Passkeys | Passkey authentication. |

## Social Auth

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/auth/{provider}/redirect` | public | Start Socialite OAuth redirect flow. |
| `GET` | `/auth/{provider}/callback` | public | Handle Socialite OAuth callback. |

## OAuth And MCP Auth

Passport and Laravel MCP expose OAuth routes used by interop clients:

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/.well-known/oauth-authorization-server/{path?}` | MCP OAuth authorization-server metadata. |
| `GET` | `/.well-known/oauth-protected-resource/{path?}` | MCP OAuth protected-resource metadata. |
| `POST` | `/oauth/register` | Dynamic OAuth client registration for MCP. |
| `GET` | `/oauth/authorize` | OAuth authorization screen. |
| `POST` | `/oauth/authorize` | Approve OAuth authorization. |
| `DELETE` | `/oauth/authorize` | Deny OAuth authorization. |
| `POST` | `/oauth/token` | Issue OAuth token. |
| `POST` | `/oauth/token/refresh` | Refresh OAuth token. |
| `GET` | `/oauth/device` | Device authorization screen. |
| `POST` | `/oauth/device/code` | Issue device code. |
| `GET` | `/oauth/device/authorize` | Device authorization screen. |
| `POST` | `/oauth/device/authorize` | Approve device authorization. |
| `DELETE` | `/oauth/device/authorize` | Deny device authorization. |


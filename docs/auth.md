# Auth

Auth endpoints establish the user, machine, and transport identity used by the rest of the system.

Most product APIs use `auth:sanctum,passport`. Some interop APIs add token ability checks such as `mcp:use`, `acp:use`, or A2A task/message abilities.

## Core Auth Endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/auth/register` | public | Register a user. |
| `POST` | `/api/auth/login` | public | Log in and issue auth/session state. |
| `POST` | `/api/auth/logout` | `auth:sanctum,passport` | Log out the authenticated user. |
| `POST` | `/api/auth/broadcasting` | `auth:sanctum,passport` | Authenticate private/presence broadcast channels. |
| `GET` | `/api/auth/user` | `auth:sanctum,passport` | Read the current User and effective abilities. |
| `PATCH` | `/api/auth/user` | `auth:sanctum,passport` | Update the current User's public fields. |
| `POST` | `/api/users` | `auth:sanctum,passport`, `EnsureTransportUser:subject` | Provision a delegated User with scoped abilities. |

## Passkeys

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/auth/passkeys` | `auth:sanctum,passport` | List passkeys for the authenticated user. |
| `POST` | `/api/auth/passkeys/options` | public | Generate passkey registration options. |
| `POST` | `/api/auth/passkeys` | public | Store/register a passkey. |
| `DELETE` | `/api/auth/passkeys/{passkey}` | `auth:sanctum,passport` | Delete a passkey. |

Package passkey routes are also present:

| Method | Path | Source | Purpose |
| --- | --- | --- | --- |
| `GET` | `/passkeys/authentication-options` | Spatie Passkeys | Passkey authentication options. |
| `POST` | `/passkeys/authenticate` | Spatie Passkeys | Passkey authentication. |

## External Identity Protocols

Figurate does not ship vendor-specific social login providers. External identity integrations should use protocol adapters, such as OAuth 2.0 or OpenID Connect, while mapping the resulting subject to the generic Identity model.

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

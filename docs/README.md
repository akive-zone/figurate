# Figurate Docs

These docs are grouped around the main runtime surfaces of the system.

## Groups

- [Auth](./auth.md): identity, login, passkeys, robot users, broadcast auth, OAuth/package auth routes.
- [Context](./context.md): the engine of the system. Covers channels, spaces, threads, and posts.
- [Graph](./graph.md): explicit edges between existing context nodes.
- [Form](./form.md): generic authenticated form submission.

## Endpoint Inventory

- [Endpoint Catalog](./endpoints.md): full HTTP route inventory, including interop, web UI, control panel, and framework/package routes.

## Current Product Gap

The next major missing surface is conversation ingestion:

```text
CRM conversation JSON -> Fig context -> agent review -> review artifact
```

That should become a Context ingestion flow, not a Graph endpoint. Graph can link the resulting context artifacts after they exist.


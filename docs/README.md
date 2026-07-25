# Figurate Docs

Figurate is an API-first coordination platform for third-party systems. These docs describe the contracts used to ingest context, coordinate agents and humans, track work, and deliver results.

The web workspace and control panel are first-party clients of these contracts. Remote is the primary deployment target; Device provides the same API-oriented capabilities for local/private operation.

## Groups

- [Endpoint Catalog](./endpoints.md): the HTTP API and protocol entrypoints.
- [Auth](./auth.md): identity, login, passkeys, robot users, broadcast auth, OAuth/package auth routes.
- [Context](./context.md): the engine of the system. Covers channels, spaces, threads, and posts.
- [Graph](./graph.md): explicit edges between existing context nodes.
- [Form](./form.md): generic authenticated form submission.
- [Fulfillment Reference Scenario](./fulfillment-scenario.md): a future test case for proving an application built on Figurate.

## Integration Model

```text
Third-party system
    -> HTTP / webhook / WebSocket / MCP / A2A / ACP
    -> channel / route / address
    -> space / thread / post
    -> agent or human coordination
    -> task / event / callback
```

## Current Product Gap

The next major missing surface is conversation ingestion:

```text
CRM conversation JSON -> Fig context -> agent review -> review artifact
```

That should become a Context ingestion flow, not a Graph endpoint. Graph can link the resulting context artifacts after they exist.

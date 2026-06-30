# Graph

The graph endpoint manages explicit edges between existing Fig nodes. It does not ingest whole conversations or create context from an external system payload.

Use it when a `Space`, `Thread`, or `Post` already exists and you need to link it to another existing node.

## Routes

All graph routes require `auth:sanctum,passport`.

```text
GET  /graph/edges
POST /graph/edges
```

## Supported Nodes

The current graph API supports these node types:

- `space`: identified by `uuid`
- `thread`: identified by `uuid`
- `post`: identified by `ulid`

It does not currently support external nodes such as CRM conversation ids, customers, tickets, accounts, or files directly. Those must first be represented as Fig context, usually through posts and relations.

## Supported Edge Types

```text
related_to
references
depends_on
blocks
derived_from
child_of
```

## Create an Edge

```http
POST /graph/edges
```

Example:

```json
{
  "source_type": "space",
  "source_id": "space-uuid",
  "target_type": "thread",
  "target_id": "thread-uuid",
  "edge_type": "references",
  "purpose": "Space references the conversation review thread."
}
```

When the source is a `post`, the edge is stored as a post relation. Post-backed edges use the relation role as the edge type and do not store `purpose`.

## Query Edges

```http
GET /graph/edges
```

Example:

```text
/graph/edges?node_type=space&node_id=space-uuid&direction=outgoing&depth=3
```

Query parameters:

| Parameter | Required | Values |
| --- | --- | --- |
| `node_type` | yes | `space`, `thread`, `post` |
| `node_id` | yes | `uuid` for spaces/threads, `ulid` for posts |
| `direction` | no | `outgoing`, `incoming`, `both` |
| `edge_type` | no | any supported edge type |
| `target_type` | no | `space`, `thread`, `post` |
| `depth` | no | integer from `1` to `5` |
| `limit` | no | integer from `1` to `100` |

## What This Endpoint Is Not

The graph endpoint is not the right surface for submitting a CRM conversation packet.

That flow needs a Context ingestion path that can create or update Fig context first:

1. Resolve or create a `Space` for the CRM conversation or account.
2. Resolve or create a `Thread` for the review/workstream.
3. Store the source conversation as one or more `Post` records.
4. Link the posts, thread, and space using graph edges.
5. Queue or invoke the reviewing agent.
6. Store the review as a new post and optionally callback to the CRM.

The graph endpoint can still be used inside that ingestion flow, but it should not be the ingestion contract itself.


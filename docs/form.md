# Form

The form endpoint is a generic authenticated submission surface.

| Method | Path | Auth | Name | Purpose |
| --- | --- | --- | --- | --- |
| `POST` | `/form` | `auth:sanctum,passport` | `api.form.store` | Generic form submission endpoint. |

This endpoint is separate from Context. If a submission is meant to create durable work context, it should eventually be routed through a Context-specific ingestion flow.


@api @domain-agnostic
Feature: Build different third-party products on the same Figurate model
  In order to keep Figurate independent of any one industry
  As a third-party product builder
  I want domain records to remain payloads and artifacts on the common work graph

  Background:
    Given an API subject exists
    And the client "domain-overlay-service" has these abilities:
      | nodes:read  |
      | nodes:write |

  Scenario Outline: Preserve an external record without adding a domain-specific endpoint
    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "space",
        "attributes": {
          "status": "active"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "workspace_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "space",
          "id": "{{workspace_id}}"
        },
        "attributes": {
          "title": "<workstream>",
          "purpose": "<purpose>",
          "phase": "intake"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "workstream_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{workstream_id}}"
        },
        "attributes": {
          "post_type": "<artifact_type>",
          "status": "received",
          "payload": {
            "source": {
              "system": "<system>",
              "external_id": "<external_id>"
            }
          }
        }
      }
      """
    Then the response status should be 201
    And the response field "data.attributes.post_type" should equal "<artifact_type>"
    And the response field "data.attributes.payload.source.system" should equal "<system>"
    And the response field "data.attributes.payload.source.external_id" should equal "<external_id>"

    Examples:
      | workstream            | purpose             | artifact_type             | system              | external_id |
      | Appliance repair      | service_fulfillment  | service.requested         | marketplace         | JOB-2041    |
      | Homepage publication  | content_review       | content.change.proposed   | cms                 | REV-551     |
      | Invoice exception     | financial_review     | invoice.exception         | erp                 | INV-778     |
      | Operations follow-up  | conversation_capture | conversation.action_found | collaboration-suite | CHAT-992    |

  Scenario: Extract actionable work beneath a conversation artifact
    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "space",
        "attributes": {
          "status": "active"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "conversation_space_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "space",
          "id": "{{conversation_space_id}}"
        },
        "attributes": {
          "title": "Finance operations chat",
          "purpose": "conversation_capture"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "conversation_thread_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{conversation_thread_id}}"
        },
        "attributes": {
          "post_type": "conversation.transcript",
          "text": "Please investigate why invoice INV-778 failed approval.",
          "payload": {
            "source": {
              "system": "collaboration-suite",
              "external_id": "CHAT-992"
            }
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "transcript_post_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "post",
          "id": "{{transcript_post_id}}"
        },
        "attributes": {
          "post_type": "action.required",
          "status": "open",
          "text": "Investigate the approval failure for invoice INV-778.",
          "payload": {
            "owner": "finance-operations"
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "action_post_id"

    When the client sends a "GET" request to "/api/posts/{{transcript_post_id}}/nodes"
    Then the response status should be 200
    And the response field "data.0.id" should equal "{{action_post_id}}"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "post",
          "id": "{{transcript_post_id}}"
        },
        "attributes": {
          "title": "Invalid child workstream"
        }
      }
      """
    Then the response status should be 422

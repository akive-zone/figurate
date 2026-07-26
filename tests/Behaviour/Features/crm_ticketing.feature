@api @crm @ticketing
Feature: Coordinate a CRM support ticket through Figurate
  In order to add durable agent-assisted work to an existing CRM
  As a CRM integration
  I want to track a ticket without requiring CRM-specific Figurate endpoints

  Background:
    Given an API subject exists
    And the client "crm-ticketing-service" has these abilities:
      | nodes:read  |
      | nodes:write |
      | edges:read  |
      | edges:write |

  Scenario: Carry a support ticket from intake to resolution and reopening
    Given the next request has header "Idempotency-Key" with value "crm-case-8842"
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
    And I remember response field "data.id" as "case_space_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "space",
          "id": "{{case_space_id}}"
        },
        "attributes": {
          "title": "Ticket CRM-8842",
          "purpose": "support_case",
          "phase": "intake",
          "status": "open"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "ticket_thread_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{ticket_thread_id}}"
        },
        "attributes": {
          "post_type": "support.ticket.opened",
          "status": "open",
          "text": "Customer cannot download the paid invoice.",
          "payload": {
            "source": {
              "system": "crm",
              "external_id": "CRM-8842"
            },
            "customer": {
              "external_id": "CUSTOMER-91"
            },
            "priority": "high"
          }
        }
      }
      """
    Then the response status should be 201
    And the response field "data.attributes.payload.source.external_id" should equal "CRM-8842"
    And I remember response field "data.id" as "ticket_post_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{ticket_thread_id}}"
        },
        "attributes": {
          "post_type": "support.evidence",
          "text": "Invoice INV-109 is paid but its PDF export is unavailable.",
          "payload": {
            "invoice_id": "INV-109",
            "payment_status": "paid"
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "evidence_post_id"

    When the client sends a "POST" request to "/api/edges" with JSON:
      """
      {
        "source_type": "post",
        "source_id": "{{ticket_post_id}}",
        "target_type": "post",
        "target_id": "{{evidence_post_id}}",
        "edge_type": "references"
      }
      """
    Then the response status should be 201

    When the client sends a "PATCH" request to "/api/nodes/post/{{ticket_post_id}}" with JSON:
      """
      {
        "attributes": {
          "status": "triaged",
          "tag": "billing",
          "payload": {
            "source": {
              "system": "crm",
              "external_id": "CRM-8842"
            },
            "customer": {
              "external_id": "CUSTOMER-91"
            },
            "priority": "high",
            "owner": "billing-operations"
          }
        }
      }
      """
    Then the response status should be 200
    And the response field "data.attributes.status" should equal "triaged"
    And the response field "data.attributes.payload.owner" should equal "billing-operations"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{ticket_thread_id}}"
        },
        "attributes": {
          "post_type": "support.resolution",
          "status": "proposed",
          "text": "Regenerate the invoice PDF and restore its download link.",
          "payload": {
            "outcome": "invoice_export_regenerated"
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "resolution_post_id"

    When the client sends a "POST" request to "/api/edges" with JSON:
      """
      {
        "source_type": "post",
        "source_id": "{{resolution_post_id}}",
        "target_type": "post",
        "target_id": "{{ticket_post_id}}",
        "edge_type": "derived_from"
      }
      """
    Then the response status should be 201

    When the client sends a "PATCH" request to "/api/nodes/post/{{resolution_post_id}}" with JSON:
      """
      {
        "attributes": {
          "status": "approved"
        }
      }
      """
    Then the response status should be 200
    And the response field "data.attributes.status" should equal "approved"

    When the client sends a "PATCH" request to "/api/nodes/post/{{ticket_post_id}}" with JSON:
      """
      {
        "attributes": {
          "status": "resolved"
        }
      }
      """
    Then the response status should be 200
    And the response field "data.attributes.status" should equal "resolved"

    When the client sends a "GET" request to "/api/edges?node_type=post&node_id={{resolution_post_id}}&direction=outgoing&edge_type=derived_from"
    Then the response status should be 200
    And the response field "data.0.target.id" should equal "{{ticket_post_id}}"
    And the response field "meta.edge_count" should equal 1

    When the client sends a "PATCH" request to "/api/nodes/post/{{ticket_post_id}}" with JSON:
      """
      {
        "attributes": {
          "status": "reopened"
        }
      }
      """
    Then the response status should be 200
    And the response field "data.attributes.status" should equal "reopened"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "thread",
          "id": "{{ticket_thread_id}}"
        },
        "attributes": {
          "title": "CRM-8842 escalation",
          "purpose": "specialist_review",
          "phase": "reopened",
          "status": "open"
        }
      }
      """
    Then the response status should be 201
    And the response field "data.attributes.phase" should equal "reopened"
    And I remember response field "data.id" as "escalation_thread_id"

    When the client sends a "GET" request to "/api/threads/{{ticket_thread_id}}/nodes"
    Then the response status should be 200
    And the response list "data.*.id" should contain "{{escalation_thread_id}}"

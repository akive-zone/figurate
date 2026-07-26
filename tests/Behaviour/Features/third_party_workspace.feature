@api
Feature: Build a workspace through Figurate
  In order to add structured collaboration to another product
  As a third-party service
  I want to compose spaces, threads, posts, and edges through the API

  Background:
    Given an API subject exists

  Scenario: Build a contract review workspace using public identifiers
    Given the client "contract-service" has these abilities:
      | nodes:read  |
      | nodes:write |
      | edges:read  |
      | edges:write |
    And the next request has header "Idempotency-Key" with value "review-workspace"
    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "space",
        "attributes": {
          "status": "collecting"
        }
      }
      """
    Then the response status should be 201
    And the response field "data.type" should equal "space"
    And the response field "data.attributes.status" should equal "collecting"
    And I remember response field "data.id" as "space_id"

    Given the next request has header "Idempotency-Key" with value "review-workspace"
    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "space",
        "attributes": {
          "status": "collecting"
        }
      }
      """
    Then the response status should be 201
    And the response header "Idempotency-Replayed" should equal "true"
    And the response field "data.id" should equal "{{space_id}}"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "space",
          "id": "{{space_id}}"
        },
        "attributes": {
          "title": "Contract review",
          "purpose": "document_review",
          "phase": "intake"
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "thread_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{thread_id}}"
        },
        "attributes": {
          "post_type": "review.requested",
          "text": "Identify risky termination clauses.",
          "payload": {
            "external_case_id": "CASE-42"
          },
          "meta": {
            "source": "contract-service"
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "prompt_id"

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "post",
        "parent": {
          "type": "thread",
          "id": "{{thread_id}}"
        },
        "attributes": {
          "post_type": "document.reference",
          "text": "Master services agreement",
          "payload": {
            "document_id": "DOC-7"
          }
        }
      }
      """
    Then the response status should be 201
    And I remember response field "data.id" as "evidence_id"

    When the client sends a "POST" request to "/api/edges" with JSON:
      """
      {
        "source_type": "post",
        "source_id": "{{prompt_id}}",
        "target_type": "post",
        "target_id": "{{evidence_id}}",
        "edge_type": "references"
      }
      """
    Then the response status should be 201
    And the response field "data.source.id" should equal "{{prompt_id}}"
    And the response field "data.target.id" should equal "{{evidence_id}}"
    And I remember response field "data.id" as "edge_id"

    When the client sends a "GET" request to "/api/spaces/{{space_id}}/nodes?per_page=1"
    Then the response status should be 200
    And the response field "data.0.id" should equal "{{thread_id}}"
    And the response field "meta.per_page" should equal 1

    When the client sends a "GET" request to "/api/edges?node_type=post&node_id={{prompt_id}}&direction=outgoing"
    Then the response status should be 200
    And the response field "data.0.id" should equal "{{edge_id}}"
    And the response field "data.0.target.id" should equal "{{evidence_id}}"

  Scenario: A read-only client cannot mutate the workspace
    Given an accessible space exists as "space_id"
    And the client "reporting-service" has these abilities:
      | nodes:read |
    When the client sends a "GET" request to "/api/spaces/{{space_id}}"
    Then the response status should be 200

    When the client sends a "POST" request to "/api/nodes" with JSON:
      """
      {
        "type": "thread",
        "parent": {
          "type": "space",
          "id": "{{space_id}}"
        },
        "attributes": {
          "title": "Forbidden mutation"
        }
      }
      """
    Then the response status should be 403
    And the response field "required_ability" should equal "nodes:write"

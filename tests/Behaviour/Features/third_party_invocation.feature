@api @invocation @ai-pipeline
Feature: Follow work submitted to Figurate
  In order to consume agent processing from another product
  As a third-party service
  I want to submit work and follow its invocation through the API

  Scenario: Follow submitted work by invocation identifier
    Given an API subject exists
    And an accessible automated thread exists
    And the deterministic AI provider responds with:
      """
      Termination clause requires thirty days notice.
      """
    And the client "workflow-service" has these abilities:
      | forms:submit     |
      | invocations:read |
      | nodes:read       |
    When the client sends a "POST" request to "/api/form" with JSON:
      """
      {
        "body": {
          "type": "post",
          "parent": {
            "type": "thread",
            "id": "{{thread_id}}"
          },
          "attributes": {
            "post_type": "message",
            "text": "Review contract CASE-42 and return the risks."
          }
        }
      }
      """
    Then the response status should be 202
    And the response field "space" should equal "{{space_id}}"
    And the response field "thread" should equal "{{thread_id}}"
    And the response field "pending" should equal true
    And I remember response field "post_id" as "prompt_id"

    When the client sends a "GET" request to "/api/posts/{{prompt_id}}"
    Then the response status should be 200
    And the response field "data.invocation.invocation_id" should not be empty
    And the response field "data.invocation.status" should equal "completed"
    And I remember response field "data.invocation.invocation_id" as "invocation_id"

    When the client sends a "GET" request to "/api/threads/{{thread_id}}/nodes"
    Then the response status should be 200
    And the response list "data.*.attributes.text" should contain "Termination clause requires thirty days notice."
    And I remember field "id" from the response item in "data" where "attributes.text" equals "Termination clause requires thirty days notice." as "assistant_post_id"

    When the client sends a "GET" request to "/api/posts/{{assistant_post_id}}"
    Then the response status should be 200
    And the response field "data.text" should equal "Termination clause requires thirty days notice."
    And the response field "data.meta.source" should equal "agent_response"

    When the client sends a "GET" request to "/api/form/{{invocation_id}}/turns"
    Then the response status should be 200
    And the response field "invocation_id" should equal "{{invocation_id}}"
    And the response field "data.0.agent_message_id" should not be empty
    And the response field "data.0.content" should equal "Termination clause requires thirty days notice."
    And the response field "data.0.trace_id" should equal "{{invocation_id}}"
    And the response field "data.0.invocable.type" should equal "post"
    And the response field "data.0.invocable.id" should equal "{{prompt_id}}"

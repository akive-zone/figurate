@api @invocation
Feature: Follow work submitted to Figurate
  In order to consume agent processing from another product
  As a third-party service
  I want to submit work and follow its invocation through the API

  Scenario: Follow submitted work by invocation identifier
    Given an API subject exists
    And an accessible automated thread exists
    And agent execution can be queued
    And the client "workflow-service" has these abilities:
      | forms:submit     |
      | invocations:read |
      | nodes:read       |
    When the client sends a "POST" request to "/api/form" with JSON:
      """
      {
        "space": "{{space_id}}",
        "thread": "{{thread_id}}",
        "content": {
          "text": "Review contract CASE-42 and return the risks."
        }
      }
      """
    Then the response status should be 202
    And the response field "space" should equal "{{space_id}}"
    And the response field "thread" should equal "{{thread_id}}"
    And the response field "pending" should equal true
    And I remember response field "post_id" as "prompt_id"

    Given the invocation "contract-review-invocation" completed for post "{{prompt_id}}"
    When the client sends a "GET" request to "/api/posts/{{prompt_id}}"
    Then the response status should be 200
    And the response field "data.invocation.invocation_id" should equal "{{invocation_id}}"

    When the client sends a "GET" request to "/api/form/{{invocation_id}}/turns"
    Then the response status should be 200
    And the response field "invocation_id" should equal "{{invocation_id}}"
    And the response field "data.0.agent_message_id" should equal "{{agent_message_id}}"
    And the response field "data.0.invocable.type" should equal "post"
    And the response field "data.0.invocable.id" should equal "{{prompt_id}}"

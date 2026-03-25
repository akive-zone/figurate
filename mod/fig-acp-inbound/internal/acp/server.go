package acp

import (
	"bufio"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"strconv"
	"strings"
	"sync"

	"figurate/mod/fig-acp-inbound/internal/figurate"
)

const (
	jsonrpcVersion = "2.0"

	errorCodeParse          = -32700
	errorCodeInvalidRequest = -32600
	errorCodeMethodNotFound = -32601
	errorCodeInvalidParams  = -32602
	errorCodeInternal       = -32603
	errorCodeAuthRequired   = -32001
)

type Server struct {
	logger *log.Logger
	client *figurate.Client

	writerMu sync.Mutex

	promptsMu      sync.Mutex
	activePrompts  map[string]context.CancelFunc
	activeTaskByID map[string]string
}

func NewServer(logger *log.Logger, client *figurate.Client) *Server {
	return &Server{
		logger:         logger,
		client:         client,
		activePrompts:  map[string]context.CancelFunc{},
		activeTaskByID: map[string]string{},
	}
}

func (s *Server) Serve(ctx context.Context, input io.Reader, output io.Writer) error {
	reader := bufio.NewReader(input)
	var workers sync.WaitGroup

	for {
		select {
		case <-ctx.Done():
			return nil
		default:
		}

		payload, err := readMessage(reader)
		if err != nil {
			if err == io.EOF {
				workers.Wait()
				return nil
			}

			s.logger.Printf("read error: %v", err)
			if writeErr := s.writeResponse(output, responseMessage{
				JSONRPC: jsonrpcVersion,
				Error: &responseError{
					Code:    errorCodeParse,
					Message: "Failed to read ACP message.",
					Data:    err.Error(),
				},
			}); writeErr != nil {
				return writeErr
			}

			continue
		}

		var request requestMessage
		if err := json.Unmarshal(payload, &request); err != nil {
			s.logger.Printf("decode error: %v", err)
			if writeErr := s.writeResponse(output, responseMessage{
				JSONRPC: jsonrpcVersion,
				Error: &responseError{
					Code:    errorCodeParse,
					Message: "Failed to decode JSON-RPC payload.",
					Data:    err.Error(),
				},
			}); writeErr != nil {
				return writeErr
			}

			continue
		}

		requestCopy := request
		workers.Add(1)
		go func() {
			defer workers.Done()

			response := s.handle(ctx, requestCopy, output)
			if requestCopy.ID == nil {
				return
			}

			if err := s.writeResponse(output, response); err != nil {
				s.logger.Printf("write response error: %v", err)
			}
		}()
	}
}

func (s *Server) handle(ctx context.Context, request requestMessage, output io.Writer) responseMessage {
	if request.JSONRPC != "" && request.JSONRPC != jsonrpcVersion {
		return s.errorResponse(request.ID, errorCodeInvalidRequest, "Unsupported JSON-RPC version.", request.JSONRPC)
	}

	params := map[string]any{}
	if len(request.Params) > 0 {
		if err := json.Unmarshal(request.Params, &params); err != nil {
			return s.errorResponse(request.ID, errorCodeInvalidParams, "Invalid params.", err.Error())
		}
	}

	switch request.Method {
	case "initialize":
		return s.initialize(request.ID)
	case "authenticate":
		return s.authenticate(request.ID, params)
	case "session/new":
		return s.newSession(ctx, request.ID, params)
	case "session/list":
		return s.listSessions(ctx, request.ID)
	case "session/load":
		return s.loadSession(ctx, request.ID, params)
	case "session/prompt":
		return s.promptSession(ctx, request.ID, params, output)
	case "session/cancel":
		return s.cancelSession(ctx, request.ID, params)
	case "shutdown":
		return responseMessage{
			JSONRPC: jsonrpcVersion,
			ID:      request.ID,
			Result: map[string]any{
				"ok": true,
			},
		}
	default:
		return s.errorResponse(request.ID, errorCodeMethodNotFound, "Method not found.", request.Method)
	}
}

func (s *Server) initialize(id any) responseMessage {
	config := s.client.Config()

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"protocolVersion": "0.1",
			"serverInfo": map[string]any{
				"name":    "fig-acp-inbound",
				"version": "0.1.0",
			},
			"capabilities": map[string]any{
				"authMethods": []map[string]any{
					{
						"id":          "figurate_bearer",
						"name":        "Figurate bearer token",
						"description": "Provide FIG_ACP_BASE_URL, FIG_ACP_TOKEN, FIG_ACP_USER_UUID, and optionally FIG_ACP_SPACE_ID.",
					},
				},
				"session": map[string]any{
					"new":    true,
					"list":   true,
					"load":   true,
					"prompt": true,
					"cancel": true,
				},
			},
			"metadata": map[string]any{
				"workspaceModel": "space",
				"sessionModel":   "thread",
				"defaultSpace":   config.DefaultSpaceID,
			},
		},
	}
}

func (s *Server) authenticate(id any, params map[string]any) responseMessage {
	config := s.client.Config()

	if baseURL := stringValue(params["baseUrl"]); baseURL != "" {
		config.BaseURL = baseURL
	}
	if token := stringValue(params["token"]); token != "" {
		config.Token = token
	}
	if userUUID := stringValue(params["userUuid"]); userUUID != "" {
		config.UserUUID = userUUID
	}
	if spaceID := stringValue(params["spaceId"]); spaceID != "" {
		config.DefaultSpaceID = spaceID
	}

	meta, _ := params["_meta"].(map[string]any)
	if meta != nil {
		if baseURL := stringValue(meta["baseUrl"]); baseURL != "" {
			config.BaseURL = baseURL
		}
		if token := stringValue(meta["token"]); token != "" {
			config.Token = token
		}
		if userUUID := stringValue(meta["userUuid"]); userUUID != "" {
			config.UserUUID = userUUID
		}
		if spaceID := stringValue(meta["spaceId"]); spaceID != "" {
			config.DefaultSpaceID = spaceID
		}
	}

	s.client.Configure(config)

	if err := s.client.Ready(); err != nil {
		return s.errorResponse(id, errorCodeAuthRequired, "Authentication is incomplete.", err.Error())
	}

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"authenticated": true,
			"config": map[string]any{
				"baseUrl":  config.BaseURL,
				"userUuid": config.UserUUID,
				"spaceId":  config.DefaultSpaceID,
			},
		},
	}
}

func (s *Server) newSession(ctx context.Context, id any, params map[string]any) responseMessage {
	if err := s.client.Ready(); err != nil {
		return s.errorResponse(id, errorCodeAuthRequired, "Authentication is required.", err.Error())
	}

	spaceID := firstNonEmpty(
		stringValue(params["spaceId"]),
		stringValue(params["workspaceId"]),
		stringValue(params["space_id"]),
	)
	title := firstNonEmpty(
		stringValue(params["title"]),
		stringValue(params["name"]),
		stringValue(params["description"]),
	)

	session, err := s.client.CreateSession(ctx, spaceID, title)
	if err != nil {
		return s.errorResponse(id, errorCodeInternal, "Failed to create Figurate thread.", err.Error())
	}

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"sessionId": session.ID,
			"session": map[string]any{
				"id":     session.ID,
				"title":  session.Title,
				"status": session.Status,
			},
			"workspace": map[string]any{
				"id":   session.SpaceID,
				"type": "space",
			},
			"metadata": session.Metadata,
		},
	}
}

func (s *Server) listSessions(ctx context.Context, id any) responseMessage {
	if err := s.client.Ready(); err != nil {
		return s.errorResponse(id, errorCodeAuthRequired, "Authentication is required.", err.Error())
	}

	sessions, err := s.client.ListSessions(ctx)
	if err != nil {
		return s.errorResponse(id, errorCodeInternal, "Failed to list Figurate sessions.", err.Error())
	}

	items := make([]map[string]any, 0, len(sessions))
	for _, session := range sessions {
		items = append(items, map[string]any{
			"id":        session.ID,
			"title":     session.Title,
			"status":    session.Status,
			"workspace": map[string]any{"id": session.SpaceID, "type": "space", "name": session.Space},
			"metadata":  session.Metadata,
		})
	}

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"sessions": items,
		},
	}
}

func (s *Server) loadSession(ctx context.Context, id any, params map[string]any) responseMessage {
	if err := s.client.Ready(); err != nil {
		return s.errorResponse(id, errorCodeAuthRequired, "Authentication is required.", err.Error())
	}

	sessionID := firstNonEmpty(stringValue(params["sessionId"]), stringValue(params["session_id"]), stringValue(params["id"]))
	if sessionID == "" {
		return s.errorResponse(id, errorCodeInvalidParams, "sessionId is required.", nil)
	}

	session, err := s.client.LoadSession(ctx, sessionID)
	if err != nil {
		return s.errorResponse(id, errorCodeInternal, "Failed to load Figurate thread.", err.Error())
	}

	messages := make([]map[string]any, 0, len(session.Messages))
	for _, message := range session.Messages {
		messages = append(messages, map[string]any{
			"id":   message.ID,
			"role": message.Role,
			"content": []map[string]any{
				{
					"type": "text",
					"text": message.Text,
				},
			},
			"metadata": map[string]any{
				"createdAt": message.CreatedAt,
				"source":    message.Metadata["source"],
			},
		})
	}

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"sessionId": session.ID,
			"session": map[string]any{
				"id":     session.ID,
				"title":  session.Title,
				"status": session.Status,
			},
			"workspace": map[string]any{
				"id":   session.SpaceID,
				"type": "space",
			},
			"messages": messages,
			"metadata": session.Metadata,
		},
	}
}

func (s *Server) promptSession(ctx context.Context, id any, params map[string]any, output io.Writer) responseMessage {
	if err := s.client.Ready(); err != nil {
		return s.errorResponse(id, errorCodeAuthRequired, "Authentication is required.", err.Error())
	}

	sessionID := firstNonEmpty(stringValue(params["sessionId"]), stringValue(params["session_id"]), stringValue(params["id"]))
	if sessionID == "" {
		return s.errorResponse(id, errorCodeInvalidParams, "sessionId is required.", nil)
	}

	prompt := resolvePromptText(params)
	if prompt == "" {
		return s.errorResponse(id, errorCodeInvalidParams, "Prompt text is required.", nil)
	}

	spaceID := firstNonEmpty(stringValue(params["spaceId"]), stringValue(params["workspaceId"]), stringValue(params["space_id"]))
	promptCtx, cancel := context.WithCancel(ctx)
	s.setPrompt(sessionID, "", cancel)
	defer s.clearPrompt(sessionID)

	_ = s.writeNotification(output, "session/update", map[string]any{
		"sessionId": sessionID,
		"state":     "working",
		"message":   "Prompt submitted to Figurate.",
	})

	handle, err := s.client.BeginPrompt(promptCtx, spaceID, sessionID, prompt)
	if err != nil {
		return s.errorResponse(id, errorCodeInternal, "Failed to submit Figurate prompt.", err.Error())
	}
	s.setPrompt(sessionID, handle.TaskID, cancel)

	_ = s.writeNotification(output, "session/update", map[string]any{
		"sessionId": sessionID,
		"state":     "submitted",
		"taskId":    handle.TaskID,
		"message":   "Figurate accepted the prompt.",
	})

	result, err := s.client.AwaitPrompt(promptCtx, handle)
	if err != nil {
		return s.errorResponse(id, errorCodeInternal, "Failed to complete Figurate prompt.", err.Error())
	}

	_ = s.writeNotification(output, "session/update", map[string]any{
		"sessionId": sessionID,
		"state":     result.State,
		"taskId":    result.TaskID,
		"message":   "Figurate prompt completed.",
	})

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"sessionId":  result.SessionID,
			"stopReason": result.StopReason,
			"message": map[string]any{
				"role": "assistant",
				"content": []map[string]any{
					{
						"type": "text",
						"text": result.Text,
					},
				},
			},
			"metadata": map[string]any{
				"spaceId": result.SpaceID,
				"taskId":  result.TaskID,
				"state":   result.State,
				"raw":     result.Metadata,
			},
		},
	}
}

func (s *Server) cancelSession(ctx context.Context, id any, params map[string]any) responseMessage {
	sessionID := firstNonEmpty(stringValue(params["sessionId"]), stringValue(params["session_id"]), stringValue(params["id"]))
	if sessionID == "" {
		return s.errorResponse(id, errorCodeInvalidParams, "sessionId is required.", nil)
	}

	cancel, taskID := s.promptState(sessionID)
	if cancel != nil {
		cancel()
	}

	if taskID != "" {
		if err := s.client.CancelTask(ctx, taskID); err != nil {
			return s.errorResponse(id, errorCodeInternal, "Failed to cancel Figurate task.", err.Error())
		}
	}

	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Result: map[string]any{
			"sessionId": sessionID,
			"cancelled": true,
			"taskId":    taskID,
		},
	}
}

func (s *Server) promptState(sessionID string) (context.CancelFunc, string) {
	s.promptsMu.Lock()
	defer s.promptsMu.Unlock()

	return s.activePrompts[sessionID], s.activeTaskByID[sessionID]
}

func (s *Server) setPrompt(sessionID string, taskID string, cancel context.CancelFunc) {
	s.promptsMu.Lock()
	defer s.promptsMu.Unlock()

	s.activePrompts[sessionID] = cancel
	if taskID != "" {
		s.activeTaskByID[sessionID] = taskID
	}
}

func (s *Server) clearPrompt(sessionID string) {
	s.promptsMu.Lock()
	defer s.promptsMu.Unlock()

	delete(s.activePrompts, sessionID)
	delete(s.activeTaskByID, sessionID)
}

func (s *Server) errorResponse(id any, code int, message string, data any) responseMessage {
	return responseMessage{
		JSONRPC: jsonrpcVersion,
		ID:      id,
		Error: &responseError{
			Code:    code,
			Message: message,
			Data:    data,
		},
	}
}

func (s *Server) writeResponse(output io.Writer, response responseMessage) error {
	return s.write(output, response)
}

func (s *Server) writeNotification(output io.Writer, method string, params any) error {
	return s.write(output, notificationMessage{
		JSONRPC: jsonrpcVersion,
		Method:  method,
		Params:  params,
	})
}

func (s *Server) write(output io.Writer, message any) error {
	payload, err := json.Marshal(message)
	if err != nil {
		return err
	}

	s.writerMu.Lock()
	defer s.writerMu.Unlock()

	if _, err := fmt.Fprintf(output, "Content-Length: %d\r\n\r\n", len(payload)); err != nil {
		return err
	}
	if _, err := output.Write(payload); err != nil {
		return err
	}

	return nil
}

func readMessage(reader *bufio.Reader) ([]byte, error) {
	line, err := reader.ReadString('\n')
	if err != nil {
		return nil, err
	}

	trimmed := strings.TrimSpace(line)
	if trimmed == "" {
		return readMessage(reader)
	}

	if strings.HasPrefix(strings.ToLower(trimmed), "content-length:") {
		lengthValue := strings.TrimSpace(trimmed[len("Content-Length:"):])

		length, err := strconv.Atoi(lengthValue)
		if err != nil {
			return nil, fmt.Errorf("invalid content length: %w", err)
		}

		for {
			headerLine, err := reader.ReadString('\n')
			if err != nil {
				return nil, err
			}
			if strings.TrimSpace(headerLine) == "" {
				break
			}
		}

		payload := make([]byte, length)
		if _, err := io.ReadFull(reader, payload); err != nil {
			return nil, err
		}

		return payload, nil
	}

	return []byte(strings.TrimSpace(line)), nil
}

func resolvePromptText(params map[string]any) string {
	if prompt := stringValue(params["prompt"]); prompt != "" {
		return prompt
	}
	if input := stringValue(params["input"]); input != "" {
		return input
	}
	if text := stringValue(params["text"]); text != "" {
		return text
	}

	if content, ok := params["content"].([]any); ok {
		return contentBlocksToText(content)
	}

	if promptBlocks, ok := params["prompt"].([]any); ok {
		return contentBlocksToText(promptBlocks)
	}

	if message, ok := params["message"].(map[string]any); ok {
		if content, ok := message["content"].([]any); ok {
			return contentBlocksToText(content)
		}
	}

	if messages, ok := params["messages"].([]any); ok {
		for index := len(messages) - 1; index >= 0; index-- {
			entry, ok := messages[index].(map[string]any)
			if !ok {
				continue
			}

			if content, ok := entry["content"].([]any); ok {
				if text := contentBlocksToText(content); text != "" {
					return text
				}
			}
		}
	}

	return ""
}

func contentBlocksToText(blocks []any) string {
	parts := make([]string, 0)

	for _, block := range blocks {
		item, ok := block.(map[string]any)
		if !ok {
			continue
		}

		blockType := firstNonEmpty(stringValue(item["type"]), stringValue(item["kind"]))

		switch blockType {
		case "text":
			if text := stringValue(item["text"]); text != "" {
				parts = append(parts, text)
			}
		case "resource_link", "resource":
			uri := firstNonEmpty(stringValue(item["uri"]), stringValue(item["href"]))
			title := firstNonEmpty(stringValue(item["title"]), stringValue(item["name"]))
			switch {
			case title != "" && uri != "":
				parts = append(parts, title+": "+uri)
			case uri != "":
				parts = append(parts, uri)
			case title != "":
				parts = append(parts, title)
			}
		}
	}

	return strings.TrimSpace(strings.Join(parts, "\n\n"))
}

func stringValue(value any) string {
	text, ok := value.(string)
	if !ok {
		return ""
	}

	return strings.TrimSpace(text)
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return value
		}
	}

	return ""
}

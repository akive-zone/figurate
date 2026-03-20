package gateway

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type Server struct {
	config  Config
	manager *ProcessManager
	http    *http.Server
}

type GatewayRequest struct {
	Agent          string          `json:"agent"`
	JSONRPC        string          `json:"jsonrpc,omitempty"`
	ID             json.RawMessage `json:"id,omitempty"`
	Method         string          `json:"method"`
	Params         json.RawMessage `json:"params,omitempty"`
	TimeoutSeconds int             `json:"timeout_seconds,omitempty"`
}

func NewServer(config Config) (*Server, error) {
	manager := NewProcessManager(config)
	server := &Server{
		config:  config,
		manager: manager,
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/health", server.handleHealth)
	mux.HandleFunc("/rpc", server.handleRPC)

	server.http = &http.Server{
		Addr:              config.Listen,
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}

	return server, nil
}

func (server *Server) ListenAndServe() error {
	err := server.http.ListenAndServe()
	if errors.Is(err, http.ErrServerClosed) {
		return nil
	}

	return err
}

func (server *Server) Shutdown() error {
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := server.manager.Shutdown(); err != nil {
		return err
	}

	return server.http.Shutdown(ctx)
}

func (server *Server) handleHealth(writer http.ResponseWriter, request *http.Request) {
	writer.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(writer).Encode(map[string]any{
		"ok": true,
	})
}

func (server *Server) handleRPC(writer http.ResponseWriter, request *http.Request) {
	if request.Method != http.MethodPost {
		http.Error(writer, "method not allowed", http.StatusMethodNotAllowed)
		return
	}

	if !server.authorized(request) {
		http.Error(writer, "unauthorized", http.StatusUnauthorized)
		return
	}

	var payload GatewayRequest
	if err := json.NewDecoder(request.Body).Decode(&payload); err != nil {
		writeRPCError(writer, payload.ID, http.StatusBadRequest, -32700, "invalid JSON payload")
		return
	}

	payload.Agent = strings.TrimSpace(payload.Agent)
	payload.Method = strings.TrimSpace(payload.Method)
	if payload.Agent == "" || payload.Method == "" {
		writeRPCError(writer, payload.ID, http.StatusUnprocessableEntity, -32602, "agent and method are required")
		return
	}

	timeoutSeconds := payload.TimeoutSeconds
	if timeoutSeconds < 1 {
		timeoutSeconds = 45
	}
	if timeoutSeconds > 600 {
		timeoutSeconds = 600
	}

	ctx, cancel := context.WithTimeout(request.Context(), time.Duration(timeoutSeconds)*time.Second)
	defer cancel()

	response, err := server.manager.Call(ctx, payload.Agent, RPCMessage{
		JSONRPC: payload.JSONRPC,
		ID:      payload.ID,
		Method:  payload.Method,
		Params:  payload.Params,
	})
	if err != nil {
		writeRPCError(writer, payload.ID, http.StatusBadGateway, -32000, err.Error())
		return
	}

	writer.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(writer).Encode(response); err != nil {
		writeRPCError(writer, payload.ID, http.StatusInternalServerError, -32603, fmt.Sprintf("encode response: %v", err))
	}
}

func (server *Server) authorized(request *http.Request) bool {
	if strings.TrimSpace(server.config.AuthToken) == "" {
		return true
	}

	return request.Header.Get("Authorization") == "Bearer "+server.config.AuthToken
}

func writeRPCError(writer http.ResponseWriter, id json.RawMessage, status int, code int, message string) {
	writer.Header().Set("Content-Type", "application/json")
	writer.WriteHeader(status)
	_ = json.NewEncoder(writer).Encode(RPCMessage{
		JSONRPC: "2.0",
		ID:      id,
		Error: &RPCError{
			Code:    code,
			Message: message,
		},
	})
}

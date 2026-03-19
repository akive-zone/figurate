package figurate

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"sync"
	"time"
)

type Config struct {
	BaseURL          string
	Token            string
	UserUUID         string
	DefaultChannelID string
	SessionPurpose   string
	SessionTitle     string
	PollInterval     time.Duration
	PromptTimeout    time.Duration
}

type Client struct {
	httpClient *http.Client

	mu     sync.RWMutex
	config Config
}

type SessionSummary struct {
	ID        string         `json:"id"`
	Title     string         `json:"title"`
	ChannelID string         `json:"channelId"`
	Channel   string         `json:"channel,omitempty"`
	Status    string         `json:"status,omitempty"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

type ChatMessage struct {
	ID        string         `json:"id"`
	Role      string         `json:"role"`
	Text      string         `json:"text"`
	CreatedAt string         `json:"createdAt,omitempty"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

type SessionDetail struct {
	ID        string         `json:"id"`
	Title     string         `json:"title"`
	ChannelID string         `json:"channelId,omitempty"`
	Status    string         `json:"status,omitempty"`
	Messages  []ChatMessage  `json:"messages"`
	Metadata  map[string]any `json:"metadata,omitempty"`
}

type PromptResult struct {
	SessionID  string         `json:"sessionId"`
	ChannelID  string         `json:"channelId,omitempty"`
	TaskID     string         `json:"taskId,omitempty"`
	State      string         `json:"state"`
	Text       string         `json:"text,omitempty"`
	Artifacts  []any          `json:"artifacts,omitempty"`
	Metadata   map[string]any `json:"metadata,omitempty"`
	StopReason string         `json:"stopReason,omitempty"`
}

type promptHandle struct {
	SessionID string
	ChannelID string
	TaskID    string
}

func NewClient(config Config) *Client {
	return &Client{
		httpClient: &http.Client{Timeout: 30 * time.Second},
		config:     config,
	}
}

func (c *Client) Configure(config Config) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.config = config
}

func (c *Client) Config() Config {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.config
}

func (c *Client) Ready() error {
	config := c.Config()

	switch {
	case config.BaseURL == "":
		return fmt.Errorf("FIG_ACP_BASE_URL is required")
	case config.Token == "":
		return fmt.Errorf("FIG_ACP_TOKEN is required")
	case config.UserUUID == "":
		return fmt.Errorf("FIG_ACP_USER_UUID is required")
	default:
		return nil
	}
}

func (c *Client) CreateSession(ctx context.Context, channelID string, title string) (*SessionSummary, error) {
	if err := c.Ready(); err != nil {
		return nil, err
	}

	config := c.Config()
	if channelID == "" {
		channelID = config.DefaultChannelID
	}
	if channelID == "" {
		return nil, fmt.Errorf("channelId is required")
	}
	if title == "" {
		title = config.SessionTitle
	}

	body := map[string]any{
		"title":        title,
		"purpose":      config.SessionPurpose,
		"channel_uuid": channelID,
	}

	var response struct {
		Data struct {
			ID        string `json:"id"`
			Title     string `json:"title"`
			Status    string `json:"status"`
			CreatedAt string `json:"created_at"`
			Purpose   string `json:"purpose"`
			Channel   struct {
				ID string `json:"id"`
			} `json:"channel"`
		} `json:"data"`
	}

	if err := c.doJSON(ctx, http.MethodPost, "/api/acp/sessions", body, &response); err != nil {
		return nil, err
	}

	return &SessionSummary{
		ID:        response.Data.ID,
		Title:     response.Data.Title,
		ChannelID: coalesce(response.Data.Channel.ID, channelID),
		Status:    response.Data.Status,
		Metadata: map[string]any{
			"createdAt": response.Data.CreatedAt,
			"purpose":   response.Data.Purpose,
		},
	}, nil
}

func (c *Client) ListSessions(ctx context.Context) ([]SessionSummary, error) {
	if err := c.Ready(); err != nil {
		return nil, err
	}

	var response struct {
		Data []struct {
			ID      string `json:"id"`
			Title   string `json:"title"`
			Purpose string `json:"purpose"`
			Status  string `json:"status"`
			Channel struct {
				ID string `json:"id"`
			} `json:"channel"`
			LastMessageAt string `json:"last_message_at"`
		} `json:"data"`
	}

	if err := c.doJSON(ctx, http.MethodGet, "/api/acp/sessions", nil, &response); err != nil {
		return nil, err
	}

	sessions := make([]SessionSummary, 0, len(response.Data))
	for _, session := range response.Data {
		sessions = append(sessions, SessionSummary{
			ID:        session.ID,
			Title:     session.Title,
			ChannelID: session.Channel.ID,
			Status:    session.Status,
			Metadata: map[string]any{
				"purpose":       session.Purpose,
				"lastMessageAt": session.LastMessageAt,
			},
		})
	}

	return sessions, nil
}

func (c *Client) LoadSession(ctx context.Context, sessionID string) (*SessionDetail, error) {
	if err := c.Ready(); err != nil {
		return nil, err
	}
	if sessionID == "" {
		return nil, fmt.Errorf("sessionId is required")
	}

	var response struct {
		Data struct {
			ID      string `json:"id"`
			Title   string `json:"title"`
			Status  string `json:"status"`
			Channel struct {
				ID string `json:"id"`
			} `json:"channel"`
			Messages []struct {
				ID        any    `json:"id"`
				Role      string `json:"role"`
				Text      string `json:"text"`
				Source    string `json:"source"`
				CreatedAt string `json:"created_at"`
			} `json:"messages"`
			Purpose string `json:"purpose"`
		} `json:"data"`
	}

	path := fmt.Sprintf("/api/acp/sessions/%s", url.PathEscape(sessionID))
	if err := c.doJSON(ctx, http.MethodGet, path, nil, &response); err != nil {
		return nil, err
	}

	messages := make([]ChatMessage, 0, len(response.Data.Messages))
	for _, message := range response.Data.Messages {
		messages = append(messages, ChatMessage{
			ID:        fmt.Sprintf("%v", message.ID),
			Role:      message.Role,
			Text:      message.Text,
			CreatedAt: message.CreatedAt,
			Metadata: map[string]any{
				"source": message.Source,
			},
		})
	}

	metadata := map[string]any{}
	metadata["purpose"] = response.Data.Purpose

	return &SessionDetail{
		ID:        sessionID,
		Title:     response.Data.Title,
		ChannelID: response.Data.Channel.ID,
		Status:    response.Data.Status,
		Messages:  messages,
		Metadata:  metadata,
	}, nil
}

func (c *Client) Prompt(ctx context.Context, channelID string, sessionID string, text string) (*PromptResult, error) {
	handle, err := c.BeginPrompt(ctx, channelID, sessionID, text)
	if err != nil {
		return nil, err
	}

	return c.AwaitPrompt(ctx, handle)
}

func (c *Client) BeginPrompt(ctx context.Context, channelID string, sessionID string, text string) (*promptHandle, error) {
	if err := c.Ready(); err != nil {
		return nil, err
	}
	if sessionID == "" {
		return nil, fmt.Errorf("sessionId is required")
	}

	return c.beginACPPrompt(ctx, channelID, sessionID, text)
}

func (c *Client) AwaitPrompt(ctx context.Context, handle *promptHandle) (*PromptResult, error) {
	if handle == nil {
		return nil, fmt.Errorf("prompt handle is required")
	}

	return c.awaitACPPrompt(ctx, handle)
}

func (c *Client) CancelTask(ctx context.Context, taskID string) error {
	if err := c.Ready(); err != nil {
		return err
	}
	if taskID == "" {
		return fmt.Errorf("taskId is required")
	}

	path := fmt.Sprintf("/api/acp/tasks/%s/cancel", url.PathEscape(taskID))
	if err := c.doJSON(ctx, http.MethodPost, path, map[string]any{}, nil); err != nil {
		return err
	}

	return nil
}

func (c *Client) beginACPPrompt(ctx context.Context, channelID string, sessionID string, text string) (*promptHandle, error) {
	config := c.Config()
	if channelID == "" {
		channelID = config.DefaultChannelID
	}

	body := map[string]any{
		"text": text,
	}

	if channelID != "" {
		body["channel_uuid"] = channelID
	}

	var response struct {
		Data struct {
			ID        string `json:"id"`
			SessionID string `json:"session_id"`
			ChannelID string `json:"channel_id"`
			State     string `json:"state"`
		} `json:"data"`
	}

	path := fmt.Sprintf("/api/acp/sessions/%s/prompt", url.PathEscape(sessionID))
	if err := c.doJSON(ctx, http.MethodPost, path, body, &response); err != nil {
		return nil, err
	}

	return &promptHandle{
		SessionID: coalesce(response.Data.SessionID, sessionID),
		ChannelID: coalesce(response.Data.ChannelID, channelID),
		TaskID:    response.Data.ID,
	}, nil
}

func (c *Client) awaitACPPrompt(ctx context.Context, handle *promptHandle) (*PromptResult, error) {
	config := c.Config()
	pollCtx, cancel := context.WithTimeout(ctx, config.PromptTimeout)
	defer cancel()

	result := &PromptResult{
		SessionID: handle.SessionID,
		ChannelID: handle.ChannelID,
		TaskID:    handle.TaskID,
		State:     "submitted",
	}

	ticker := time.NewTicker(config.PollInterval)
	defer ticker.Stop()

	for {
		task, err := c.GetTask(pollCtx, handle.TaskID)
		if err != nil {
			return nil, err
		}

		result.State = task.State
		result.Artifacts = task.Artifacts
		result.Metadata = task.Metadata
		result.Text = artifactText(task.Artifacts)

		switch task.State {
		case "completed":
			result.StopReason = "end_turn"
			return result, nil
		case "failed":
			result.StopReason = "error"
			if result.Text == "" {
				result.Text = "Figurate task failed."
			}
			return result, nil
		case "canceled":
			result.StopReason = "cancelled"
			return result, nil
		}

		select {
		case <-pollCtx.Done():
			result.StopReason = "max_turn_requests"
			if result.Text == "" {
				result.Text = "Figurate prompt timed out while waiting for completion."
			}
			return result, nil
		case <-ticker.C:
		}
	}
}

type taskSnapshot struct {
	State     string
	Artifacts []any
	Metadata  map[string]any
}

func (c *Client) GetTask(ctx context.Context, taskID string) (*taskSnapshot, error) {
	var response struct {
		Data struct {
			ID          string `json:"id"`
			State       string `json:"state"`
			SessionID   string `json:"session_id"`
			ChannelID   string `json:"channel_id"`
			Artifacts   []any  `json:"artifacts"`
			Invocations []any  `json:"invocations"`
		} `json:"data"`
	}

	path := fmt.Sprintf("/api/acp/tasks/%s", url.PathEscape(taskID))
	if err := c.doJSON(ctx, http.MethodGet, path, nil, &response); err != nil {
		return nil, err
	}

	return &taskSnapshot{
		State:     response.Data.State,
		Artifacts: response.Data.Artifacts,
		Metadata: map[string]any{
			"id":          response.Data.ID,
			"session_id":  response.Data.SessionID,
			"channel_id":  response.Data.ChannelID,
			"invocations": response.Data.Invocations,
		},
	}, nil
}

func (c *Client) doJSON(ctx context.Context, method string, path string, payload any, target any) error {
	config := c.Config()
	baseURL := strings.TrimRight(config.BaseURL, "/")
	if baseURL == "" {
		return fmt.Errorf("base URL is required")
	}

	fullURL := baseURL + path

	var body io.Reader
	if payload != nil {
		encoded, err := json.Marshal(payload)
		if err != nil {
			return fmt.Errorf("marshal request: %w", err)
		}
		body = bytes.NewReader(encoded)
	}

	request, err := http.NewRequestWithContext(ctx, method, fullURL, body)
	if err != nil {
		return fmt.Errorf("build request: %w", err)
	}

	request.Header.Set("Accept", "application/json")
	request.Header.Set("Authorization", "Bearer "+config.Token)
	if payload != nil {
		request.Header.Set("Content-Type", "application/json")
	}

	response, err := c.httpClient.Do(request)
	if err != nil {
		return fmt.Errorf("request %s %s: %w", method, fullURL, err)
	}
	defer response.Body.Close()

	responseBody, err := io.ReadAll(response.Body)
	if err != nil {
		return fmt.Errorf("read response: %w", err)
	}

	if response.StatusCode >= 400 {
		return fmt.Errorf("request failed with status %d: %s", response.StatusCode, strings.TrimSpace(string(responseBody)))
	}

	if target == nil || len(responseBody) == 0 {
		return nil
	}

	if err := json.Unmarshal(responseBody, target); err != nil {
		return fmt.Errorf("decode response: %w", err)
	}

	return nil
}

func coalesce(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return value
		}
	}

	return ""
}

func artifactText(artifacts []any) string {
	textParts := make([]string, 0)

	for _, artifact := range artifacts {
		artifactMap, ok := artifact.(map[string]any)
		if !ok {
			continue
		}

		textValue, ok := artifactMap["text"].(string)
		if ok && strings.TrimSpace(textValue) != "" {
			textParts = append(textParts, strings.TrimSpace(textValue))
		}
	}

	return strings.Join(textParts, "\n\n")
}

package gateway

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"sync"
)

type RPCMessage struct {
	JSONRPC string          `json:"jsonrpc,omitempty"`
	ID      json.RawMessage `json:"id,omitempty"`
	Method  string          `json:"method,omitempty"`
	Params  json.RawMessage `json:"params,omitempty"`
	Result  json.RawMessage `json:"result,omitempty"`
	Error   *RPCError       `json:"error,omitempty"`
}

type RPCError struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
}

type ProcessManager struct {
	mu      sync.Mutex
	process map[string]*AgentProcess
	config  map[string]AgentConfig
}

func NewProcessManager(cfg Config) *ProcessManager {
	return &ProcessManager{
		process: make(map[string]*AgentProcess),
		config:  cfg.Agents,
	}
}

func (manager *ProcessManager) Call(ctx context.Context, agentName string, request RPCMessage) (RPCMessage, error) {
	process, err := manager.agent(agentName)
	if err != nil {
		return RPCMessage{}, err
	}

	return process.Call(ctx, request)
}

func (manager *ProcessManager) agent(agentName string) (*AgentProcess, error) {
	manager.mu.Lock()
	defer manager.mu.Unlock()

	config, ok := manager.config[agentName]
	if !ok {
		return nil, fmt.Errorf("unknown gateway agent %q", agentName)
	}

	if process, ok := manager.process[agentName]; ok {
		return process, nil
	}

	process := &AgentProcess{
		name:   agentName,
		config: config,
	}
	manager.process[agentName] = process

	return process, nil
}

func (manager *ProcessManager) Shutdown() error {
	manager.mu.Lock()
	defer manager.mu.Unlock()

	for name, process := range manager.process {
		if err := process.Close(); err != nil {
			log.Printf("gateway agent %s shutdown error: %v", name, err)
		}
	}

	return nil
}

type AgentProcess struct {
	mu     sync.Mutex
	name   string
	config AgentConfig

	cmd    *exec.Cmd
	stdin  io.WriteCloser
	stdout *bufio.Reader
}

func (process *AgentProcess) Call(ctx context.Context, request RPCMessage) (RPCMessage, error) {
	process.mu.Lock()
	defer process.mu.Unlock()

	if err := process.ensureStartedLocked(); err != nil {
		return RPCMessage{}, err
	}

	if request.JSONRPC == "" {
		request.JSONRPC = "2.0"
	}

	if err := writeFramedMessage(process.stdin, process.config.Framing, request); err != nil {
		_ = process.killLocked()
		return RPCMessage{}, err
	}

	type result struct {
		message RPCMessage
		err     error
	}

	done := make(chan result, 1)
	go func() {
		message, err := readResponse(process.stdout, process.config.Framing, request.ID)
		done <- result{message: message, err: err}
	}()

	select {
	case <-ctx.Done():
		_ = process.killLocked()
		return RPCMessage{}, ctx.Err()
	case response := <-done:
		if response.err != nil {
			_ = process.killLocked()
			return RPCMessage{}, response.err
		}

		return response.message, nil
	}
}

func (process *AgentProcess) Close() error {
	process.mu.Lock()
	defer process.mu.Unlock()

	return process.killLocked()
}

func (process *AgentProcess) ensureStartedLocked() error {
	if process.cmd != nil && process.cmd.Process != nil && process.cmd.ProcessState == nil {
		return nil
	}

	command := exec.Command(process.config.Command[0], process.config.Command[1:]...)
	command.Dir = process.config.Cwd
	command.Env = append(os.Environ(), flattenedEnv(process.config.Env)...)

	stdin, err := command.StdinPipe()
	if err != nil {
		return err
	}

	stdout, err := command.StdoutPipe()
	if err != nil {
		return err
	}

	stderr, err := command.StderrPipe()
	if err != nil {
		return err
	}

	if err := command.Start(); err != nil {
		return err
	}

	process.cmd = command
	process.stdin = stdin
	process.stdout = bufio.NewReader(stdout)

	go streamStderr(process.name, stderr)
	go process.await(command)

	return nil
}

func (process *AgentProcess) await(command *exec.Cmd) {
	err := command.Wait()
	process.mu.Lock()
	defer process.mu.Unlock()

	if process.cmd == command {
		process.cmd = nil
		process.stdin = nil
		process.stdout = nil
	}

	if err != nil {
		log.Printf("gateway agent %s exited: %v", process.name, err)
	}
}

func (process *AgentProcess) killLocked() error {
	if process.cmd == nil || process.cmd.Process == nil {
		process.cmd = nil
		process.stdin = nil
		process.stdout = nil
		return nil
	}

	err := process.cmd.Process.Kill()
	process.cmd = nil
	process.stdin = nil
	process.stdout = nil

	return err
}

func readResponse(reader *bufio.Reader, framing string, id json.RawMessage) (RPCMessage, error) {
	requestID := normalizeRawID(id)

	for {
		message, err := readFramedMessage(reader, framing)
		if err != nil {
			return RPCMessage{}, err
		}

		if len(message.ID) == 0 {
			continue
		}

		if normalizeRawID(message.ID) == requestID {
			return message, nil
		}
	}
}

func writeFramedMessage(writer io.Writer, framing string, message RPCMessage) error {
	payload, err := json.Marshal(message)
	if err != nil {
		return err
	}

	switch framing {
	case "newline":
		_, err = writer.Write(append(payload, '\n'))
		return err
	default:
		header := fmt.Sprintf("Content-Length: %d\r\n\r\n", len(payload))
		if _, err = io.WriteString(writer, header); err != nil {
			return err
		}
		_, err = writer.Write(payload)
		return err
	}
}

func readFramedMessage(reader *bufio.Reader, framing string) (RPCMessage, error) {
	switch framing {
	case "newline":
		line, err := reader.ReadBytes('\n')
		if err != nil {
			return RPCMessage{}, err
		}

		var message RPCMessage
		err = json.Unmarshal(bytes.TrimSpace(line), &message)
		return message, err
	default:
		length, err := readContentLength(reader)
		if err != nil {
			return RPCMessage{}, err
		}

		payload := make([]byte, length)
		if _, err := io.ReadFull(reader, payload); err != nil {
			return RPCMessage{}, err
		}

		var message RPCMessage
		err = json.Unmarshal(payload, &message)
		return message, err
	}
}

func readContentLength(reader *bufio.Reader) (int, error) {
	contentLength := 0

	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			return 0, err
		}

		trimmed := strings.TrimSpace(line)
		if trimmed == "" {
			break
		}

		key, value, found := strings.Cut(trimmed, ":")
		if !found {
			continue
		}

		if strings.EqualFold(strings.TrimSpace(key), "Content-Length") {
			parsed, err := strconv.Atoi(strings.TrimSpace(value))
			if err != nil {
				return 0, err
			}
			contentLength = parsed
		}
	}

	if contentLength < 1 {
		return 0, fmt.Errorf("missing Content-Length header")
	}

	return contentLength, nil
}

func normalizeRawID(id json.RawMessage) string {
	return strings.TrimSpace(string(id))
}

func flattenedEnv(environment map[string]string) []string {
	flattened := make([]string, 0, len(environment))
	for key, value := range environment {
		flattened = append(flattened, fmt.Sprintf("%s=%s", key, value))
	}

	return flattened
}

func streamStderr(agentName string, stream io.Reader) {
	scanner := bufio.NewScanner(stream)
	for scanner.Scan() {
		log.Printf("[%s] %s", agentName, scanner.Text())
	}
}

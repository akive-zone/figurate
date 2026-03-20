package gateway

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

type Config struct {
	Listen    string                 `json:"listen"`
	AuthToken string                 `json:"auth_token"`
	Agents    map[string]AgentConfig `json:"agents"`
}

type AgentConfig struct {
	Label                 string            `json:"label"`
	Command               []string          `json:"command"`
	Cwd                   string            `json:"cwd"`
	Env                   map[string]string `json:"env"`
	Framing               string            `json:"framing"`
	StartupTimeoutSeconds int               `json:"startup_timeout_seconds"`
}

func LoadConfig(path string) (Config, error) {
	content, err := os.ReadFile(path)
	if err != nil {
		return Config{}, err
	}

	var cfg Config
	if err := json.Unmarshal(content, &cfg); err != nil {
		return Config{}, err
	}

	cfg.Listen = strings.TrimSpace(cfg.Listen)
	if cfg.Listen == "" {
		cfg.Listen = "127.0.0.1:4319"
	}

	baseDir := filepath.Dir(path)

	if len(cfg.Agents) == 0 {
		return Config{}, fmt.Errorf("gateway config does not define any agents")
	}

	for key, agent := range cfg.Agents {
		agent.Label = strings.TrimSpace(agent.Label)
		agent.Cwd = resolvePath(expandString(agent.Cwd), baseDir)
		agent.Framing = normalizeFraming(agent.Framing)
		if agent.StartupTimeoutSeconds < 1 {
			agent.StartupTimeoutSeconds = 10
		}
		for index, part := range agent.Command {
			agent.Command[index] = expandString(part)
		}
		if len(agent.Command) == 0 {
			return Config{}, fmt.Errorf("agent %q is missing a command", key)
		}
		if agent.Env == nil {
			agent.Env = map[string]string{}
		}
		for envKey, value := range agent.Env {
			agent.Env[envKey] = expandString(value)
		}
		cfg.Agents[key] = agent
	}

	return cfg, nil
}

func expandString(value string) string {
	return os.ExpandEnv(strings.TrimSpace(value))
}

func resolvePath(value string, baseDir string) string {
	if value == "" || filepath.IsAbs(value) {
		return value
	}

	return filepath.Clean(filepath.Join(baseDir, value))
}

func normalizeFraming(value string) string {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "newline", "ndjson":
		return "newline"
	default:
		return "content-length"
	}
}

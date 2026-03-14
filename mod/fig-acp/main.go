package main

import (
	"context"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"figurate/mod/fig-acp/internal/acp"
	"figurate/mod/fig-acp/internal/figurate"
)

func main() {
	logger := log.New(os.Stderr, "fig-acp: ", log.LstdFlags|log.Lmsgprefix)

	config := figurate.Config{
		BaseURL:          os.Getenv("FIG_ACP_BASE_URL"),
		Token:            os.Getenv("FIG_ACP_TOKEN"),
		UserUUID:         os.Getenv("FIG_ACP_USER_UUID"),
		DefaultChannelID: os.Getenv("FIG_ACP_CHANNEL_ID"),
		SessionPurpose:   envOrDefault("FIG_ACP_SESSION_PURPOSE", "execution"),
		SessionTitle:     envOrDefault("FIG_ACP_SESSION_TITLE", "ACP Session"),
		PollInterval:     durationFromEnv("FIG_ACP_POLL_INTERVAL", time.Second),
		PromptTimeout:    durationFromEnv("FIG_ACP_PROMPT_TIMEOUT", 2*time.Minute),
	}

	client := figurate.NewClient(config)
	server := acp.NewServer(logger, client)

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	if err := server.Serve(ctx, os.Stdin, os.Stdout); err != nil {
		logger.Printf("server stopped with error: %v", err)
		os.Exit(1)
	}
}

func envOrDefault(key string, fallback string) string {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	return value
}

func durationFromEnv(key string, fallback time.Duration) time.Duration {
	value := os.Getenv(key)
	if value == "" {
		return fallback
	}

	duration, err := time.ParseDuration(value)
	if err != nil {
		return fallback
	}

	return duration
}

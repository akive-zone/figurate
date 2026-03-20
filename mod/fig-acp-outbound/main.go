package main

import (
	"flag"
	"log"
	"os"
	"os/signal"
	"syscall"

	"fig-acp-outbound/internal/gateway"
)

func main() {
	defaultConfigPath := os.Getenv("FIG_ACP_OUTBOUND_CONFIG")
	if defaultConfigPath == "" {
		defaultConfigPath = "config.json"
	}

	configPath := flag.String("config", defaultConfigPath, "Path to the gateway config file")
	flag.Parse()

	cfg, err := gateway.LoadConfig(*configPath)
	if err != nil {
		log.Fatalf("load config: %v", err)
	}

	server, err := gateway.NewServer(cfg)
	if err != nil {
		log.Fatalf("create server: %v", err)
	}

	signals := make(chan os.Signal, 1)
	signal.Notify(signals, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		<-signals
		if err := server.Shutdown(); err != nil {
			log.Printf("shutdown error: %v", err)
		}
	}()

	log.Printf("fig-acp-outbound listening on %s", cfg.Listen)
	if err := server.ListenAndServe(); err != nil {
		log.Fatalf("serve: %v", err)
	}
}

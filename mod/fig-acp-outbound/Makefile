LOCAL_ROOT := $(CURDIR)

export GOCACHE := $(LOCAL_ROOT)/.gocache
export GOMODCACHE := $(LOCAL_ROOT)/.gomodcache
export GOTMPDIR := $(LOCAL_ROOT)/.tmp

CONFIG ?= $(LOCAL_ROOT)/config.json

.PHONY: build run test

build:
	mkdir -p $(GOCACHE) $(GOMODCACHE) $(GOTMPDIR) $(LOCAL_ROOT)/bin
	go build -o $(LOCAL_ROOT)/bin/fig-acp-outbound .

run: build
	$(LOCAL_ROOT)/bin/fig-acp-outbound --config $(CONFIG)

test:
	mkdir -p $(GOCACHE) $(GOMODCACHE) $(GOTMPDIR)
	go test ./...

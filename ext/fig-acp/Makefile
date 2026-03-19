GOCACHE := $(CURDIR)/.gocache
GOMODCACHE := $(CURDIR)/.gomodcache
GOTMPDIR := $(CURDIR)/.tmp

export GOCACHE
export GOMODCACHE
export GOTMPDIR

.PHONY: prepare build run clean

prepare:
	mkdir -p "$(GOCACHE)" "$(GOMODCACHE)" "$(GOTMPDIR)" "$(CURDIR)/bin"

build: prepare
	go build -o "$(CURDIR)/bin/fig-acp" .

run: prepare
	go run .

clean:
	rm -rf "$(CURDIR)/bin" "$(GOCACHE)" "$(GOMODCACHE)" "$(GOTMPDIR)"

package gateway

import (
	"bufio"
	"bytes"
	"encoding/json"
	"testing"
)

func TestContentLengthRoundTrip(t *testing.T) {
	buffer := &bytes.Buffer{}
	message := RPCMessage{
		JSONRPC: "2.0",
		ID:      json.RawMessage(`"rpc-1"`),
		Method:  "initialize",
		Params:  json.RawMessage(`{"client":{"name":"Figurate"}}`),
	}

	if err := writeFramedMessage(buffer, "content-length", message); err != nil {
		t.Fatalf("writeFramedMessage returned error: %v", err)
	}

	decoded, err := readFramedMessage(bufio.NewReader(buffer), "content-length")
	if err != nil {
		t.Fatalf("readFramedMessage returned error: %v", err)
	}

	if string(decoded.ID) != `"rpc-1"` {
		t.Fatalf("unexpected id: %s", decoded.ID)
	}
	if decoded.Method != "initialize" {
		t.Fatalf("unexpected method: %s", decoded.Method)
	}
}

func TestNewlineRoundTrip(t *testing.T) {
	buffer := &bytes.Buffer{}
	message := RPCMessage{
		JSONRPC: "2.0",
		ID:      json.RawMessage(`1`),
		Result:  json.RawMessage(`{"ok":true}`),
	}

	if err := writeFramedMessage(buffer, "newline", message); err != nil {
		t.Fatalf("writeFramedMessage returned error: %v", err)
	}

	decoded, err := readFramedMessage(bufio.NewReader(buffer), "newline")
	if err != nil {
		t.Fatalf("readFramedMessage returned error: %v", err)
	}

	if string(decoded.ID) != "1" {
		t.Fatalf("unexpected id: %s", decoded.ID)
	}
	if string(decoded.Result) != `{"ok":true}` {
		t.Fatalf("unexpected result: %s", decoded.Result)
	}
}

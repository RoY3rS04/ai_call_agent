package main

import (
	"fmt"
	"log"
	"net/http"

	"github.com/gorilla/websocket"
)

// Creating indivual structs for each type of message twilio sends (for now)

type SetupMessage struct {
	Type             string            `json:"type"`
	SessionID        string            `json:"sessionId"`
	AccountSID       string            `json:"accountSid"`
	ParentCallSID    string            `json:"parentCallSid"`
	CallSID          string            `json:"callSid"`
	From             string            `json:"from"`
	To               string            `json:"to"`
	ForwardedFrom    string            `json:"forwardedFrom"`
	CallType         string            `json:"callType"`
	CallerName       string            `json:"callerName"`
	Direction        string            `json:"direction"`
	CallStatus       string            `json:"callStatus"`
	CustomParameters map[string]string `json:"customParameters"`
}

type PromptMessage struct {
	Type        string
	VoicePrompt string
	Lang        string
	Last        bool
}

type DTMFMessage struct {
	Type  string
	Digit string
}

type InterruptMessage struct {
	Type                     string
	UtteranceUntilInterrupt  string
	DurationUntilInterruptMs int
}

type ErrorMessage struct {
	Type        string
	Description string
}

// Create structs for messages we could send

type TextTokenMessage struct {
	Type          string
	Token         string
	Last          bool
	Interruptible bool
	Preemptible   bool
}

type PlayMediaMessage struct {
	Type          string
	Source        string
	Loop          int
	Preemptible   bool
	Interruptible bool
}

type DigitMessage struct {
	Type   string
	Digits string
}

type LanguageMessage struct {
	Type                  string
	TtsLanguage           string
	TranscriptionLanguage string
}

type EndSessionMessage struct {
	Type        string
	HandoffData string
}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func wssEndpoint(w http.ResponseWriter, r *http.Request) {

	upgrader.CheckOrigin = func(r *http.Request) bool { return true }

	ws, err := upgrader.Upgrade(w, r, nil)

	if err != nil {
		log.Println(err)
		return
	}

	log.Println("Client connected")

	reader(ws)
}

func main() {

	mux := http.NewServeMux()

	mux.HandleFunc("/wss", wssEndpoint)

	log.Println("Starting server on :3000")

	err := http.ListenAndServe(":3000", mux)
	log.Fatal(err)
}

func reader(conn *websocket.Conn) {
	for {
		// read in a message
		_, p, err := conn.ReadMessage()
		if err != nil {
			log.Println(err)
			return
		}
		// print out that message for clarity
		fmt.Println(string(p))
	}
}

package internal

import (
	"encoding/json"
	"fmt"
)

// Creating indivual structs for each type of message twilio sends (for now)

type TwilioMessageType string

const (
	SetupMessageType      TwilioMessageType = "setup"
	PromptMessageType     TwilioMessageType = "prompt"
	DTMFMessageType       TwilioMessageType = "dtmf"
	InterruptMessageType  TwilioMessageType = "interrupt"
	ErrorMessageType      TwilioMessageType = "error"
	TextTokenMessageType  TwilioMessageType = "text"
	PlayMediaMessageType  TwilioMessageType = "play"
	DigitMessageType      TwilioMessageType = "sendDigits"
	LanguageMessageType   TwilioMessageType = "language"
	EndSessionMessageType TwilioMessageType = "end"
)

type TwilioMessage struct {
	Type TwilioMessageType `json:"type"`
}

type TwilioMessenger interface {
	GetMessageType() TwilioMessageType
}

func (msg TwilioMessage) GetMessageType() TwilioMessageType {
	return msg.Type
}

type SetupMessage struct {
	TwilioMessage
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
	TwilioMessage
	VoicePrompt string `json:"voicePrompt"`
	Lang        string `json:"lang"`
	Last        bool   `json:"last"`
}

type DTMFMessage struct {
	TwilioMessage
	Digit string `json:"digit"`
}

type InterruptMessage struct {
	TwilioMessage
	UtteranceUntilInterrupt  string `json:"utteranceUntilInterrupt"`
	DurationUntilInterruptMs int    `json:"durationUntilInterruptMs"`
}

type ErrorMessage struct {
	TwilioMessage
	Description string `json:"description"`
}

// Create structs for messages we could send

type TextTokenMessage struct {
	TwilioMessage
	Token         string `json:"token"`
	Last          bool   `json:"last"`
	Interruptible bool   `json:"interruptible"`
	Preemptible   bool   `json:"preemptible"`
}

type PlayMediaMessage struct {
	TwilioMessage
	Source        string `json:"source"`
	Loop          int    `json:"loop"`
	Preemptible   bool   `json:"preemptible"`
	Interruptible bool   `json:"interruptible"`
}

type DigitMessage struct {
	TwilioMessage
	Digits string `json:"digits"`
}

type LanguageMessage struct {
	TwilioMessage
	TtsLanguage           string `json:"ttsLanguage"`
	TranscriptionLanguage string `json:"transcriptionLanguage"`
}

type EndSessionMessage struct {
	TwilioMessage
	HandoffData string `json:"handoffData"`
}

func GetTwilioMessage(raw []byte) TwilioMessenger {

	var twilioMessage TwilioMessage

	err := json.Unmarshal(raw, &twilioMessage)
	if err != nil {
		// Handle error
		fmt.Println("Invalid message received")
		return nil
	}

	switch twilioMessage.Type {
	case SetupMessageType:
		var msg SetupMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid setup message received")
			return nil
		}
		return msg
	case PromptMessageType:
		var msg PromptMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid prompt message received")
			return nil
		}
		return msg
	case DTMFMessageType:
		var msg DTMFMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid dtmf message received")
			return nil
		}
		return msg
	case InterruptMessageType:
		var msg InterruptMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid interrupt message received")
			return nil
		}
		return msg
	case ErrorMessageType:
		var msg ErrorMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid error message received")
			return nil
		}
		return msg
	case TextTokenMessageType:
		var msg TextTokenMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid text token message received")
			return nil
		}
		return msg
	case PlayMediaMessageType:
		var msg PlayMediaMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid play media message received")
			return nil
		}
		return msg
	case DigitMessageType:
		var msg DigitMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid digit message received")
			return nil
		}
		return msg
	case LanguageMessageType:
		var msg LanguageMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid language message received")
			return nil
		}
		return msg
	case EndSessionMessageType:
		var msg EndSessionMessage
		if err := json.Unmarshal(raw, &msg); err != nil {
			fmt.Println("Invalid end session message received")
			return nil
		}
		return msg
	default:
		fmt.Printf("Unknown Twilio message type received: %s\n", twilioMessage.Type)
		return nil
	}
}

package internal

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

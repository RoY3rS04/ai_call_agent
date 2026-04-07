package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"sync"

	"github.com/RoY3rS04/conversation_relay/internal"
	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
	"github.com/redis/go-redis/v9"
)

type WebSocketServer struct {
	redisClient *redis.Client
	connections map[string]*CallSession
	mutex       sync.RWMutex
}

type CallSession struct {
	CallSID    string
	Connection *websocket.Conn
}

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

func main() {

	if err := godotenv.Load("../.env"); err != nil {
		log.Println("No .env file found")
		os.Exit(1)
	}

	clientPort, err := strconv.Atoi(os.Getenv("REDIS_PORT"))

	if err != nil {
		clientPort = 6379
	}

	redisClient := internal.NewRedisClient(internal.RedisConfig{
		Addr: internal.Address{
			Host: os.Getenv("REDIS_HOST"),
			Port: clientPort,
		},
		Password: envOrEmpty("REDIS_PASSWORD"),
		DB:       0,
		Protocol: 2,
	})

	server := WebSocketServer{
		redisClient: redisClient,
		connections: make(map[string]*CallSession),
		mutex:       sync.RWMutex{},
	}

	go server.SubscribeToRedisChannel(
		context.Background(),
		"twilio:outbound",
		func(msg string) {

			log.Println("from redis: ", msg)

			twilioMessage := internal.GetTwilioMessage([]byte(msg))
			if twilioMessage == nil {
				log.Println("Failed to process Twilio message")
				return
			}

			switch msg := twilioMessage.(type) {
			case internal.TextTokenMessage:
				session := server.getCallSession(msg.CallSID)

				if session == nil {
					log.Println("No session found for this callSid: ", msg.CallSID)
					return
				}

				jsonMsg, err := json.Marshal(struct {
					Type string `json:"type"`
					internal.TextTokenPayload
				}{
					Type:             string(msg.Type),
					TextTokenPayload: msg.Data,
				})

				if err != nil {
					log.Println("Error marshalling message data to JSON:", err)
					return
				}

				err = session.Connection.WriteMessage(websocket.TextMessage, []byte(jsonMsg))
				if err != nil {
					log.Println("Error writing message to WebSocket:", err)
					return
				}

			case internal.LanguageMessage:
				session := server.getCallSession(msg.CallSID)

				if session == nil {
					log.Println("No session found for this callSid: ", msg.CallSID)
					return
				}

				jsonMsg, err := json.Marshal(struct {
					Type string `json:"type"`
					internal.LanguagePayload
				}{
					Type:            string(msg.Type),
					LanguagePayload: msg.Data,
				})

				if err != nil {
					log.Println("Error marshalling message data to JSON:", err)
					return
				}

				err = session.Connection.WriteMessage(websocket.TextMessage, []byte(jsonMsg))
				if err != nil {
					log.Println("Error writing message to WebSocket:", err)
					return
				}
			default:
				log.Println("Received unsupported message type from Redis")
				return
			}
		},
	)

	mux := http.NewServeMux()

	mux.HandleFunc("/wss", server.wssEndpoint)
	mux.HandleFunc("/ping-redis", server.pingRedis)

	log.Println("Starting server on :3000")

	err = http.ListenAndServe(":3000", mux)
	log.Fatal(err)
}

func (server *WebSocketServer) reader(callSession *CallSession) {
	for {
		// read in a message
		_, p, err := callSession.Connection.ReadMessage()
		if err != nil {
			log.Println(err)
			return
		}

		twilioMessage := internal.GetTwilioMessage(p)
		if twilioMessage == nil {
			log.Println("Failed to process Twilio message")
			return
		}

		switch msg := twilioMessage.(type) {
		case internal.SetupMessage:
			server.setCallSession(msg.CallSID, callSession)
		}

		data := struct {
			CallSid string `json:"callSid"`
			Data    any    `json:"data"`
		}{
			CallSid: callSession.CallSID,
			Data:    string(p),
		}

		jsonData, err := json.Marshal(data)

		if err != nil {
			log.Println("Error marshalling message to JSON:", err)
			return
		}

		// print out that message for clarity
		server.redisClient.Publish(
			context.Background(),
			"twilio:inbound",
			string(jsonData),
		)
		fmt.Println(string(p))
	}
}

func (server *WebSocketServer) getCallSession(callSID string) *CallSession {
	server.mutex.RLock()
	session, exists := server.connections[callSID]
	server.mutex.RUnlock()

	if !exists {
		log.Printf("No active WebSocket connection for CallSID: %s\n", callSID)
		return nil
	}

	return session
}

func (server *WebSocketServer) setCallSession(callSID string, session *CallSession) {
	server.mutex.Lock()
	session.CallSID = callSID
	server.connections[callSID] = session
	server.mutex.Unlock()
}

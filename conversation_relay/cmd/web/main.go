package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"

	"github.com/RoY3rS04/conversation_relay/internal"
	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
	"github.com/redis/go-redis/v9"
)

type WebSocketServer struct {
	redisClient *redis.Client
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
	}

	go server.SubscribeToRedisChannel(context.Background(), "twilio:outbound", func(msg string) {
		log.Println("Received message from Redis channel callback:", msg)
	})

	mux := http.NewServeMux()

	mux.HandleFunc("/wss", server.wssEndpoint)
	mux.HandleFunc("/ping-redis", server.pingRedis)

	log.Println("Starting server on :3000")

	err = http.ListenAndServe(":3000", mux)
	log.Fatal(err)
}

func (server *WebSocketServer) reader(conn *websocket.Conn) {
	for {
		// read in a message
		_, p, err := conn.ReadMessage()
		if err != nil {
			log.Println(err)
			return
		}
		// print out that message for clarity
		server.redisClient.Publish(context.Background(), "twilio:inbound", string(p))
		fmt.Println(string(p))
	}
}

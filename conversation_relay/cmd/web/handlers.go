package main

import (
	"context"
	"log"
	"net/http"
)

func (server *WebSocketServer) wssEndpoint(w http.ResponseWriter, r *http.Request) {

	upgrader.CheckOrigin = func(r *http.Request) bool { return true }

	connection, err := upgrader.Upgrade(w, r, nil)

	if err != nil {
		log.Println(err)
		return
	}

	log.Println("Client connected")

	server.reader(connection)
}

func (server *WebSocketServer) pingRedis(w http.ResponseWriter, r *http.Request) {

	ctx := context.Background()

	pong, err := server.redisClient.Ping(ctx).Result()
	if err != nil {
		log.Println("Error pinging Redis:", err)
	}

	log.Println("Redis connected:", pong)
}

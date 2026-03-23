package main

import (
	"context"
	"log"
	"os"
	"strings"
)

func (server *WebSocketServer) SubscribeToRedisChannel(
	context context.Context,
	channel string,
	callback func(msg string),
) {

	sub := server.redisClient.Subscribe(context, channel)

	defer sub.Close()

	ch := sub.Channel()

	for msg := range ch {
		log.Println("Received message from Redis channel:", msg.Payload)
		callback(msg.Payload)
	}
}

func envOrEmpty(key string) string {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" || strings.EqualFold(v, "null") {
		return ""
	}
	return v
}

package main

import (
	"fmt"
	"log"
	"net/http"
	"github.com/gorilla/websocket"
)

var upgrader = websocket.Upgrader{
    ReadBufferSize: 1024,
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
        messageType, p, err := conn.ReadMessage()
        if err != nil {
            log.Println(err)
            return
        }
    // print out that message for clarity
        fmt.Println(string(p))

        if err := conn.WriteMessage(messageType, p); err != nil {
            log.Println(err)
            return
        }

    }
}
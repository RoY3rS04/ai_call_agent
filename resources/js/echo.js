import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const socket = new WebSocket('wss://' + import.meta.env.VITE_GO_WEBSOCKET_SERVER_HOST)

socket.onopen = (event) => {
    console.log('Connected to the server!');

    // 2. Now it's safe to send data
    socket.send(JSON.stringify({
        title: 'oe',
        data: {
            oe: 'que onda',
            number: 1
        }
    }));
};

// Good practice: handle errors too
socket.addEventListener("error", (event) => {
    console.log("WebSocket error: ", event);
});

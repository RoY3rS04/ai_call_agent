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

const Channel = {
    CALLS: 'calls',
    CALL_MESSAGES: /^calls\.[^.]+$/,
}

const context = document.getElementById('realtime-context');
const callMessagesContainer = document.getElementById('call-messages');
const subscribedChannels = new Set();

document.addEventListener('livewire:navigated', () => {

    subscribeToChannel(Channel.CALLS, (echoChannel) => {
        echoChannel.listen('CallStarted', ({call}) => handleCallStartedEvent(call))
    });

    if (! context) {
        return;
    }

    const channels = JSON.parse(context.dataset.channels)

    if (context.dataset.page === 'calls-list') {
        addListenerToChannel(Channel.CALLS, (echoChannel) => {
            echoChannel.listen('CallStatusUpdated', ({callSid}) => handleCallStatusUpdatedEvent(callSid))
        });
    }

    for (const channel of channels) {
        if (Channel.CALL_MESSAGES.test(channel)) {
            subscribeToChannel(channel, (echoChannel) => {
                echoChannel
                    .listen('NewCallMessage', ({message, direction}) => handleNewCallMessage(message, direction))
                    .listen('CallStatusUpdated', () => {
                        window.Livewire.dispatch('call-view-status-updated');
                    });
            });
        }
    }
})

function subscribeToChannel(name, registerListeners) {
    if (subscribedChannels.has(name)) {
        return;
    }

    const echoChannel = window.Echo.private(name);

    registerListeners(echoChannel);

    subscribedChannels.add(name);
}

function addListenerToChannel(channelName, registerListener) {
    if (!subscribedChannels.has(channelName)) {
        return;
    }

    const echoChannel = window.Echo.private(channelName)

    registerListener(echoChannel);
}

function handleCallStartedEvent(call) {

    window.Livewire?.dispatch('calls-table-call-started');

    new window.FilamentNotification()
        .title('A new call has started')
        .info()
        .actions([
            new window.FilamentNotificationAction('view')
                .label('Check it out!')
                .button()
                .url(`/calls/${call.id}`)
        ])
        .send()
}

function handleCallStatusUpdatedEvent(callSid) {
    window.Livewire.dispatch('calls-table-status-updated', {callSid})
}

function handleNewCallMessage(message, direction) {

    console.log('hey')
    if (! callMessagesContainer) {
        return;
    }

    const isCustomer = direction === 'inbound' || message.role === 'customer';
    const wrapper = document.createElement('div');
    wrapper.className = 'flex max-w-[82%] items-end gap-x-3';

    if (isCustomer) {
        wrapper.classList.add('self-end');
    }

    const avatar = document.createElement('div');
    avatar.className = 'rounded-full bg-gray-200 text-gray-400 p-2 flex items-center justify-center dark:bg-gray-700 dark:text-gray-300';

    if (isCustomer) {
        avatar.classList.add('order-1');
    }

    avatar.innerHTML = isCustomer
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-icon lucide-user-round"><circle cx="12" cy="8" r="5"></circle><path d="M20 21a8 8 0 0 0-16 0"></path></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain-circuit-icon lucide-brain-circuit"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"></path><path d="M9 13a4.5 4.5 0 0 0 3-4"></path><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"></path><path d="M3.477 10.896a4 4 0 0 1 .585-.396"></path><path d="M6 18a4 4 0 0 1-1.967-.516"></path><path d="M12 13h4"></path><path d="M12 18h6a2 2 0 0 1 2 2v1"></path><path d="M12 8h8"></path><path d="M16 8V5a2 2 0 0 1 2-2"></path><circle cx="16" cy="13" r=".5"></circle><circle cx="18" cy="3" r=".5"></circle><circle cx="20" cy="21" r=".5"></circle><circle cx="20" cy="8" r=".5"></circle></svg>';

    const bubble = document.createElement('div');
    bubble.className = 'relative rounded-2xl px-4 pt-3 pb-7 text-sm leading-6 shadow-sm ring-1 ring-inset';
    bubble.classList.add(
        ...(isCustomer
            ? ['bg-sky-600', 'text-white', 'ring-sky-500/20', 'rounded-br-md']
            : ['rounded-bl-md', 'bg-white', 'text-gray-900', 'ring-gray-200', 'dark:bg-gray-900', 'dark:text-gray-200', 'dark:ring-gray-700/80'])
    );

    const content = document.createElement('p');
    content.className = 'whitespace-pre-wrap break-words pr-18';
    content.textContent = message.content ?? '';

    const timestamp = document.createElement('span');
    timestamp.className = 'absolute bottom-2.5 right-3 text-[11px] font-medium';
    timestamp.classList.add(...(isCustomer ? ['text-white/80'] : ['text-gray-500', 'dark:text-gray-400']));

    if (message.created_at) {
        const createdAt = new Date(message.created_at);

        timestamp.textContent = Number.isNaN(createdAt.getTime())
            ? ''
            : createdAt.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
    }

    bubble.append(content, timestamp);
    wrapper.append(avatar, bubble);
    callMessagesContainer.appendChild(wrapper);
}

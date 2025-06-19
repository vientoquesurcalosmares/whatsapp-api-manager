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

const type = import.meta.env.VITE_WHATSAPP_BROADCAST_CHANNEL_TYPE || 'public';
const echoChannel = type === 'private'
    ? window.Echo.private('whatsapp-messages')
    : window.Echo.channel('whatsapp-messages');

echoChannel.listen('MessageReceived', (e) => {
    alert(`Mensaje recibido`);
    console.log("Evento Mensaje recibido!");
});

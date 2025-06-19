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

// Configuración de canales
const getChannel = (channelName) => {
    return type === 'private'
        ? window.Echo.private(channelName)
        : window.Echo.channel(channelName);
};

// Canal: whatsapp-business
const businessChannel = getChannel('whatsapp-business');
businessChannel
    .listen('.BusinessSettingsUpdated', (e) => {
        console.log("BusinessSettingsUpdated:", e);
    })
    .listen('.PhoneNumberStatusUpdated', (e) => {
        console.log("PhoneNumberStatusUpdated:", e);
    });

// Canal: whatsapp-contacts
const contactsChannel = getChannel('whatsapp-contacts');
contactsChannel
    .listen('.ContactCreated', (e) => {
        console.log("ContactCreated:", e);
    })
    .listen('.ContactUpdated', (e) => {
        console.log("ContactUpdated:", e);
    });

// Canal: whatsapp-messages (maneja múltiples tipos de mensajes)
const messagesChannel = getChannel('whatsapp-messages');
messagesChannel
    .listen('.MessageReceived', (e) => {
        console.log("MessageReceived:", e);
    })
    .listen('.TextMessageReceived', (e) => {
        console.log("TextMessageReceived:", e);
    })
    .listen('.MediaMessageReceived', (e) => {
        console.log("MediaMessageReceived:", e);
    })
    .listen('.InteractiveMessageReceived', (e) => {
        console.log("InteractiveMessageReceived:", e);
    })
    .listen('.LocationMessageReceived', (e) => {
        console.log("LocationMessageReceived:", e);
    })
    .listen('.ReactionReceived', (e) => {
        console.log("ReactionReceived:", e);
    })
    .listen('.ContactMessageReceived', (e) => {
        console.log("ContactMessageReceived:", e);
    });

// Canal: whatsapp-status
const statusChannel = getChannel('whatsapp-status');
statusChannel
    .listen('.MessageDeleted', (e) => {
        console.log("MessageDeleted:", e);
    })
    .listen('.MessageDelivered', (e) => {
        console.log("MessageDelivered:", e);
    })
    .listen('.MessageRead', (e) => {
        console.log("MessageRead:", e);
    });

// Canal: whatsapp-outgoing
const outgoingChannel = getChannel('whatsapp-outgoing');
outgoingChannel
    .listen('.MessageSent', (e) => {
        console.log("MessageSent:", e);
    })
    .listen('.MessageFailed', (e) => {
        console.log("MessageFailed:", e);
    })
    .listen('.TemplateMessageSent', (e) => {
        console.log("TemplateMessageSent:", e);
    });

// Canal: whatsapp-templates
const templatesChannel = getChannel('whatsapp-templates');
templatesChannel
    .listen('.TemplateCreated', (e) => {
        console.log("TemplateCreated:", e);
    })
    .listen('.TemplateApproved', (e) => {
        console.log("TemplateApproved:", e);
    })
    .listen('.TemplateRejected', (e) => {
        console.log("TemplateRejected:", e);
    });

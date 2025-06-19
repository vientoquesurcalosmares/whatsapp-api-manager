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
        alert('Configuración de negocio actualizada');
        console.log("BusinessSettingsUpdated:", e);
    })
    .listen('.PhoneNumberStatusUpdated', (e) => {
        alert('Estado de número telefónico actualizado');
        console.log("PhoneNumberStatusUpdated:", e);
    });

// Canal: whatsapp-contacts
const contactsChannel = getChannel('whatsapp-contacts');
contactsChannel
    .listen('.ContactCreated', (e) => {
        alert('Nuevo contacto creado');
        console.log("ContactCreated:", e);
    })
    .listen('.ContactUpdated', (e) => {
        alert('Contacto actualizado');
        console.log("ContactUpdated:", e);
    });

// Canal: whatsapp-messages (maneja múltiples tipos de mensajes)
const messagesChannel = getChannel('whatsapp-messages');
messagesChannel
    .listen('.MessageReceived', (e) => {
        alert('Mensaje recibido');
        console.log("MessageReceived:", e);
    })
    .listen('.TextMessageReceived', (e) => {
        alert('Mensaje de texto recibido');
        console.log("TextMessageReceived:", e);
    })
    .listen('.MediaMessageReceived', (e) => {
        alert('Mensaje multimedia recibido');
        console.log("MediaMessageReceived:", e);
    })
    .listen('.InteractiveMessageReceived', (e) => {
        alert('Mensaje interactivo recibido');
        console.log("InteractiveMessageReceived:", e);
    })
    .listen('.LocationMessageReceived', (e) => {
        alert('Mensaje con ubicación recibido');
        console.log("LocationMessageReceived:", e);
    })
    .listen('.ReactionReceived', (e) => {
        alert('Reacción recibida');
        console.log("ReactionReceived:", e);
    })
    .listen('.ContactMessageReceived', (e) => {
        alert('Mensaje de contacto recibido');
        console.log("ContactMessageReceived:", e);
    });

// Canal: whatsapp-status
const statusChannel = getChannel('whatsapp-status');
statusChannel
    .listen('.MessageDeleted', (e) => {
        alert('Mensaje eliminado');
        console.log("MessageDeleted:", e);
    })
    .listen('.MessageDelivered', (e) => {
        alert('Mensaje entregado');
        console.log("MessageDelivered:", e);
    })
    .listen('.MessageRead', (e) => {
        alert('Mensaje leído');
        console.log("MessageRead:", e);
    });

// Canal: whatsapp-outgoing
const outgoingChannel = getChannel('whatsapp-outgoing');
outgoingChannel
    .listen('.MessageSent', (e) => {
        alert('Mensaje enviado');
        console.log("MessageSent:", e);
    })
    .listen('.MessageFailed', (e) => {
        alert('Error al enviar mensaje');
        console.log("MessageFailed:", e);
    })
    .listen('.TemplateMessageSent', (e) => {
        alert('Plantilla de mensaje enviada');
        console.log("TemplateMessageSent:", e);
    });

// Canal: whatsapp-templates
const templatesChannel = getChannel('whatsapp-templates');
templatesChannel
    .listen('.TemplateCreated', (e) => {
        alert('Plantilla creada');
        console.log("TemplateCreated:", e);
    })
    .listen('.TemplateApproved', (e) => {
        alert('Plantilla aprobada');
        console.log("TemplateApproved:", e);
    })
    .listen('.TemplateRejected', (e) => {
        alert('Plantilla rechazada');
        console.log("TemplateRejected:", e);
    });

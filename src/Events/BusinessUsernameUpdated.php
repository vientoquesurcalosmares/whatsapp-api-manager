<?php

namespace ScriptDevelop\WhatsappManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando el estado del nombre de usuario de empresa cambia.
 *
 * Llega del webhook `business_username_update` cuando:
 * - Un nombre de usuario es aprobado (`approved`)
 * - Un nombre de usuario es eliminado (`deleted`)
 * - Un nombre de usuario es reservado (`reserved`)
 *
 * Payload de $data:
 * - display_phone_number  string  Número de teléfono de la empresa
 * - username              string  El nombre de usuario afectado (ausente si status = deleted)
 * - status                string  approved | deleted | reserved
 */
class BusinessUsernameUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        $channelName = 'whatsapp-messages';

        return config('whatsapp.broadcast_channel_type') === 'private'
            ? new PrivateChannel($channelName)
            : new Channel($channelName);
    }

    public function broadcastAs(): string
    {
        return 'BusinessUsernameUpdated';
    }
}

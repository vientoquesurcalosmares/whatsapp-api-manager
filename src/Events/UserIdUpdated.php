<?php

namespace ScriptDevelop\WhatsappManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando el BSUID de un usuario cambia.
 *
 * Llega del webhook `user_id_update` cuando el usuario cambia su número de teléfono
 * en WhatsApp, lo que regenera su BSUID.
 *
 * Payload de $data:
 * - wa_id              string|null  Número de teléfono del usuario (puede omitirse si tiene username activo)
 * - previous_bsuid     string       BSUID anterior del usuario
 * - current_bsuid      string       Nuevo BSUID del usuario
 * - previous_parent_bsuid  string|null  BSUID principal anterior (si aplica)
 * - current_parent_bsuid   string|null  Nuevo BSUID principal (si aplica)
 * - timestamp          string       Marca de tiempo del webhook
 * - display_phone_number   string   Número de teléfono de la empresa
 * - phone_number_id    string       ID del número de teléfono de la empresa
 */
class UserIdUpdated implements ShouldBroadcast
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
        return 'UserIdUpdated';
    }
}

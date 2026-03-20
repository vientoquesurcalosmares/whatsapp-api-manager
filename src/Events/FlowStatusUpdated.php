<?php

namespace ScriptDevelop\WhatsappManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class FlowStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Datos del evento (payload de Meta sobre el estado del Flow)
     */
    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Canal de transmisión.
     */
    public function broadcastOn(): Channel
    {
        // Usamos un canal específico para administración/monitoreo de flujos
        $channelName = 'whatsapp-flows-monitor';

        return config('whatsapp.broadcast_channel_type') === 'private'
            ? new PrivateChannel($channelName)
            : new Channel($channelName);
    }

    /**
     * Nombre del evento en el cliente (JS).
     */
    public function broadcastAs(): string
    {
        return 'FlowStatusUpdated';
    }
}
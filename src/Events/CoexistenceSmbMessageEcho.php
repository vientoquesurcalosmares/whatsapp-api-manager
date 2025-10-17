<?php

namespace ScriptDevelop\WhatsappManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CoexistenceSmbMessageEcho implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        $channelName = 'whatsapp-coexistence';

        return config('whatsapp.broadcast_channel_type') === 'private'
            ? new PrivateChannel($channelName)
            : new Channel($channelName);
    }

    public function broadcastAs(): string
    {
        return 'CoexistenceSmbMessageEcho';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'smb_message_echo',
            'data' => $this->data,
            'timestamp' => now()->toISOString()
        ];
    }
}
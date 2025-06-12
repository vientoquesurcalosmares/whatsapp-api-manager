<?php

namespace Scriptdevelop\WhatsappManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        $channelName = 'whatsapp.outgoing';

        return config('whatsapp.broadcast_channel_type') === 'private'
            ? new PrivateChannel($channelName)
            : new Channel($channelName);
    }

    public function broadcastAs(): string
    {
        return 'message.failed';
    }
}

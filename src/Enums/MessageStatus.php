<?php

namespace ScriptDevelop\WhatsappManager\Enums;

enum MessageStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    
    public static function fromApiStatus(string $apiStatus): self
    {
        return match(strtolower($apiStatus)) {
            'sent' => self::SENT,
            'delivered' => self::DELIVERED,
            'read' => self::READ,
            'failed' => self::FAILED,
            default => self::PENDING
        };
    }
}
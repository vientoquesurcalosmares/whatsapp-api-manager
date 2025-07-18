<?php

namespace ScriptDevelop\WhatsappManager\Enums;

enum MessageStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
    case RECEIVED = 'received';
    case DELETED = 'deleted';
    case WARNING = 'warning';
    
    public static function fromApiStatus(string $apiStatus): self
    {
        return match(strtolower($apiStatus)) {
            'sent' => self::SENT,
            'delivered' => self::DELIVERED,
            'read' => self::READ,
            'failed' => self::FAILED,
            'received' => self::RECEIVED,
            'deleted' => self::DELETED,
            'warning' => self::WARNING,
            default => self::PENDING
        };
    }
}
<?php


namespace ScriptDevelop\WhatsappManager\Enums;

enum ConditionType: string
{
    case EQUALS = 'equals';
    case CONTAINS = 'contains';
    case REGEX = 'regex';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case BETWEEN = 'between';
    
    public static function validationRules(): array
    {
        return [
            self::EQUALS->value => 'string',
            self::CONTAINS->value => 'string',
            self::REGEX->value => 'regex',
            self::GREATER_THAN->value => 'numeric',
            self::LESS_THAN->value => 'numeric',
            self::BETWEEN->value => 'array'
        ];
    }
}


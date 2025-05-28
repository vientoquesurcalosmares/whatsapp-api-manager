<?php

namespace ScriptDevelop\WhatsappManager\Enums;

enum StepType: string
{
    //'message_sequence','open_question','closed_question','conditional','terminal','api_call'
    case MESSAGE_SEQUENCE = 'message_sequence';       // Paso que solo envía mensajes
    case OPEN_QUESTION = 'open_question';           // Paso que recolecta entrada del usuario
    case CLOSED_QUESTION = 'closed_question';           // Paso que recolecta entrada del usuario
    case CONDITIONAL = 'conditional'; // Paso con lógica condicional
    case TERMINAL = 'terminal';      // Paso final que termina el flujo
    case API_CALL = 'api_call';      // Paso fcon acciones de aplicacion API

    public function requiresInteraction(): bool
    {
        return in_array($this, [
            self::CLOSED_QUESTION,
            self::CONDITIONAL,
            self::API_CALL
        ]);
    }
    
    public static function interactiveMessageTypes(): array
    {
        return ['buttons', 'list', 'quick_reply'];
    }
}
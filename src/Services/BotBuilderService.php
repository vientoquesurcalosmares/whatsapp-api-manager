<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappBot;
use Illuminate\Support\Str;

class BotBuilderService
{
    /**
     * Crea un nuevo bot de WhatsApp.
     */
    public function createBot(array $data): WhatsappBot
    {
        return WhatsappBot::create([
            'bot_name'         => $data['name'],
            'phone_number_id'  => $data['phone_number_id'],
            'description'      => $data['description'] ?? null,
            'on_failure'       => $data['failure_message'] ?? 'Disculpa, no entendí tu mensaje.',
            'is_enable'        => true,
            'default_flow_id'  => $data['default_flow_id'] ?? null,
        ]);
    }

    /**
     * Recupera un bot por ID.
     */
    public function getById(string $botId): ?WhatsappBot
    {
        return WhatsappBot::find($botId);
    }

    /**
     * Lista todos los bots activos.
     */
    public function getActiveBots()
    {
        return WhatsappBot::where('is_enable', true)->get();
    }

    /**
     * Activa o desactiva un bot.
     */
    public function setStatus(string $botId, bool $status): bool
    {
        $bot = $this->getById($botId);
        if (!$bot) return false;

        $bot->is_enable = $status;
        return $bot->save();
    }

    /**
     * Asigna un flujo por defecto.
     */
    public function setDefaultFlow(string $botId, string $flowId): bool
    {
        $bot = $this->getById($botId);
        if (!$bot) return false;

        $bot->default_flow_id = $flowId;
        return $bot->save();
    }

    /**
     * Actualiza la respuesta por defecto en caso de error.
     */
    public function setFailureResponse(string $botId, string $response): bool
    {
        $bot = $this->getById($botId);
        if (!$bot) return false;

        $bot->on_failure = $response;
        return $bot->save();
    }
}

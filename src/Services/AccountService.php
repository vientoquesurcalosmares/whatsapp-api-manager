<?php

namespace ScriptDevelop\WhatsappManager\Services;

use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;

class AccountService
{
    public function register(array $data): WhatsappBusinessAccount
    {
        // Validar datos
        if (empty($data['api_token'])) {
            throw new \InvalidArgumentException('API token is required');
        }

        // Lógica de registro
        return WhatsappBusinessAccount::updateOrCreate(
            ['whatsapp_business_id' => $data['business_id']],
            $data
        );
    }
}
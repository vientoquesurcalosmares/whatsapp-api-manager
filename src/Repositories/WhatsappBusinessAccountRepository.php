<?php

namespace ScriptDevelop\WhatsappManager\Repositories;

use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WhatsappBusinessAccountRepository
{
    /**
     * Encuentra una cuenta empresarial por ID.
     */
    public function find(string $id): WhatsappBusinessAccount
    {
        $account = WhatsappBusinessAccount::find($id);

        if (!$account) {
            throw new ModelNotFoundException("Whatsapp Business Account $id no encontrada");
        }

        return $account;
    }

    /**
     * Encuentra una cuenta por número de teléfono.
     */
    public function findByPhoneNumberId(string $phoneNumberId): WhatsappBusinessAccount
    {
        return WhatsappBusinessAccount::where('phone_number_id', $phoneNumberId)->firstOrFail();
    }
}
<?php

namespace ScriptDevelop\WhatsappManager\Repositories;

//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

class WhatsappBusinessAccountRepository
{
    /**
     * Encuentra una cuenta empresarial por ID.
     */
    public function find(string $id): Model
    {
        $account = WhatsappModelResolver::business_account()->find($id);

        if (!$account) {
            throw new ModelNotFoundException("Whatsapp Business Account $id no encontrada");
        }

        return $account;
    }

    /**
     * Encuentra una cuenta por número de teléfono.
     */
    public function findByPhoneNumberId(string $phoneNumberId): Model
    {
        return WhatsappModelResolver::business_account()->where('phone_number_id', $phoneNumberId)->firstOrFail();
    }
}
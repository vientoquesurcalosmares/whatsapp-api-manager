<?php

namespace ScriptDevelop\WhatsappManager\Repositories;

//use ScriptDevelop\WhatsappManager\Models\WhatsappBusinessAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Support\WhatsappModelResolver;

class WhatsappBusinessAccountRepository
{
    /**
     * Find a business account by ID.
     */
    public function find(string $id): Model
    {
        $account = WhatsappModelResolver::business_account()->find($id);

        if (!$account) {
            throw new ModelNotFoundException("Whatsapp Business Account $id not found");
        }

        return $account;
    }

    /**
     * Find an account by phone number ID.
     */
    public function findByPhoneNumberId(string $phoneNumberId): Model
    {
        return WhatsappModelResolver::business_account()->where('phone_number_id', $phoneNumberId)->firstOrFail();
    }
}
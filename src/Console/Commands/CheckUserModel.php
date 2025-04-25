<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;

class CheckUserModel extends Command
{
    protected $signature = 'whatsapp:check-user-model';
    protected $description = 'Verifica si el modelo User está configurado correctamente';

    public function handle()
    {
        $userModel = config('whatsapp.models.user_model');

        if (!class_exists($userModel)) {
            $this->error("El modelo User ($userModel) no existe.");
            return 1;
        }

        $this->info("Modelo User configurado correctamente: $userModel");
        return 0;
    }
}
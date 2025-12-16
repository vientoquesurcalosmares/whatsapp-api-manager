<?php

namespace ScriptDevelop\WhatsappManager\Console\Commands;

use Illuminate\Console\Command;

class CheckUserModel extends Command
{
    protected $signature = 'whatsapp:check-user-model';
    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = whatsapp_trans('console.check_user_model_description');
    }

    public function handle()
    {
        $userModel = config('whatsapp.models.user_model');

        if (!class_exists($userModel)) {
            $this->error(whatsapp_trans('console.user_model_not_found', ['model' => $userModel]));
            return 1;
        }

        $this->info(whatsapp_trans('console.user_model_configured', ['model' => $userModel]));
        return 0;
    }
}
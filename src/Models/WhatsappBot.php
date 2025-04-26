<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappBot extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'whatsapp_bot_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'bot_name',
        'port',
        'url',
    ];

    public function phoneNumbers()
    {
        return $this->hasMany(WhatsappPhoneNumber::class, 'whatsapp_bot_id', 'whatsapp_bot_id');
    }

    public static function getBotsConfig()
    {
        return self::with('phoneNumbers.businessAccount')->get()->map(function ($bot) {
            return [
                'flows' => ['dxFlow', 'welcomeFlow', 'registerFlow', 'fullSamplesFlow'],
                'jwtToken' => $bot->phoneNumbers->first()->businessAccount->api_token,
                'numberId' => $bot->phoneNumbers->first()->phone_number_id,
                'verifyToken' => config('whatsapp-manager.webhook.verify_token'),
                'version' => env('WHATSAPP_API_VERSION'),
                'dbHost' => env('DB_HOST'),
                'dbUser' => env('DB_USERNAME'),
                'dbName' => env('DB_DATABASE'),
                'dbPassword' => env('DB_PASSWORD'),
                'port' => $bot->port,
            ];
        })->toArray();
    }

    // Relación con Flujos
    public function flows()
    {
        return $this->hasMany(Flow::class, 'bot_id');
    }
}

<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappPhoneNumber extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'phone_number_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_business_account_id',
        'whatsapp_business_profile_id',
        'whatsapp_bot_id',
        'display_phone_number',
        'api_phone_number_id',
        'verified_name',
    ];

    public function bot()
    {
        return $this->belongsTo(WhatsappBot::class, 'whatsapp_bot_id', 'whatsapp_bot_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'whatsapp_phone_id');
    }

    public function businessAccount()
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }

    public function businessProfile()
    {
        return $this->belongsTo(
            WhatsappBusinessProfile::class,
            'whatsapp_business_profile_id', 
            'whatsapp_business_profile_id'
        );
    }

    public function contacts()
    {
        return $this->hasManyThrough(Contact::class, Message::class, 'whatsapp_phone_id', 'contact_id', 'whatsapp_phone_id', 'contact_id')
                    ->distinct();
    }

    // Relación con Sesiones de chat
    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class, 'whatsapp_phone_id');
    }
}

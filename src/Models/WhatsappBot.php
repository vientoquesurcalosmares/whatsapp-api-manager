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
        'phone_number_id',
        'bot_name',
        'description',
        'is_enable',
        'default_flow_id',
        'on_failure',
    ];

    public function phoneNumbers()
    {
        return $this->hasMany(WhatsappPhoneNumber::class, 'whatsapp_bot_id', 'whatsapp_bot_id');
    }

    // Relación con Flujos
    public function flows()
    {
        return $this->hasMany(Flow::class, 'whatsapp_bot_id');
    }
}

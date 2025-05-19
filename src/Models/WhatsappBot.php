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
        'failure_message',
    ];

    public function phoneNumber()
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'phone_number_id', 'phone_number_id');
    }

    // Relación con Flujos
    public function flows()
    {
        return $this->belongsToMany(Flow::class, 'whatsapp_bot_id');
    }
}

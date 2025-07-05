<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowSession extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_sessions';
    protected $primaryKey = 'flow_session_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'user_phone',
        'flow_token',
        'current_screen',
        'collected_data',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'collected_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function flow()
    {
        return $this->belongsTo(config('whatsapp.models.flow'), 'flow_id', 'flow_id');
    }

    public function responses()
    {
        return $this->hasMany(config('whatsapp.models.flow_response'), 'session_id', 'flow_session_id');
    }

    public function events()
    {
        return $this->hasMany(config('whatsapp.models.flow_event'), 'session_id', 'flow_session_id');
    }
}

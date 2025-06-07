<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowEvent extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    public $timestamps = false;

    protected $primaryKey = 'flow_event_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'event_type',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(WhatsappFlowSession::class, 'session_id', 'flow_session_id');
    }
}

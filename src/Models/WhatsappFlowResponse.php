<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowResponse extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_responses';
    protected $primaryKey = 'flow_response_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'screen_id',
        'element_name',
        'response_value',
        'responded_at',
    ];

    protected $dates = ['responded_at'];

    public function session()
    {
        return $this->belongsTo(config('whatsapp.models.flow_session'), 'session_id', 'flow_session_id');
    }

    public function screen()
    {
        return $this->belongsTo(config('whatsapp.models.flow_screen'), 'screen_id', 'screen_id');
    }
}

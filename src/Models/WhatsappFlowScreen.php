<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowScreen extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_screens';
    protected $primaryKey = 'screen_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'name',
        'title',
        'content',
        'is_start',
        'order',
        'validation_rules',
        'next_screen_logic',
        'extra_logic',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'next_screen_logic' => 'array',
        'extra_logic' => 'array',
    ];

    public function flow()
    {
        return $this->belongsTo(WhatsappFlow::class, 'flow_id', 'flow_id');
    }

    public function elements()
    {
        return $this->hasMany(WhatsappScreenElement::class, 'screen_id', 'screen_id');
    }
}

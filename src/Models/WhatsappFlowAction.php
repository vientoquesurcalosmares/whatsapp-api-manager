<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowAction extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_actions';
    protected $primaryKey = 'flow_action_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'name',
        'action_type',
        'trigger',
        'trigger_screen',
        'is_enabled',
        'execution_order',
        'config',
        'retry_config',
    ];

    protected $casts = [
        'config'       => 'array',
        'retry_config' => 'array',
        'is_enabled'   => 'boolean',
    ];

    /**
     * The flow this action belongs to.
     */
    public function flow()
    {
        return $this->belongsTo(
            config('whatsapp.models.flow'),
            'flow_id',
            'flow_id'
        );
    }

    /**
     * Scope: only enabled actions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope: filter by trigger type (on_complete, on_screen).
     */
    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }

    /**
     * Scope: order by execution_order ascending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('execution_order');
    }
}

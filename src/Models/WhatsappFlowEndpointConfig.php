<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowEndpointConfig extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_endpoint_configs';
    protected $primaryKey = 'flow_endpoint_config_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'is_enabled',
        'mode',
        'webhook_url',
        'webhook_timeout_ms',
        'webhook_secret',
        'handler_class',
        'auto_config',
        'metadata',
    ];

    protected $casts = [
        'is_enabled'  => 'boolean',
        'auto_config' => 'array',
        'metadata'    => 'array',
    ];

    /**
     * The flow this endpoint config belongs to.
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
     * Whether this config uses automatic mode (no-code transitions).
     */
    public function isAutoMode(): bool
    {
        return $this->mode === 'auto';
    }

    /**
     * Whether this config proxies requests to an external webhook URL.
     */
    public function isWebhookMode(): bool
    {
        return $this->mode === 'webhook';
    }

    /**
     * Whether this config delegates to a custom PHP handler class.
     */
    public function isClassMode(): bool
    {
        return $this->mode === 'class';
    }
}

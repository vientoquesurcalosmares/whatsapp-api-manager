<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappBusinessAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_business_accounts';
    protected $primaryKey = 'whatsapp_business_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_business_id',
        'phone_number_id',
        'name',
        'api_token',
        'app_id',
        'app_name',
        'app_link',
        'currency',
        'webhook_token',
        'timezone_id',
        'message_template_namespace',
        'status', // Nuevo campo
        'partner_app_id', // agregar a fillable
        'disconnected_at', // Nuevo campo
        'fully_removed_at', // Nuevo campo
        'disconnection_reason', // Nuevo campo
    ];

    protected $casts = [
        'disconnected_at' => 'datetime',
        'fully_removed_at' => 'datetime',
    ];

    public function setApiTokenAttribute($value) {
        if ($value !== null) {
            $this->attributes['api_token'] = encrypt($value);
        }
    }
    
    public function getApiTokenAttribute($value) {
        if ($value === null) {
            return null;
        }
        return decrypt($value);
    }

    public function phoneNumbers()
    {
        return $this->hasMany(config('whatsapp.models.phone_number'), 'whatsapp_business_account_id', 'whatsapp_business_id');
    }

    public function templates()
    {
        return $this->hasMany(config('whatsapp.models.template'), 'whatsapp_business_id');
    }

    // Scopes para filtrar por estado
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDisconnected($query)
    {
        return $query->where('status', 'disconnected');
    }

    public function scopeRemoved($query)
    {
        return $query->where('status', 'removed');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDisconnected(): bool
    {
        return $this->status === 'disconnected';
    }

    public function isRemoved(): bool
    {
        return $this->status === 'removed';
    }
}
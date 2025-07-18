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
        'message_template_namespace'
    ];

    public function setApiTokenAttribute($value) {
        $this->attributes['api_token'] = encrypt($value);
    }
    public function getApiTokenAttribute($value) {
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
}

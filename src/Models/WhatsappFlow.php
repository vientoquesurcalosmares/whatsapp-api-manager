<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlow extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'flow_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_business_account_id',
        'name',
        'wa_flow_id',
        'flow_type',
        'description',
        'json_structure',
        'status',
        'version',
    ];

    protected $casts = [
        'json_structure' => 'array',
    ];

    public function screens()
    {
        return $this->hasMany(WhatsappFlowScreen::class, 'flow_id', 'flow_id');
    }

    public function templates()
    {
        return $this->belongsToMany(
            Template::class,
            'whatsapp_template_flows',
            'flow_id',
            'template_id'
        );
    }

    public function sessions()
    {
        return $this->hasMany(WhatsappFlowSession::class, 'flow_id', 'flow_id');
    }

    public function businessAccount()
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id', 'whatsapp_business_account_id');
    }
}

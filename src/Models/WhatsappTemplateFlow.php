<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappTemplateFlow extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_flows';
    protected $primaryKey = 'template_flow_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'flow_id',
        'flow_button_label',
        'flow_variables',
    ];

    protected $casts = [
        'flow_variables' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(config('whatsapp.models.template'), 'template_id', 'template_id');
    }

    public function flow()
    {
        return $this->belongsTo(config('whatsapp.models.flow'), 'flow_id', 'flow_id');
    }
}

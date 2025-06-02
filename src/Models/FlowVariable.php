<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class FlowVariable extends Model
{
    use HasFactory, GeneratesUlid;

    protected $table = 'whatsapp_flow_variables';
    protected $primaryKey = 'variable_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'name',
        'type',
        'default_value',
    ];

    protected $casts = [
        'default_value' => 'json',
    ];

    // Relación inversa: una variable pertenece a un flujo
    public function flow()
    {
        return $this->belongsTo(Flow::class, 'flow_id', 'flow_id');
    }
}

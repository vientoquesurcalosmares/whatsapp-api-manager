<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class FlowStep extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'step_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'step_type',
        'validation_rules',
        'max_attempts',
        'retry_message',
        'failure_action',
        'failure_step_id',
        'is_terminal',
        'is_entry_point',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'is_terminal' => 'boolean',
    ];

    // Relaciones

    // FlowStep pertenece a un flujo
    public function flow()
    {
        return $this->belongsTo(Flow::class, 'flow_id', 'flow_id');
    }

    public function messages() {
        return $this->hasMany(StepMessage::class, 'flow_step_id');
    }

    public function variables() {
        return $this->hasMany(StepVariable::class, 'flow_step_id');
    }

    public function getNextStep($input) {
        // Evaluar conditions para determinar siguiente paso
    }

    // Si se requiere, se puede definir la relación con el siguiente paso
    public function nextStep()
    {
        return $this->belongsTo(FlowStep::class, 'next_step_id', 'step_id');
    }

    // Relación con Respuestas de usuario
    public function userResponses()
    {
        return $this->hasMany(UserResponse::class, 'flow_step_id', 'step_id');
    }

    // Validación para pasos terminales
    protected static function booted()
    {
        static::saving(function ($step) {
            if ($step->is_terminal && !is_null($step->next_step_id)) {
                throw new \Exception('Un paso terminal no puede tener next_step_id');
            }
        });
    }
}

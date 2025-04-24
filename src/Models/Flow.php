<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Flow extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'flow_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'bot_id',
        'name',
        'trigger_keywords',       // Nuevo: almacena un arreglo de palabras clave
        'is_case_sensitive', // Nuevo: define si la comparación es sensible a mayúsculas/minúsculas
        'is_default',
    ];

    protected $casts = [
        'trigger_keywords' => 'array', // Convertir JSON a array
        'is_case_sensitive' => 'boolean',
    ];

    // Validación al guardar: si el flujo no es sensible a mayúsculas, se guardan las palabras clave en minúsculas.
    protected static function booted()
    {
        static::saving(function ($flow) {
            if (!$flow->is_case_sensitive && !empty($flow->trigger_keywords)) {
                $flow->trigger_keywords = array_map('strtolower', $flow->trigger_keywords);
            }
        });
    }


    public function matchesTrigger(string $message): bool
    {
        $message = trim($message);
        $messageToCheck = $this->is_case_sensitive ? $message : strtolower($message);

        foreach ($this->trigger_keywords as $keyword) {
            $keywordToMatch = $this->is_case_sensitive ? $keyword : strtolower($keyword);
            if ($keywordToMatch === $messageToCheck) {
                return true;
            }
        }
        return false;
    }

    // Scope para buscar flujos por palabra clave
    public function scopeTriggeredBy(Builder $query, string $message): Builder
    {
        return $query->whereJsonContains('trigger_keywords', $message);
    }

    // Relaciones

    // Flow pertenece a un bot
    public function bot()
    {
        return $this->belongsTo(WhatsappBot::class, 'bot_id', 'whatsapp_bot_id');
    }

    // Flow tiene muchos pasos
    public function steps()
    {
        return $this->hasMany(FlowStep::class, 'flow_id', 'flow_id');
    }

    // Accesor para obtener el paso inicial dinámicamente
    public function getInitialStepAttribute()
    {
        return $this->steps()->orderBy('order')->first();
    }
}

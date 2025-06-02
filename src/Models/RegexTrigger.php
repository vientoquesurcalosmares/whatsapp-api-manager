<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;
use Illuminate\Support\Facades\Log;

class RegexTrigger extends Model
{
    use HasFactory, SoftDeletes, GeneratesUlid;

    protected $table = 'whatsapp_regex_triggers';
    protected $primaryKey = 'regex_trigger_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pattern',
        'flags',
        'match_full'
    ];

    protected $casts = [
        'match_full' => 'boolean'
    ];

    public function trigger()
    {
        return $this->morphOne(FlowTrigger::class, 'triggerable');
    }

    public function matches(string $text): bool
    {
        $pattern = $this->pattern;
        $flags = $this->flags ?? '';
        
        try {
            if ($this->match_full) {
                return preg_match("/^{$pattern}$/{$flags}", $text) === 1;
            }
            return preg_match("/{$pattern}/{$flags}", $text) === 1;
        } catch (\Exception $e) {
            Log::error("Error en regex: {$pattern}", ['error' => $e]);
            return false;
        }
    }
}
<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class RegexTrigger extends Model
{
    use HasFactory, SoftDeletes, GeneratesUlid;

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
}
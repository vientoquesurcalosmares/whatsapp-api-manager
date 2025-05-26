<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;


class KeywordTrigger extends Model
{
    use HasFactory, SoftDeletes, GeneratesUlid;

    protected $fillable = [
        'keywords',
        'case_sensitive',
        'match_type'
    ];

    protected $casts = [
        'keywords' => 'array',
        'case_sensitive' => 'boolean'
    ];

    public function trigger()
    {
        return $this->morphOne(FlowTrigger::class, 'triggerable');
    }
}
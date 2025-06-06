<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappScreenElement extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'screen_element_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'screen_id',
        'type',
        'name',
        'label',
        'placeholder',
        'default_value',
        'options',
        'style_json',
        'required',
        'validation',
        'next_screen',
    ];

    protected $casts = [
        'options' => 'array',
        'style_json' => 'array',
        'validation' => 'array',
    ];

    public function screen()
    {
        return $this->belongsTo(WhatsappFlowScreen::class, 'screen_id', 'screen_id');
    }
}

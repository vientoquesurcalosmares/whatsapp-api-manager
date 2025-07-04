<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TemplateLanguage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_template_languages';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', // Ej: en_US
        'name',
        'language_code',
        'country_code',
        'variant',
    ];

    public function templates()
    {
        return $this->hasMany(config('whatsapp.models.template'), 'language', 'id');
    }
}

<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class TemplateComponent extends Model
{
    use HasFactory;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_components';
    protected $primaryKey = 'component_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'type', // Ej: header, body, footer, button
        'content', // Contenido del componente
        'parameters', // Parámetros dinámicos
    ];

    protected $casts = [
        'content' => 'array',
        'parameters' => 'array',
    ];

    /**
     * Relación con la plantilla.
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id', 'template_id');
    }
}
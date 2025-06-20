<?php

namespace ScriptDevelop\WhatsappManager\Models;

use ScriptDevelop\WhatsappManager\Services\TemplateEditor;
use ScriptDevelop\WhatsappManager\WhatsappApi\ApiClient;
use ScriptDevelop\WhatsappManager\Services\TemplateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Template extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_templates';
    protected $primaryKey = 'template_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_business_id',
        'wa_template_id',
        'name',
        'language',
        'category_id',
        'status',
        'file',
        'json',
        'rejection_reason',
    ];

    public function businessAccount()
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_id');
    }

    /**
     * Relación con la categoría de la plantilla.
     */
    public function category()
    {
        return $this->belongsTo(TemplateCategory::class, 'category_id', 'category_id'); // Usar 'category_id'
    }

    public function languageData()
    {
        return $this->belongsTo(TemplateLanguage::class, 'language', 'id');
    }

    public function components()
    {
        return $this->hasMany(TemplateComponent::class, 'template_id', 'template_id');
    }

    public function flows()
    {
        return $this->belongsToMany(
            WhatsappFlow::class, 'whatsapp_template_flows', 'template_id', 'flow_id'
        );
    }

    /**
     * Scope para buscar plantillas activas.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Método para obtener el contenido de la plantilla en un idioma específico.
     */
    public function getContentByLanguage(string $language): ?array
    {
        return $this->json['languages'][$language] ?? null;
    }

    /**
     * Inicia el editor de plantillas para esta instancia
     */
    public function edit(): TemplateEditor
    {
        return app(TemplateEditor::class, [
            'template' => $this,
            'apiClient' => app(ApiClient::class),
            'templateService' => app(TemplateService::class)
        ]);
    }

    // Relación con versiones
    public function versions()
    {
        return $this->hasMany(TemplateVersion::class, 'template_id');
    }

    // Relación con la última versión aprobada
    public function activeVersion()
    {
        return $this->hasOne(TemplateVersion::class, 'template_id')
            ->where('status', 'APPROVED')
            ->latest();
    }
}

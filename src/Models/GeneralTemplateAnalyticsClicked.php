<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralTemplateAnalyticsClicked extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_general_template_analytics_clicked';

    protected $fillable = [
        'template_analytics_id',
        'type',
        'button_content',
        'count',
    ];

    protected $casts = [
        'template_analytics_id' => 'integer',
        'count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con GeneralTemplateAnalyticsClicked
     */
    public function templateAnalytics(): BelongsTo
    {
        return $this->belongsTo(config('whatsapp.models.general_template_analytics'), 'template_analytics_id');
    }

    /**
     * Scope para filtrar por tipo de click
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para obtener solo clicks de botones URL
     */
    public function scopeUrlButtons($query)
    {
        return $query->whereIn('type', ['url_button', 'unique_url_button']);
    }

    /**
     * Scope para obtener solo clicks de respuestas rápidas
     */
    public function scopeQuickReplies($query)
    {
        return $query->where('type', 'quick_reply');
    }

    /**
     * Scope para filtrar clicks con conteo mayor a cero
     */
    public function scopeWithClicks($query)
    {
        return $query->where('count', '>', 0);
    }

    /**
     * Verificar si es un click único
     */
    public function getIsUniqueClickAttribute(): bool
    {
        return str_contains($this->type, 'unique_');
    }

    /**
     * Verificar si es un click de botón URL
     */
    public function getIsUrlButtonAttribute(): bool
    {
        return in_array($this->type, ['url_button', 'unique_url_button']);
    }

    /**
     * Verificar si es una respuesta rápida
     */
    public function getIsQuickReplyAttribute(): bool
    {
        return $this->type === 'quick_reply';
    }
}
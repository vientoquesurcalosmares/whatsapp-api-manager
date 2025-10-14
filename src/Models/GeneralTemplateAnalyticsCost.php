<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralTemplateAnalyticsCost extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_general_template_analytics_cost';
    protected $primaryKey = 'id';

    protected $fillable = [
        'general_template_analytics_id',
        'type',
        'value',
        'currency',
    ];

    protected $casts = [
        'general_template_analytics_id' => 'integer',
        'value' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con GeneralTemplateAnalytics
     */
    public function templateAnalytics(): BelongsTo
    {
        return $this->belongsTo(config('whatsapp.models.general_template_analytics'), 'template_analytics_id');
    }

    /**
     * Scope para filtrar por tipo de costo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para obtener solo gastos totales
     */
    public function scopeAmountSpent($query)
    {
        return $query->where('type', 'amount_spent');
    }

    /**
     * Scope para obtener costo por entrega
     */
    public function scopeCostPerDelivered($query)
    {
        return $query->where('type', 'cost_per_delivered');
    }

    /**
     * Scope para obtener costo por click en botón URL
     */
    public function scopeCostPerUrlButtonClick($query)
    {
        return $query->where('type', 'cost_per_url_button_click');
    }

    /**
     * Scope para filtrar costos con valor
     */
    public function scopeWithValue($query)
    {
        return $query->whereNotNull('value')->where('value', '>', 0);
    }

    /**
     * Scope para filtrar costos sin valor
     */
    public function scopeWithoutValue($query)
    {
        return $query->whereNull('value');
    }

    /**
     * Scope para filtrar por moneda
     */
    public function scopeByCurrency($query, string $currency = 'USD')
    {
        return $query->where('currency', $currency);
    }

    /**
     * Verificar si el costo tiene valor
     */
    public function getHasValueAttribute(): bool
    {
        return !is_null($this->value) && $this->value > 0;
    }

    /**
     * Obtener el valor formateado con moneda
     */
    public function getFormattedValueAttribute(): string
    {
        if (!$this->has_value) {
            return 'N/A';
        }

        return $this->currency . ' ' . number_format($this->value, 4);
    }

    /**
     * Verificar si es un gasto total
     */
    public function getIsAmountSpentAttribute(): bool
    {
        return $this->type === 'amount_spent';
    }

    /**
     * Verificar si es costo por entrega
     */
    public function getIsCostPerDeliveredAttribute(): bool
    {
        return $this->type === 'cost_per_delivered';
    }

    /**
     * Verificar si es costo por click
     */
    public function getIsCostPerClickAttribute(): bool
    {
        return $this->type === 'cost_per_url_button_click';
    }
}
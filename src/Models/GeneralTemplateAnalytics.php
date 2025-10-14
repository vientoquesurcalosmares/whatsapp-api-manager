<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class GeneralTemplateAnalytics extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_general_template_analytics';
    protected $primaryKey = 'id';

    protected $fillable = [
        'wa_template_id',
        'granularity',
        'product_type',
        'start_timestamp',
        'end_timestamp',
        'start_date',
        'end_date',
        'sent',
        'delivered',
        'read',
        'json_data',
    ];

    protected $casts = [
        'start_timestamp' => 'integer',
        'end_timestamp' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'sent' => 'integer',
        'delivered' => 'integer',
        'read' => 'integer',
        'json_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con Template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(config('whatsapp.models.template'), 'wa_template_id', 'wa_template_id');
    }

    /**
     * Relación con los datos de clicks
     */
    public function clickedData(): HasMany
    {
        return $this->hasMany(config('whatsapp.models.general_template_analytics_clicked'), 'template_analytics_id');
    }

    /**
     * Relación con los datos de costos
     */
    public function costData(): HasMany
    {
        return $this->hasMany(config('whatsapp.models.general_template_analytics_cost'), 'template_analytics_id');
    }

    /**
     * Accessor para obtener la fecha de inicio en Carbon
     */
    public function getStartDateCarbonAttribute(): Carbon
    {
        return Carbon::createFromTimestamp($this->start_timestamp);
    }

    /**
     * Accessor para obtener la fecha de fin en Carbon
     */
    public function getEndDateCarbonAttribute(): Carbon
    {
        return Carbon::createFromTimestamp($this->end_timestamp);
    }

    /**
     * Scope para filtrar por template
     */
    public function scopeForTemplate($query, string $templateId)
    {
        return $query->where('wa_template_id', $templateId);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Scope para filtrar por período usando timestamps
     */
    public function scopeTimestampRange($query, int $startTimestamp, int $endTimestamp)
    {
        return $query->where('start_timestamp', '>=', $startTimestamp)
                    ->where('start_timestamp', '<', $endTimestamp);
    }

    /**
     * Scope para obtener datos de la semana actual
     */
    public function scopeCurrentWeek($query)
    {
        $startOfWeek = now()->startOfWeek()->format('Y-m-d');
        $today = now()->format('Y-m-d');

        return $query->whereBetween('start_date', [$startOfWeek, $today]);
    }

    /**
     * Scope para obtener datos del mes actual
     */
    public function scopeCurrentMonth($query)
    {
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $today = now()->format('Y-m-d');

        return $query->whereBetween('start_date', [$startOfMonth, $today]);
    }

    /**
     * Calcular el engagement rate
     */
    public function getEngagementRateAttribute(): float
    {
        if ($this->delivered == 0) {
            return 0.0;
        }

        return round(($this->read / $this->delivered) * 100, 2);
    }

    /**
     * Calcular el delivery rate
     */
    public function getDeliveryRateAttribute(): float
    {
        if ($this->sent == 0) {
            return 0.0;
        }

        return round(($this->delivered / $this->sent) * 100, 2);
    }
}
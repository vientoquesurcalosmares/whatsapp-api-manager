<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

/**
 * Campaign Model
 *
 * @property string $campaign_id
 * @property string $whatsapp_business_account_id
 * @property string $template_id
 * @property string $name
 * @property string $message_content
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string $status
 * @property int $total_recipients
 * @property array|null $filters
 */
class Campaign extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    // Definición de la tabla y la clave primaria
    protected $table = 'whatsapp_campaigns';
    // Definición de la clave primaria
    protected $primaryKey = 'campaign_id';
    // Definición de la clave primaria como no autoincremental
    public $incrementing = false;
    // Definición del tipo de la clave primaria
    protected $keyType = 'string';

    // Definición de los campos que se pueden asignar masivamente
    protected $fillable = [
        'whatsapp_business_account_id',
        'template_id',
        'name',
        'message_content',
        'type',
        'scheduled_at',
        'status',
        'total_recipients',
        'filters'
    ];

    // Definición de los campos que se deben tratar como fechas
    protected $casts = [
        'scheduled_at' => 'datetime',
        'filters' => 'json'
    ];

    // Relaciones
    /**
     * Relación con la cuenta de WhatsApp Business.
     *
     * @return BelongsTo
     */
    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(
            WhatsappBusinessAccount::class,
            'whatsapp_business_account_id',
            'whatsapp_business_id'
        );
    }

    /**
     * Relación con la plantilla.
     *
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id', 'template_id');
    }

    /**
     * Relación con los contactos.
     *
     * @return BelongsToMany
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'campaign_contact')
            ->withPivot([
                'status',
                'sent_at',
                'delivered_at',
                'read_at',
                'response_count',
                'error_details'
            ]);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(CampaignMetric::class, 'campaign_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Método para actualizar las métricas de la campaña.
     *
     * @return void
     */
    public function updateMetrics(): void
    {
        $this->load('whatsapp_contacts');

        $this->metric()->updateOrCreate(
            ['campaign_id' => $this->campaign_id],
            [
                'sent' => $this->contacts()->wherePivot('status', 'SENT')->count(),
                'delivered' => $this->contacts()->wherePivot('status', 'DELIVERED')->count(),
                'read' => $this->contacts()->wherePivot('status', 'READ')->count(),
                'failed' => $this->contacts()->wherePivot('status', 'FAILED')->count()
            ]
        );
    }

    /**
     * Método para programar los mensajes de la campaña.
     *
     * @return void
     */
    public function scheduleMessages(): void
    {
        $this->contacts()->each(function ($contact) {
            $this->contacts()->updateExistingPivot($contact->contact_id, [
                'status' => 'SCHEDULED'
            ]);
        });
    }
}

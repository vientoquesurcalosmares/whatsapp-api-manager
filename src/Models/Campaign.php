<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_campaigns';
    protected $primaryKey = 'campaign_id';
    public $incrementing = false;
    protected $keyType = 'string';

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

    protected $casts = [
        'scheduled_at' => 'datetime',
        'filters' => 'json'
    ];

    // Relaciones
    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(
            WhatsappBusinessAccount::class,
            'whatsapp_business_account_id',
            'whatsapp_business_id'
        );
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id', 'template_id');
    }

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

    // Métodos de negocio
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function updateMetrics(): void
    {
        $this->load('contacts');

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

    public function scheduleMessages(): void
    {
        $this->contacts()->each(function ($contact) {
            $this->contacts()->updateExistingPivot($contact->contact_id, [
                'status' => 'SCHEDULED'
            ]);
        });
    }
}

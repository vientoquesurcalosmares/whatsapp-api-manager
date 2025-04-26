<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class CampaignMetric extends Model
{
    use HasFactory;
    use GeneratesUlid;

    protected $primaryKey = 'campaign_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sent',
        'delivered',
        'read',
        'failed',
        'positive_responses',
        'negative_responses',
        'opt_outs'
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    // Métodos analíticos
    public function calculateEngagementRate(): float
    {
        $totalResponses = $this->positive_responses + $this->negative_responses;
        return $this->delivered > 0 
            ? round(($totalResponses / $this->delivered) * 100, 2)
            : 0;
    }

    public function deliverySuccessRate(): float
    {
        return $this->sent > 0
            ? round(($this->delivered / $this->sent) * 100, 2)
            : 0;
    }
}

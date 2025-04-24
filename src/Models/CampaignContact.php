<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CampaignContact extends Pivot
{
    protected $table = 'campaign_contact';

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime'
    ];

    public function markAsSent()
    {
        $this->update([
            'status' => 'SENT',
            'sent_at' => now()
        ]);
    }

    public function recordResponse(bool $isPositive)
    {
        $this->increment('response_count');

        if($isPositive) {
            $this->campaign->metric()->increment('positive_responses');
        } else {
            $this->campaign->metric()->increment('negative_responses');
        }
    }
}

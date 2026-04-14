<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowSession extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_sessions';
    protected $primaryKey = 'flow_session_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flow_id',
        'user_phone',
        'flow_token',
        'current_screen',
        'collected_data',
        'status',
        'expires_at',
        // Campos de recolección de datos (migration 2026_04_06_000001)
        'phone_number_id',
        'contact_id',
        'sent_by_user_id',
        'send_method',
        'is_organic',
        'intermediate_data',
        'completed_at',
        'abandoned_at',
    ];

    protected $casts = [
        'collected_data'   => 'array',
        'expires_at'       => 'datetime',
        'intermediate_data' => 'array',
        'is_organic'        => 'boolean',
        'completed_at'      => 'datetime',
        'abandoned_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones base
    // -------------------------------------------------------------------------

    public function flow()
    {
        return $this->belongsTo(config('whatsapp.models.flow'), 'flow_id', 'flow_id');
    }

    public function responses()
    {
        return $this->hasMany(config('whatsapp.models.flow_response'), 'session_id', 'flow_session_id');
    }

    public function events()
    {
        return $this->hasMany(config('whatsapp.models.flow_event'), 'session_id', 'flow_session_id');
    }

    // -------------------------------------------------------------------------
    // Relaciones nuevas (ownership)
    // -------------------------------------------------------------------------

    public function phoneNumber()
    {
        return $this->belongsTo(
            config('whatsapp.models.phone_number'),
            'phone_number_id',
            'phone_number_id'
        );
    }

    public function contact()
    {
        return $this->belongsTo(
            config('whatsapp.models.contact'),
            'contact_id',
            'contact_id'
        );
    }

    public function sentByUser()
    {
        return $this->belongsTo(
            config('whatsapp.models.user_model'),
            'sent_by_user_id'
        );
    }

    // -------------------------------------------------------------------------
    // Métodos de lifecycle
    // -------------------------------------------------------------------------

    /**
     * Mark the session as completed, merging final form data into collected_data.
     * Combines intermediate screen data with the final nfm_reply payload.
     */
    public function markAsCompleted(array $finalData = []): void
    {
        $existing     = $this->collected_data ?? [];
        $intermediate = $this->intermediate_data ?? [];
        $merged       = array_merge($intermediate, $existing, $finalData);

        $this->update([
            'status'         => 'completed',
            'completed_at'   => now(),
            'collected_data' => $merged,
        ]);
    }

    /**
     * Mark the session as abandoned (status=failed, abandoned_at=now).
     */
    public function markAsAbandoned(): void
    {
        $this->update([
            'status'       => 'failed',
            'abandoned_at' => now(),
        ]);
    }

    /**
     * Accumulate data from an intermediate screen (Data API).
     * Updates intermediate_data[$screenName] and current_screen.
     */
    public function mergeIntermediateData(string $screenName, array $screenData): void
    {
        $current = $this->intermediate_data ?? [];
        $current[$screenName] = $screenData;

        $this->update([
            'intermediate_data' => $current,
            'current_screen'    => $screenName,
        ]);
    }

    /**
     * Whether the session has been completed by the user.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Whether the flow session arrived via an organic channel (link/QR/Ad).
     */
    public function isOrganic(): bool
    {
        return (bool) $this->is_organic;
    }

    /**
     * Duration in seconds from session start to completion.
     * Returns null if the session has not been completed yet.
     */
    public function getDurationSeconds(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return (int) $this->created_at->diffInSeconds($this->completed_at);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeAbandoned($query)
    {
        return $query->where('status', 'failed')->whereNotNull('abandoned_at');
    }

    public function scopeOrganic($query)
    {
        return $query->where('is_organic', true);
    }

    public function scopeByPhoneNumber($query, string $phoneNumberId)
    {
        return $query->where('phone_number_id', $phoneNumberId);
    }
}

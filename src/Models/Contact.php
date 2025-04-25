<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Contact extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $primaryKey = 'contact_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'wa_id',
        'country_code',
        'phone_number',
        'contact_name',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'prefix',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'contact_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'contact_id')->orderBy('created_at', 'desc');
    }

    public function unreadMessagesCountByContact()
    {
        return $this->messages()->whereNull('read_at')->where('message_method', 'INPUT')->count();
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_contact')
            ->withPivot('status', 'sent_at', 'delivered_at', 'read_at');
    }

    public function campaignResponses()
    {
        return $this->hasManyThrough(
            CampaignMetric::class,
            CampaignContact::class,
            'contact_id',
            'campaign_id'
        );
    }

    // Relación con Sesiones de chat
    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class, 'contact_id');
    }

    // Relación con Respuestas de usuario
    public function userResponses()
    {
        return $this->hasMany(UserResponse::class, 'contact_id');
    }

    public function getFullNameAttribute(): string {
        return trim("{$this->prefix} {$this->first_name} {$this->middle_name} {$this->last_name} {$this->suffix}");
    }
}

<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Services\BlockService;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Contact extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_contacts';
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
        'organization',
        'department',
        'title',
        'city',
        'state',
        'zip',
        'country',
        'birthday',
        'addresses',
        'emails',
        'phones',
        'urls',
        'accepts_marketing',
        'marketing_opt_out_at'
    ];

    protected $casts = [
        'addresses' => 'array',
        'emails' => 'array',
        'phones' => 'array',
        'urls' => 'array',
        'birthday' => 'date',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'contact_id');
    }

    public function latestMessage($phoneNumberId)
    {
        return $this->messages()
            ->where('whatsapp_phone_id', $phoneNumberId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function unreadMessagesCountByContact()
    {
        return $this->messages()->whereNull('read_at')->where('message_method', 'INPUT')->count();
    }

    public function getFullNameAttribute(): string {
        return trim("{$this->prefix} {$this->first_name} {$this->middle_name} {$this->last_name} {$this->suffix}");
    }

    public function hasOptedOutOfMarketing(): bool
    {
        return !$this->accepts_marketing && $this->marketing_opt_out_at !== null;
    }

    public function blockedStatuses()
    {
        return $this->hasMany(BlockedUser::class, 'contact_id');
    }

    public function isBlockedOn(string $phoneNumberId): bool
    {
        return $this->blockedStatuses()
            ->where('phone_number_id', $phoneNumberId)
            ->whereNull('unblocked_at')
            ->exists();
    }

    public function blockOn(string $phoneNumberId): bool
    {
        if ($this->isBlockedOn($phoneNumberId)) {
            return false;
        }
        
        $service = app(BlockService::class);
        $response = $service->blockUsers($phoneNumberId, [$this->wa_id]);
        return $response['success'] ?? false;
    }

    public function unblockOn(string $phoneNumberId): bool
    {
        if (!$this->isBlockedOn($phoneNumberId)) {
            return false;
        }
        
        $service = app(BlockService::class);
        $response = $service->unblockUsers($phoneNumberId, [$this->wa_id]);
        return $response['success'] ?? false;
    }
}

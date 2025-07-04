<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class BlockedUser extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_blocked_users';
    protected $primaryKey = 'blocked_user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'phone_number_id',
        'contact_id',
        'user_wa_id',
        'blocked_at',
        'unblocked_at'
    ];

    public function phoneNumber()
    {
        return $this->belongsTo(config('whatsapp.models.phone_number'), 'phone_number_id');
    }

    public function contact()
    {
        return $this->belongsTo(config('whatsapp.models.contact'), 'contact_id');
    }
}
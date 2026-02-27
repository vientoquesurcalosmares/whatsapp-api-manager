<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappContactProfile extends Model
{
    use SoftDeletes, GeneratesUlid;

    protected $table = 'whatsapp_contact_profiles';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'phone_number_id',
        'contact_id',
        'profile_picture',
        'alias',
        'contact_name',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'prefix',
        'organization',
        'department',
        'title',
        'birthday',
        'last_interaction_at',
        'metadata',
    ];

    protected $casts = [
        'birthday'             => 'date',
        'last_interaction_at'  => 'datetime',
        'metadata'             => 'array',
    ];

    // Relación con el número de teléfono
    public function phoneNumber()
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'phone_number_id');
    }

    // Relación con el contacto base
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
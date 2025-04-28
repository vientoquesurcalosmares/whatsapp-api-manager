<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class Website extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;


    protected $table = 'whatgsapp_websites';
    protected $primaryKey = 'website_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_business_profile_id',
        'website',
    ];

    public function businessProfile()
    {
        return $this->belongsTo(WhatsappBusinessProfile::class, 'whatsapp_business_profile_id');
    }
}

<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappQrCode extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_qr_codes';
    protected $primaryKey = 'qr_id';

    protected $fillable = [
        'phone_number_id',
        'code',
        'prefilled_message',
        'deep_link_url',
        'qr_image_url',
        'qr_image_path',
        'qr_image_format',
    ];

    public function phoneNumber()
    {
        return $this->belongsTo(config('whatsapp.models.phone_number'), 'phone_number_id', 'phone_number_id');
    }
}

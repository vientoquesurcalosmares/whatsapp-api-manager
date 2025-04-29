<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;
use ScriptDevelop\WhatsappManager\Enums\MessageStatus;

class Message extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_messages';
    protected $primaryKey = 'message_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'whatsapp_phone_id',
        'contact_id',
        'conversation_id',
        'wa_id',
        'messaging_product',
        'message_method',
        'message_from',
        'message_to',
        'message_type',
        'message_content',
        'media_url',
        'message_context',
        'message_context_id',
        'message_context_from',
        'caption',
        'json_content',
        'status',
        'delivered_at',
        'read_at',
        'edited_at',
        'failed_at',
        'code_error',
        'title_error',
        'message_error',
        'details_error',
        'json',
    ];

    protected $casts = [
        'status' => MessageStatus::class,
        'json_content' => 'array',
        'json' => 'array'
    ];
    

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function phoneNumber()
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'whatsapp_phone_id');
    }

    public function mediaFiles()
    {
        return $this->hasMany(MediaFile::class, 'message_id');
    }

    public function parentMessage()
    {
        return $this->belongsTo(Message::class, 'context_message_id', 'wa_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'context_message_id', 'wa_id');
    }
}

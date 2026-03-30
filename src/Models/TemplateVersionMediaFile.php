<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class TemplateVersionMediaFile extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_media_files';
    protected $primaryKey = 'template_media_file_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'version_id',
        'media_type',
        'file_name',
        'mime_type',
        'sha256',
        'url',
        'media_id',
        'file_size',
        'animated',
    ];

    public function version()
    {
        return $this->belongsTo(config('whatsapp.models.template_version'), 'version_id', 'version_id');
    }
}

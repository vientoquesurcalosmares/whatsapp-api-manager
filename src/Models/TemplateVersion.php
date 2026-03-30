<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class TemplateVersion extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_versions';
    protected $primaryKey = 'version_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'version_hash',
        'template_structure',
        'status',
        'is_active',
        'rejection_reason',
    ];

    protected $casts = [
        'template_structure' => 'array',
        'is_active' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(config('whatsapp.models.template'), 'template_id');
    }

    public function mediaFiles()
    {
        return $this->hasMany(config('whatsapp.models.template_media_file'), 'version_id', 'version_id');
    }

    public function latestMediaFile()
    {
        return $this->hasOne(config('whatsapp.models.template_media_file'), 'version_id', 'version_id')
            ->orderBy('created_at', 'desc');
    }
}

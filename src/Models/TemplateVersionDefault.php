<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class TemplateVersionDefault extends Model
{
    use HasFactory;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_version_default';
    protected $primaryKey = 'default_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'version_id',
    ];

    public static function upsertDefault(string $templateId, string $versionId): self
    {
        return static::query()->updateOrCreate(
            ['template_id' => $templateId],
            ['version_id' => $versionId]
        );
    }

    public function template()
    {
        return $this->belongsTo(config('whatsapp.models.template'), 'template_id', 'template_id');
    }

    public function version()
    {
        return $this->belongsTo(config('whatsapp.models.template_version'), 'version_id', 'version_id');
    }
}

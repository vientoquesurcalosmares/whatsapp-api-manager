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
        'rejection_reason',
    ];

    protected $casts = [
        'template_structure' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }
}

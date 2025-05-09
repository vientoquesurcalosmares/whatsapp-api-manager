<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class TemplateCategory extends Model
{
    use HasFactory;
    use GeneratesUlid;

    protected $table = 'whatsapp_template_categories';
    protected $primaryKey = 'category_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Relación con las plantillas.
     */
    public function templates()
    {
        return $this->hasMany(Template::class, 'category_id', 'category_id');
    }
}
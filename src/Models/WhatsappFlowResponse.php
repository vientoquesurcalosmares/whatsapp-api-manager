<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;

class WhatsappFlowResponse extends Model
{
    use HasFactory, SoftDeletes;
    use GeneratesUlid;

    protected $table = 'whatsapp_flow_responses';
    protected $primaryKey = 'flow_response_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'screen_id',
        'element_name',
        'response_value',
        'responded_at',
        // Campos de enriquecimiento (migration 2026_04_06_000002)
        'phone_number_id',
        'contact_id',
        'screen_name',
        'field_type',
        'raw_value',
        'display_value',
    ];

    protected $dates = ['responded_at'];

    // -------------------------------------------------------------------------
    // Relaciones base
    // -------------------------------------------------------------------------

    public function session()
    {
        return $this->belongsTo(config('whatsapp.models.flow_session'), 'session_id', 'flow_session_id');
    }

    public function screen()
    {
        return $this->belongsTo(config('whatsapp.models.flow_screen'), 'screen_id', 'screen_id');
    }

    // -------------------------------------------------------------------------
    // Relaciones nuevas (ownership)
    // -------------------------------------------------------------------------

    public function phoneNumber()
    {
        return $this->belongsTo(
            config('whatsapp.models.phone_number'),
            'phone_number_id',
            'phone_number_id'
        );
    }

    public function contact()
    {
        return $this->belongsTo(
            config('whatsapp.models.contact'),
            'contact_id',
            'contact_id'
        );
    }

    // -------------------------------------------------------------------------
    // Métodos helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the best available human-readable value for this response.
     * Priority: display_value → raw_value → empty string.
     */
    public function getFormattedValue(): string
    {
        if ($this->display_value !== null) {
            return $this->display_value;
        }

        if ($this->raw_value !== null) {
            return $this->raw_value;
        }

        return '';
    }
}

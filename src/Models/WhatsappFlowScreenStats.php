<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WhatsappFlowScreenStats extends Model
{
    // NO usa SoftDeletes — tabla de stats, los registros no se eliminan lógicamente.
    // NO usa GeneratesUlid — PK es bigint autoincrement.

    protected $table = 'whatsapp_flow_screen_stats';
    protected $primaryKey = 'stat_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'flow_id',
        'phone_number_id',
        'screen_id',
        'screen_name',
        'stat_date',
        'views_count',
        'completions_count',
        'drop_off_count',
        'avg_time_on_screen_ms',
    ];

    protected $casts = [
        'stat_date' => 'date',
    ];

    /**
     * The flow these stats belong to.
     */
    public function flow()
    {
        return $this->belongsTo(
            config('whatsapp.models.flow'),
            'flow_id',
            'flow_id'
        );
    }

    /**
     * The phone number these stats are segmented by.
     */
    public function phoneNumber()
    {
        return $this->belongsTo(
            config('whatsapp.models.phone_number'),
            'phone_number_id',
            'phone_number_id'
        );
    }

    /**
     * Atomically increment the views counter for today.
     * Uses upsert so concurrent requests don't lose counts.
     *
     * @param  string      $flowId
     * @param  string      $screenName
     * @param  string|null $phoneNumberId
     */
    public static function incrementViews(
        string  $flowId,
        string  $screenName,
        ?string $phoneNumberId = null
    ): void {
        static::upsert(
            [[
                'flow_id'           => $flowId,
                'screen_name'       => $screenName,
                'phone_number_id'   => $phoneNumberId,
                'screen_id'         => null,
                'stat_date'         => now()->toDateString(),
                'views_count'       => 1,
                'completions_count' => 0,
                'drop_off_count'    => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]],
            ['flow_id', 'phone_number_id', 'screen_name', 'stat_date'],
            [
                'views_count' => DB::raw('whatsapp_flow_screen_stats.views_count + 1'),
                'updated_at'  => now(),
            ]
        );
    }

    /**
     * Atomically increment the completions counter for today.
     *
     * @param  string      $flowId
     * @param  string      $screenName
     * @param  string|null $phoneNumberId
     */
    public static function incrementCompletions(
        string  $flowId,
        string  $screenName,
        ?string $phoneNumberId = null
    ): void {
        static::upsert(
            [[
                'flow_id'           => $flowId,
                'screen_name'       => $screenName,
                'phone_number_id'   => $phoneNumberId,
                'screen_id'         => null,
                'stat_date'         => now()->toDateString(),
                'views_count'       => 0,
                'completions_count' => 1,
                'drop_off_count'    => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]],
            ['flow_id', 'phone_number_id', 'screen_name', 'stat_date'],
            [
                'completions_count' => DB::raw('whatsapp_flow_screen_stats.completions_count + 1'),
                'updated_at'        => now(),
            ]
        );
    }

    /**
     * Atomically increment the drop-off counter for today.
     *
     * @param  string      $flowId
     * @param  string      $screenName
     * @param  string|null $phoneNumberId
     */
    public static function incrementDrops(
        string  $flowId,
        string  $screenName,
        ?string $phoneNumberId = null
    ): void {
        static::upsert(
            [[
                'flow_id'           => $flowId,
                'screen_name'       => $screenName,
                'phone_number_id'   => $phoneNumberId,
                'screen_id'         => null,
                'stat_date'         => now()->toDateString(),
                'views_count'       => 0,
                'completions_count' => 0,
                'drop_off_count'    => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]],
            ['flow_id', 'phone_number_id', 'screen_name', 'stat_date'],
            [
                'drop_off_count' => DB::raw('whatsapp_flow_screen_stats.drop_off_count + 1'),
                'updated_at'     => now(),
            ]
        );
    }
}

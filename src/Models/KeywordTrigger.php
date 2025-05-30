<?php

namespace ScriptDevelop\WhatsappManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\WhatsappManager\Traits\GeneratesUlid;


class KeywordTrigger extends Model
{
    use HasFactory, SoftDeletes, GeneratesUlid;

    protected $primaryKey = 'keyword_trigger_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'keywords',
        'case_sensitive',
        'match_type'
    ];

    protected $casts = [
        'keywords' => 'array',
        'case_sensitive' => 'boolean'
    ];

    public function trigger()
    {
        return $this->morphOne(FlowTrigger::class, 'triggerable');
    }

    public function matches(string $text): bool
    {
        $keywords = $this->keywords;
        $text = mb_strtolower($text);

        $matchType = $this->match_type;
        $caseSensitive = $this->case_sensitive;

        if (!$caseSensitive) {
            $text = mb_strtolower($text);
            $keywords = array_map('mb_strtolower', $keywords);
        }

        foreach ($keywords as $keyword) {
            switch ($matchType) {
                case 'exact':
                    if ($text === $keyword) return true;
                    break;
                case 'contains':
                    if (mb_strpos($text, $keyword) !== false) return true;
                    break;
                case 'starts_with':
                    if (mb_strpos($text, $keyword) === 0) return true;
                    break;
                case 'ends_with':
                    if (mb_strpos($text, $keyword) === (mb_strlen($text) - mb_strlen($keyword))) return true;
                    break;
            }
        }
        
        return false;
    }
}
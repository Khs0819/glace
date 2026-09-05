<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * A long-form HTML page edited in the dashboard: terms, privacy.
 *
 * The body is sanitised on the way in, not just on the way out, so a payload
 * that got past the editor is never persisted in the first place. The frontend
 * sanitises again independently — handoff 16 is explicit that neither side
 * treats the other's pass as sufficient.
 */
class SiteContent extends Model
{
    public const KEY_TERMS   = 'terms';
    public const KEY_PRIVACY = 'privacy';

    protected $keyType = 'string';
    public $incrementing = false;
    protected $primaryKey = 'key';

    protected $fillable = ['key', 'title', 'body'];

    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $value === null ? null : HtmlSanitizer::clean($value);
    }

    public static function body(string $key): string
    {
        $content = static::find($key);

        // Sanitised again on read: rows written before this model existed, or
        // by a direct SQL edit, have never been through the setter.
        return $content?->body === null ? '' : HtmlSanitizer::clean($content->body);
    }
}

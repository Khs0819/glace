<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends Model
{
    /** The two lists a scoop of ice cream is chosen from. */
    public const SCOOP_CLASSIC = 'classic';
    public const SCOOP_SPECIAL = 'special';

    public const SCOOP_FAMILIES = [
        self::SCOOP_CLASSIC => 'كلاسيك',
        self::SCOOP_SPECIAL => 'سبيشال',
    ];

    protected $fillable = [
        'product_id', 'slug', 'label', 'price',
        'available', 'type', 'max_qty', 'sort_order', 'scoop_family',
    ];

    protected $casts = [
        'price' => 'float',
        'available' => 'boolean',
        'max_qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** A scoop of ice cream, rather than an ordinary addon row. */
    public function isScoop(): bool
    {
        return in_array($this->scoop_family, [self::SCOOP_CLASSIC, self::SCOOP_SPECIAL], true);
    }
}

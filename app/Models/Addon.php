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
        'available', 'type', 'max_qty', 'sort_order', 'scoop_family', 'flavor_id',
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

    /**
     * The flavour this scoop is, from القائمة ← النكهات.
     *
     * Null on every ordinary addon — a sauce is not a flavour — and on a scoop
     * whose flavour has since been deleted.
     */
    public function flavor(): BelongsTo
    {
        return $this->belongsTo(Flavor::class);
    }

    /** A scoop of ice cream, rather than an ordinary addon row. */
    public function isScoop(): bool
    {
        return in_array($this->scoop_family, [self::SCOOP_CLASSIC, self::SCOOP_SPECIAL], true);
    }

    /**
     * Sellable right now.
     *
     * Two different questions, and they used to be one switch:
     *  - `available` — is this offered on this product at all? A catalog
     *    decision, made once, and what «تعطيل الكل» writes.
     *  - the flavour's own switch — do we have it today? The cashier's, set in
     *    القائمة ← النكهات, and true everywhere the flavour appears.
     *
     * A scoop whose flavour was deleted answers no: it has a price and a label
     * but nothing behind them, and selling it would be selling a ghost.
     */
    public function orderable(): bool
    {
        if (! $this->available) {
            return false;
        }

        return $this->flavor_id === null || (bool) $this->flavor?->available;
    }

    /** What to call this scoop — the flavour's name, or the last one it had. */
    public function scoopLabel(): string
    {
        return $this->flavor?->name_ar ?: (string) $this->label;
    }

    /**
     * Which of the two lists the storefront draws this scoop in.
     *
     * The flavour decides, so moving one from كلاسيك to سبيشال in النكهات moves
     * it on every product that offers it. A flavour in a family the storefront
     * has no list for (ستيفيا) keeps the row where it was rather than vanishing
     * into a third list nothing draws.
     */
    public function scoopFamily(): ?string
    {
        $family = $this->flavor?->family;

        return isset(self::SCOOP_FAMILIES[$family]) ? $family : $this->scoop_family;
    }
}

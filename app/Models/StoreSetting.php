<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value store for runtime settings the dashboard can toggle.
 *
 * Every read goes through a 60-second cache so the API never hits the
 * database just to find out if the store is open.
 */
class StoreSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    // ── Read helpers ──────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("store_setting:{$key}", 60, function () use ($key, $default) {
            $record = static::find($key);
            return $record?->value ?? $default;
        });
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        return filter_var(static::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    // ── Write helper ─────────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget("store_setting:{$key}");
    }

    // ── Convenience ──────────────────────────────────────────────────────

    /** Open right now: the weekly hours, or a manager's override. */
    public static function isStoreOpen(): bool
    {
        return app(\App\Services\Storefront\StoreHours::class)->isStoreOpen();
    }

    /** Delivery open right now — never while the shop itself is closed. */
    public static function isDeliveryOpen(): bool
    {
        return app(\App\Services\Storefront\StoreHours::class)->isDeliveryOpen();
    }

    public static function autoConfirmMinutes(): int
    {
        return static::getInt('auto_confirm_minutes', 30);
    }

    public static function closedMessage(): string
    {
        return static::get('closed_message', 'المتجر مغلق حالياً') ?: 'المتجر مغلق حالياً';
    }

    public static function deliveryClosedMessage(): string
    {
        return static::get('delivery_closed_message', 'التوصيل غير متاح حالياً — يمكنك الاستلام من المحل')
            ?: 'التوصيل غير متاح حالياً — يمكنك الاستلام من المحل';
    }
}

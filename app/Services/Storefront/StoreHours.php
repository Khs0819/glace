<?php

namespace App\Services\Storefront;

use App\Models\StoreSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * When the shop and its delivery are open.
 *
 * Two independent weekly schedules — the shop, and delivery — each with one
 * period per day, which may run past midnight (14:00 → 02:00). The schedule
 * decides the state. A manager can override it for a limited time, and the
 * override lapses on its own so a shop is never left closed because somebody
 * forgot to switch it back.
 *
 * Everything is evaluated in the shop's own timezone. The application runs in
 * UTC, and comparing opening hours against UTC would open and close the shop
 * two or three hours off.
 *
 * Delivery can never be open while the shop is closed.
 */
class StoreHours
{
    public const SCOPES = ['store', 'delivery'];

    /** Carbon day of week => [API key, Arabic name], in display order (Saturday first). */
    public const DAYS = [
        6 => ['sat', 'السبت'],
        0 => ['sun', 'الأحد'],
        1 => ['mon', 'الإثنين'],
        2 => ['tue', 'الثلاثاء'],
        3 => ['wed', 'الأربعاء'],
        4 => ['thu', 'الخميس'],
        5 => ['fri', 'الجمعة'],
    ];

    /** How long a manual override lasts. */
    public const DURATIONS = [
        'schedule' => 'حتى الموعد التالي في الجدول',
        '1'        => 'لمدة ساعة',
        '2'        => 'لمدة ساعتين',
        '4'        => 'لمدة 4 ساعات',
        'manual'   => 'حتى أعيده للجدول يدوياً',
    ];

    private const TIME = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function timezone(): string
    {
        return (string) config('storefront.timezone', 'Asia/Gaza');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone($this->timezone());
    }

    // ─── the schedule ───────────────────────────────────────────────────────

    /**
     * The week for one scope, keyed by Carbon day of week.
     *
     * A day that was never configured is open around the clock — how the shop
     * behaved before hours existed — so nothing closes on the day this ships.
     * `open` equal to `close` means the whole day.
     *
     * @return array<int, array{enabled: bool, open: string, close: string}>
     */
    public function schedule(string $scope): array
    {
        $this->assertScope($scope);

        $stored = json_decode((string) StoreSetting::get("{$scope}_schedule", ''), true);
        $stored = is_array($stored) ? $stored : [];

        $week = [];

        foreach (array_keys(self::DAYS) as $dow) {
            $day = $stored[$dow] ?? null;

            $week[$dow] = is_array($day) && $this->validTime($day['open'] ?? null) && $this->validTime($day['close'] ?? null)
                ? ['enabled' => (bool) ($day['enabled'] ?? false), 'open' => $day['open'], 'close' => $day['close']]
                : ['enabled' => true, 'open' => '00:00', 'close' => '00:00'];
        }

        return $week;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $days
     *
     * @throws InvalidArgumentException naming the day that is wrong
     */
    public function saveSchedule(string $scope, array $days): void
    {
        $this->assertScope($scope);

        $week = [];

        foreach (self::DAYS as $dow => [, $label]) {
            $day     = $days[$dow] ?? $days[(string) $dow] ?? [];
            $enabled = filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $open    = (string) ($day['open'] ?? '');
            $close   = (string) ($day['close'] ?? '');

            if ($enabled && (! $this->validTime($open) || ! $this->validTime($close))) {
                throw new InvalidArgumentException("أدخل ساعة الفتح والإغلاق ليوم {$label}");
            }

            $week[$dow] = [
                'enabled' => $enabled,
                // A closed day keeps whatever valid times it had, so re-enabling
                // it later does not start from nothing.
                'open'    => $this->validTime($open) ? $open : '00:00',
                'close'   => $this->validTime($close) ? $close : '00:00',
            ];
        }

        StoreSetting::set("{$scope}_schedule", json_encode($week));
    }

    /**
     * What the schedule alone says, ignoring any override.
     *
     * @return array{open: bool, opensAt: ?CarbonImmutable, closesAt: ?CarbonImmutable, nextChangeAt: ?CarbonImmutable}
     */
    public function scheduledStatus(string $scope, ?CarbonInterface $at = null): array
    {
        $at = $at ? CarbonImmutable::instance($at)->setTimezone($this->timezone()) : $this->now();

        // Far enough ahead that a period still running at this point never
        // closes within the week the schedule describes.
        $horizon = $at->startOfDay()->addDays(8);

        foreach ($this->intervals($scope, $at) as [$start, $end]) {
            if ($at >= $start && $at < $end) {
                $closesAt = $end >= $horizon ? null : $end;

                return ['open' => true, 'opensAt' => null, 'closesAt' => $closesAt, 'nextChangeAt' => $closesAt];
            }

            if ($start > $at) {
                return ['open' => false, 'opensAt' => $start, 'closesAt' => null, 'nextChangeAt' => $start];
            }
        }

        return ['open' => false, 'opensAt' => null, 'closesAt' => null, 'nextChangeAt' => null];
    }

    // ─── overrides ──────────────────────────────────────────────────────────

    /**
     * The override in force, or null when the schedule decides.
     *
     * @return array{state: string, until: ?CarbonImmutable}|null
     */
    public function override(string $scope): ?array
    {
        $this->assertScope($scope);

        $state = StoreSetting::get("{$scope}_override_state");

        // Never set from the new controls: honour the old on/off switch, so a
        // shop closed before this shipped stays closed until a manager acts.
        if ($state === null) {
            return StoreSetting::getBool("{$scope}_open", true) ? null : ['state' => 'closed', 'until' => null];
        }

        if (! in_array($state, ['open', 'closed'], true)) {
            return null;
        }

        $until   = (string) StoreSetting::get("{$scope}_override_until", '');
        $untilAt = $until === '' ? null : CarbonImmutable::parse($until)->setTimezone($this->timezone());

        if ($untilAt !== null && $untilAt <= $this->now()) {
            return null;
        }

        return ['state' => $state, 'until' => $untilAt];
    }

    public function setOverride(string $scope, string $state, ?CarbonInterface $until): void
    {
        $this->assertScope($scope);

        if (! in_array($state, ['open', 'closed'], true)) {
            throw new InvalidArgumentException('حالة غير صحيحة');
        }

        StoreSetting::set("{$scope}_override_state", $state);
        StoreSetting::set("{$scope}_override_until", $until ? CarbonImmutable::instance($until)->utc()->toIso8601String() : '');
    }

    public function clearOverride(string $scope): void
    {
        $this->assertScope($scope);

        StoreSetting::set("{$scope}_override_state", '');
        StoreSetting::set("{$scope}_override_until", '');
    }

    /**
     * When an override chosen now should end.
     *
     * "schedule" ends it at the next point the schedule changes. When the
     * forced state already matches the schedule — keeping the shop open while
     * it is open — the change that matters is the one after that: stay open
     * through tonight's closing, until the schedule opens again.
     */
    public function overrideUntil(string $scope, string $state, string $duration): ?CarbonImmutable
    {
        if (in_array($duration, ['1', '2', '4'], true)) {
            return $this->now()->addHours((int) $duration);
        }

        if ($duration !== 'schedule') {
            return null;
        }

        $schedule = $this->scheduledStatus($scope);

        if ($schedule['open'] !== ($state === 'open')) {
            return $schedule['nextChangeAt'];
        }

        if ($schedule['nextChangeAt'] === null) {
            return null;
        }

        return $this->scheduledStatus($scope, $schedule['nextChangeAt']->addSecond())['nextChangeAt'];
    }

    // ─── the answer ─────────────────────────────────────────────────────────

    /**
     * Whether a scope is open right now, why, and when that changes.
     *
     * @return array<string, mixed>
     */
    public function status(string $scope): array
    {
        $schedule = $this->scheduledStatus($scope);
        $override = $this->override($scope);

        if ($override === null) {
            $open     = $schedule['open'];
            $opensAt  = $schedule['opensAt'];
            $closesAt = $schedule['closesAt'];
        } else {
            $open = $override['state'] === 'open';

            // What happens when the override lapses is the schedule at that
            // moment, so the next change is read from there.
            $after    = $override['until'] ? $this->scheduledStatus($scope, $override['until']) : null;
            $opensAt  = $open ? null : ($after ? ($after['open'] ? $override['until'] : $after['opensAt']) : null);
            $closesAt = $open ? ($after ? ($after['open'] ? $after['closesAt'] : $override['until']) : null) : null;
        }

        $result = [
            'open'         => $open,
            'source'       => $override ? 'override' : 'schedule',
            'override'     => $override ? [
                'state' => $override['state'],
                'until' => $override['until']?->toIso8601String(),
            ] : null,
            'scheduleOpen' => $schedule['open'],
            'opensAt'      => $opensAt?->toIso8601String(),
            'closesAt'     => $closesAt?->toIso8601String(),
            'nextChangeAt' => ($open ? $closesAt : $opensAt)?->toIso8601String(),
            'today'        => $this->dayForApi($scope, $this->now()->dayOfWeek),
        ];

        if ($scope === 'delivery' && $open) {
            $store = $this->status('store');

            if (! $store['open']) {
                $result['open']         = false;
                $result['source']       = 'store_closed';
                $result['opensAt']      = $store['opensAt'];
                $result['closesAt']     = null;
                $result['nextChangeAt'] = $store['opensAt'];
            }
        }

        return $result;
    }

    public function isStoreOpen(): bool
    {
        return $this->status('store')['open'];
    }

    public function isDeliveryOpen(): bool
    {
        return $this->status('delivery')['open'];
    }

    /**
     * The week in display order, for the storefront.
     *
     * @return array<int, array<string, mixed>>
     */
    public function weekForApi(string $scope): array
    {
        return array_map(
            fn (int $dow) => $this->dayForApi($scope, $dow),
            array_keys(self::DAYS),
        );
    }

    // ─── internals ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function dayForApi(string $scope, int $dow): array
    {
        $day = $this->schedule($scope)[$dow];

        return [
            'day'     => self::DAYS[$dow][0],
            'label'   => self::DAYS[$dow][1],
            'enabled' => $day['enabled'],
            'open'    => $day['enabled'] ? $day['open'] : null,
            'close'   => $day['enabled'] ? $day['close'] : null,
            'allDay'  => $day['enabled'] && $day['open'] === $day['close'],
        ];
    }

    /**
     * Opening periods from yesterday to a week ahead, merged where they touch.
     *
     * Yesterday is included because its period may still be running after
     * midnight; merging makes back-to-back periods (every day around the clock)
     * read as one long opening instead of closing for an instant at midnight.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function intervals(string $scope, CarbonImmutable $at): array
    {
        $week = $this->schedule($scope);
        $base = $at->startOfDay();
        $raw  = [];

        for ($offset = -1; $offset <= 7; $offset++) {
            $day = $base->addDays($offset);
            $cfg = $week[$day->dayOfWeek];

            if (! $cfg['enabled']) {
                continue;
            }

            [$openHour, $openMinute]   = array_map('intval', explode(':', $cfg['open']));
            [$closeHour, $closeMinute] = array_map('intval', explode(':', $cfg['close']));

            $start = $day->setTime($openHour, $openMinute);

            $end = match (true) {
                $cfg['open'] === $cfg['close'] => $day->addDay()->setTime($openHour, $openMinute),
                $cfg['close'] < $cfg['open']   => $day->addDay()->setTime($closeHour, $closeMinute),
                default                        => $day->setTime($closeHour, $closeMinute),
            };

            $raw[] = [$start, $end];
        }

        usort($raw, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($raw as [$start, $end]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $start <= $merged[$last][1]) {
                if ($end > $merged[$last][1]) {
                    $merged[$last][1] = $end;
                }

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    private function validTime(mixed $value): bool
    {
        return is_string($value) && preg_match(self::TIME, $value) === 1;
    }

    private function assertScope(string $scope): void
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException("Unknown scope [{$scope}].");
        }
    }
}

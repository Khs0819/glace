<?php

namespace App\Services\Drivers;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\DriverSettlement;
use Illuminate\Support\Facades\DB;

/**
 * Pays a driver what they are owed, with the transfer slip as evidence.
 *
 * Shared by the cashier screen and the drivers page so there is one definition
 * of "paying a driver": the unpaid delivery fees are locked, summed, and pointed
 * at a single payout row in one transaction. Two cashiers pressing the button at
 * once cannot pay the same fee twice, because the second one finds nothing left
 * unpaid.
 */
class DriverPayoutService
{
    /** @return DriverPayout|null null when there was nothing owed */
    public function pay(Driver $driver, ?string $receipt, ?string $notes = null, ?int $paidBy = null): ?DriverPayout
    {
        return DB::transaction(function () use ($driver, $receipt, $notes, $paidBy) {
            $rows = DriverSettlement::where('driver_id', $driver->getKey())
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            $amount = round((float) $rows->sum('delivery_fee'), 2);

            if ($rows->isEmpty() || $amount <= 0) {
                return null;
            }

            $payout = DriverPayout::create([
                'driver_id' => $driver->getKey(),
                'amount'    => $amount,
                'receipt'   => $receipt,
                'notes'     => $notes,
                'paid_by'   => $paidBy,
                'paid_at'   => now(),
            ]);

            DriverSettlement::whereIn('id', $rows->pluck('id'))->update(['payout_id' => $payout->getKey()]);

            return $payout;
        });
    }
}

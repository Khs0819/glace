<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\StoreSetting;
use App\Services\Storefront\StoreHours;
use Illuminate\Http\JsonResponse;

/**
 * Whether the shop and delivery are open, when that changes, and the week.
 *
 * `storeOpen`, `deliveryOpen`, `closedMessage` and `autoConfirmMinutes` keep
 * their old meaning and place, so a storefront built before opening hours
 * existed keeps working unchanged.
 */
class StoreStatusController extends Controller
{
    public function __invoke(StoreHours $hours): JsonResponse
    {
        $store    = $hours->status('store');
        $delivery = $hours->status('delivery');

        return response()->json([
            'storeOpen'             => $store['open'],
            'deliveryOpen'          => $delivery['open'],
            'closedMessage'         => StoreSetting::closedMessage(),
            'deliveryClosedMessage' => StoreSetting::deliveryClosedMessage(),
            'autoConfirmMinutes'    => StoreSetting::autoConfirmMinutes(),

            // Which payment options to show. A method switched off in the
            // dashboard is false here and is refused by POST /orders.
            'paymentMethods' => PaymentAccount::enabledMethods(),

            'timezone'   => $hours->timezone(),
            'serverTime' => $hours->now()->toIso8601String(),

            'store'    => $store,
            'delivery' => $delivery,

            'schedule' => [
                'store'    => $hours->weekForApi('store'),
                'delivery' => $hours->weekForApi('delivery'),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}

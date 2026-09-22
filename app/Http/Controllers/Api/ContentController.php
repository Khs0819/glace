<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\SiteContent;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard-owned content that used to be hardcoded in the storefront bundle:
 * payment accounts (13), help FAQs (15), terms (16) and privacy (17).
 *
 * All public, all cheap, none of them customer-specific.
 */
class ContentController extends Controller
{
    /** Where the shop is paid, for the manual-transfer methods (handoff 13). */
    public function paymentAccounts(): JsonResponse
    {
        /*
         * Every method the shop has switched on, and nothing it has switched
         * off. The storefront builds its payment options from this list — a
         * method missing here is a method the customer is not shown — so the
         * «مفعّل» switch in the dashboard is what decides it.
         *
         * Only the transfer destinations carry account details. Cash, card and
         * automatic Jawwal Pay are listed so they can be shown or hidden, but
         * a card reader has no account number, and sending the placeholder
         * text from its row would put that text on the customer's screen.
         */
        $accounts = PaymentAccount::where('active', true)
            ->whereIn('method', array_keys(PaymentAccount::METHODS))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentAccount $account) => array_filter(array_merge([
                'method'      => $account->method,
                // Set in the dashboard; absent means the storefront uses its
                // own label for the method.
                'displayName' => $account->display_name,
                'type'        => $account->type(),
            ], $account->isTransferDestination() ? [
                'qrImage'        => $account->qrImageUrl(),
                'holderName'     => $account->holder_name,
                // Only banks have one; handoff 13 says to omit it for wallets
                // rather than send an empty string.
                'bankName'       => $account->bank_name,
                'primaryLabel'   => $account->primary_label,
                'primaryValue'   => $account->primary_value,
                'secondaryLabel' => $account->secondary_label,
                'secondaryValue' => $account->secondary_value,
                // The account the customer transfers into.
                'accountNumber'  => $account->account_number,
            ] : []), static fn ($value) => $value !== null && $value !== ''));

        return response()->json($accounts->values());
    }

    /** Array order is display order; there is no pagination (handoff 15). */
    public function faqs(): JsonResponse
    {
        $faqs = Faq::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Faq $faq) => array_filter([
                'id'       => $faq->id,
                'question' => $faq->question,
                'answer'   => $faq->answer,
                'link'     => $faq->link_href === null ? null : [
                    'href'  => $faq->link_href,
                    'label' => $faq->link_label ?? $faq->link_href,
                ],
            ], static fn ($value) => $value !== null));

        return response()->json($faqs->values());
    }

    /**
     * One HTML string, not a list of sections (handoff 16 · 17).
     *
     * Sanitised on the way out as well as on the way in — the storefront runs
     * DOMPurify over it again, and neither side treats the other's pass as
     * sufficient.
     */
    public function terms(): JsonResponse
    {
        return response()->json(SiteContent::body(SiteContent::KEY_TERMS));
    }

    public function privacy(): JsonResponse
    {
        return response()->json(SiteContent::body(SiteContent::KEY_PRIVACY));
    }
}

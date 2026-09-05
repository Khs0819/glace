<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
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
        $accounts = PaymentAccount::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentAccount $account) => array_filter([
                'method'         => $account->method,
                'qrImage'        => $account->qrImageUrl(),
                'holderName'     => $account->holder_name,
                // Only banks have one; handoff 13 says to omit it for wallets
                // rather than send an empty string.
                'bankName'       => $account->bank_name,
                'primaryLabel'   => $account->primary_label,
                'primaryValue'   => $account->primary_value,
                'secondaryLabel' => $account->secondary_label,
                'secondaryValue' => $account->secondary_value,
            ], static fn ($value) => $value !== null && $value !== ''));

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

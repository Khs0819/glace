<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreStorefrontOrderRequest;
use App\Http\Resources\OrderResource;
use App\Mail\OrderSummaryMail;
use App\Models\Order;
use App\Services\Storefront\ReceiptStorage;
use App\Services\Storefront\StorefrontOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Storefront orders (handoff 12).
 *
 * Every route but creation is scoped to the signed-in customer *in the query*,
 * so another customer's order is simply not found rather than found-and-refused.
 */
class StorefrontOrderController extends Controller
{
    public function __construct(
        private readonly StorefrontOrderService $orders,
        private readonly ReceiptStorage $receipts,
    ) {}

    /** Paginated, newest first — same envelope as the wallet's history. */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('perPage', 20)));

        $orders = $request->user()->orders()
            ->with('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        return response()->json([
            'items'      => OrderResource::collection($orders->items()),
            'total'      => $orders->total(),
            'page'       => $orders->currentPage(),
            'perPage'    => $orders->perPage(),
            'totalPages' => $orders->lastPage(),
        ]);
    }

    public function store(StoreStorefrontOrderRequest $request): JsonResponse
    {
        $order = $this->orders->place(
            $request->validated(),
            $request->user(),
            $request->file('receiptImage'),
        );

        // Merged by hand rather than with ->additional(): response()->json()
        // serialises the resource through jsonSerialize(), which never sees
        // additional data — the token would silently vanish.
        return response()->json(
            (new OrderResource($order))->toArray($request) + [
                // Handed over exactly once, so a guest can still track the
                // order they just placed without an account.
                'token' => $order->public_token,
            ],
            201,
        );
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        return response()->json(new OrderResource($this->find($request, $reference)));
    }

    /** Start the automatic Jawwal flow. No order exists yet (handoff 12 §4.1). */
    public function sendJawwalCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'  => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ], [
            'phone.required'  => 'رقم الهاتف مطلوب',
            'amount.required' => 'قيمة الطلب مطلوبة',
        ]);

        $this->orders->sendJawwalCode($data['phone'], (float) $data['amount']);

        return response()->json(['sent' => true]);
    }

    public function cancel(Request $request, string $reference): JsonResponse
    {
        $order = $this->find($request, $reference);

        // 409: an order that has been delivered or already cancelled is not a
        // thing that can be cancelled again.
        if ($order->isFinal()) {
            return response()->json([
                'message' => 'لا يمكن إلغاء هذا الطلب — تم إغلاقه بالفعل',
            ], 409);
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $order->update([
            'status'        => Order::FULFILMENT_CANCELLED,
            'cancel_reason' => $data['reason'] ?? null,
            'cancelled_at'  => now(),
        ]);

        // Refunding is deliberately NOT automatic (handoff 12 §6): the amount
        // stays under review until a human has checked it, and only then does
        // the dashboard move the order to "مسترد".
        return response()->json(['order' => new OrderResource($order->fresh('items'))]);
    }

    /** Re-upload proof of transfer. Does not move the order's status. */
    public function receipt(Request $request, string $reference): JsonResponse
    {
        $order = $this->find($request, $reference);

        if (! $order->requiresReceipt()) {
            return response()->json([
                'message' => 'هذا الطلب لا يحتاج إيصال تحويل',
            ], 422);
        }

        $request->validate([
            'receiptImage' => ['nullable', 'file'],
            'receiptNote'  => ['nullable', 'string', 'max:1000', 'required_without:receiptImage'],
        ], [
            'receiptNote.required_without' => 'أرفق صورة الإيصال أو اكتب ملاحظة توضح التحويل',
        ]);

        $order->update([
            'receipt_image' => $this->receipts->replace(
                $order->receipt_image,
                $request->file('receiptImage'),
                'receipts',
            ),
            'receipt_note' => $request->input('receiptNote', $order->receipt_note),
        ]);

        return response()->json(['order' => new OrderResource($order->fresh('items'))]);
    }

    /** The customer confirming a delivery arrived (handoff 12 §6). */
    public function received(Request $request, string $reference): JsonResponse
    {
        $order = $this->find($request, $reference);

        if ($order->delivery_method !== 'delivery') {
            return response()->json(['message' => 'هذا الطلب ليس طلب توصيل'], 422);
        }

        if ($order->isFinal()) {
            return response()->json(['message' => 'تم إغلاق هذا الطلب بالفعل'], 409);
        }

        $order->update([
            'status'      => Order::FULFILMENT_RECEIVED,
            'received_at' => now(),
        ]);

        return response()->json(['order' => new OrderResource($order->fresh('items'))]);
    }

    /**
     * Mail a summary. Not tied to the account's address — the customer types
     * whichever one they want it sent to (handoff 12 §6).
     */
    public function emailSummary(Request $request, string $reference): JsonResponse
    {
        $order = $this->find($request, $reference);

        $data = $request->validate(
            ['email' => ['required', 'email:rfc', 'max:190']],
            ['email.required' => 'البريد الإلكتروني مطلوب', 'email.email' => 'البريد الإلكتروني غير صالح'],
        );

        Mail::to($data['email'])->send(new OrderSummaryMail($order->load('items')));

        return response()->json(['sent' => true]);
    }

    /**
     * Find an order by its public reference.
     *
     * Signed in: scoped to the customer's own orders. Signed out: the
     * `token` issued at creation is the whole authorisation, which is what
     * lets a guest track what they just ordered. A miss is always 404 — never
     * a 403 that would confirm the reference is real.
     */
    private function find(Request $request, string $reference): Order
    {
        $query = Order::with('items')->where('reference', $reference);

        if ($customer = $request->user()) {
            $order = (clone $query)->where('customer_id', $customer->getKey())->first();

            if ($order) {
                return $order;
            }
        }

        $order = $query->first();

        abort_if($order === null || ! $order->tokenMatches($request->input('token')), 404, 'الطلب غير موجود');

        return $order;
    }
}

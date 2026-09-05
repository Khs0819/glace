<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreAddressRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\Address;
use App\Models\DeliveryZone;
use App\Services\Storefront\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved delivery addresses (handoff 10).
 *
 * Everything but the zone list is scoped to the signed-in customer, and scoped
 * by *query* rather than by checking ownership after the fact — an address that
 * is not yours is simply not found, which is also the answer that leaks least.
 */
class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AddressResource::collection($request->user()->addresses()->with('zone')->get()),
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addresses->create($request->user(), $request->validated());

        return response()->json(['address' => new AddressResource($address)], 201);
    }

    public function update(StoreAddressRequest $request, string $id): JsonResponse
    {
        $address = $this->find($request, $id);

        return response()->json([
            'address' => new AddressResource($this->addresses->update($address, $request->validated())),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->addresses->delete($this->find($request, $id));

        return response()->json(['message' => 'تم حذف العنوان']);
    }

    public function makeDefault(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'address' => new AddressResource($this->addresses->makeDefault($this->find($request, $id))),
        ]);
    }

    /**
     * Public: the checkout screen needs delivery fees before anyone has signed
     * in (handoff 10).
     */
    public function zones(): JsonResponse
    {
        return response()->json(DeliveryZoneResource::collection(
            DeliveryZone::where('available', true)->orderBy('sort_order')->orderBy('name')->get(),
        ));
    }

    /** 404 rather than 403: someone else's address must not be confirmed to exist. */
    private function find(Request $request, string $id): Address
    {
        $address = $request->user()->addresses()->with('zone')->find($id);

        abort_if($address === null, 404, 'العنوان غير موجود');

        return $address;
    }
}

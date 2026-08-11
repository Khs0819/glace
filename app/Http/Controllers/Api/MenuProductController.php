<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class MenuProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $unknown = array_diff(array_keys($request->query()), ['category']);
        if (! empty($unknown)) {
            throw ValidationException::withMessages([
                'query' => 'Unknown query parameter(s): ' . implode(', ', $unknown)
                    . '. The only accepted filter is "category".',
            ]);
        }

        $query = Product::with([
            'containers',
            'sizes.prices',
            'iceCreamAddonPrices',
            'items',
            'mixes',
            'addons',
        ])->where('available', true)->orderBy('sort_order');

        if ($category = $request->query('category')) {
            $query->where('category_id', $category);
        }

        return ProductResource::collection($query->get());
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with([
            'containers',
            'sizes.prices',
            'iceCreamAddonPrices',
            'items',
            'mixes',
            'addons',
            'flavors',
        ])->where('slug', $slug)->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(new ProductResource($product));
    }
}

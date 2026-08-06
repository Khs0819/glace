<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddonResource;
use App\Models\Addon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuAddonController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Shared catalog: addons with no product_id
        $addons = Addon::whereNull('product_id')->orderBy('sort_order')->get();

        return AddonResource::collection($addons);
    }
}

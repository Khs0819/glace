<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = MenuCategory::orderBy('sort_order')->get();

        return MenuCategoryResource::collection($categories);
    }
}

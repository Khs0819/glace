<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('perPage', 8)));

        $paginator = Event::with('images')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items'      => EventResource::collection($paginator->items()),
            'total'      => $paginator->total(),
            'page'       => $paginator->currentPage(),
            'perPage'    => $paginator->perPage(),
            'totalPages' => $paginator->lastPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $event = Event::with('images')->find($id);

        if (! $event) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(new EventResource($event));
    }
}

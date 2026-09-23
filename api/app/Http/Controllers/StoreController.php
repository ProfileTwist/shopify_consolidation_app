<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreResource;
use App\Jobs\PullShopifyDataJob;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $connections = Store::query()
            ->withCount(['orders', 'payouts'])
            ->orderBy('name')
            ->get();

        return StoreResource::collection($connections);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', 'in:mock,token,oauth'],
            'shop_domain' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'payments_enabled' => ['boolean'],
            'is_active' => ['boolean'],
            'sync_interval_seconds' => ['integer', 'min:5', 'max:86400'],
        ]);

        $data['platform'] = 'shopify';
        $connection = Store::create($data);

        return (new StoreResource($connection))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Store $connection): StoreResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'auth_type' => ['sometimes', 'in:mock,token,oauth'],
            'shop_domain' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'payments_enabled' => ['boolean'],
            'is_active' => ['boolean'],
            'sync_interval_seconds' => ['integer', 'min:5', 'max:86400'],
        ]);

        $connection->update($data);

        return new StoreResource($connection);
    }

    public function destroy(Store $connection): JsonResponse
    {
        $connection->delete();

        return response()->json(['message' => 'Connection removed.']);
    }

    /**
     * Trigger an on-demand sync for a single connection.
     */
    public function sync(Store $connection): JsonResponse
    {
        PullShopifyDataJob::dispatch($connection->id);

        return response()->json([
            'message' => "Sync dispatched for {$connection->name}.",
        ]);
    }
}

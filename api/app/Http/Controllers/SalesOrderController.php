<?php

namespace App\Http\Controllers;

use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesOrderController extends Controller
{
    /**
     * Paginated, filterable list of consolidated orders across all stores.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = SalesOrder::query()
            ->with('connection')
            ->when($request->filled('connection_id'), fn ($q) =>
                $q->where('store_id', $request->integer('connection_id')))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->string('status')))
            ->when($request->filled('financial_status'), fn ($q) =>
                $q->where('financial_status', $request->string('financial_status')))
            ->when($request->filled('search'), fn ($q) =>
                $q->where(fn ($sub) => $sub
                    ->where('order_number', 'like', '%' . $request->string('search') . '%')
                    ->orWhere('account_name', 'like', '%' . $request->string('search') . '%')
                    ->orWhere('customer_email', 'like', '%' . $request->string('search') . '%')))
            ->orderByDesc('shopify_updated_at')
            ->paginate($request->integer('per_page', 20));

        return SalesOrderResource::collection($orders);
    }

    public function show(SalesOrder $order): SalesOrderResource
    {
        $order->load(['connection', 'lineItems']);

        return new SalesOrderResource($order);
    }
}

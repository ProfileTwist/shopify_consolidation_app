<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * Headline totals for the dashboard KPI cards.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'orders_count' => SalesOrder::count(),
            'orders_total' => (float) SalesOrder::sum('total_amount'),
            'payouts_count' => Payout::count(),
            'payouts_net' => (float) Payout::sum('amount'),
            'stores_count' => Store::count(),
            'stores_healthy' => Store::where('status', 'healthy')->count(),
            'products_count' => Product::count(),
        ]);
    }
}

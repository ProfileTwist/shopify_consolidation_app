<?php

namespace App\Http\Controllers;

use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $payouts = Payout::query()
            ->with('connection')
            ->withCount('transactions')
            ->when($request->filled('connection_id'), fn ($q) =>
                $q->where('store_id', $request->integer('connection_id')))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->string('status')))
            ->orderByDesc('issued_at')
            ->paginate($request->integer('per_page', 20));

        return PayoutResource::collection($payouts);
    }

    /**
     * A payout with its full transaction breakdown, each line reconciled to its order.
     */
    public function show(Payout $payout): PayoutResource
    {
        $payout->load(['connection', 'transactions.order']);

        return new PayoutResource($payout);
    }
}

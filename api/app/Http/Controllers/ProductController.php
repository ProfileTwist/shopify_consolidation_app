<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('store')
            ->when($request->filled('connection_id'), fn ($q) =>
                $q->where('store_id', $request->integer('connection_id')))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) =>
                $q->where(fn ($sub) => $sub
                    ->where('title', 'like', '%' . $request->string('search') . '%')
                    ->orWhere('vendor', 'like', '%' . $request->string('search') . '%')
                    ->orWhere('product_type', 'like', '%' . $request->string('search') . '%')))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }
}

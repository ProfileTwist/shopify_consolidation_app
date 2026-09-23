<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('shopify_id');               // Shopify order id (gid)
            $table->string('order_number')->nullable();
            $table->string('status')->nullable();  // Draft, Activated, ...
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('account_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('currency', 8)->default('USD');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();
            $table->json('raw')->nullable();       // full source payload for reconciliation
            $table->timestamps();

            // Idempotency: one row per (store, shopify id).
            $table->unique(['store_id', 'shopify_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('shopify_id');
            $table->string('product_name')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2)->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['sales_order_id', 'shopify_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_line_items');
    }
};

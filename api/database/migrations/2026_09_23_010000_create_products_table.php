<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('shopify_id');
            $table->string('title')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status')->nullable();       // active | draft | archived
            $table->string('handle')->nullable();
            $table->unsignedInteger('variants_count')->default(0);
            $table->integer('total_inventory')->default(0);
            $table->decimal('price_min', 14, 2)->default(0);
            $table->decimal('price_max', 14, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->timestamp('shopify_created_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'shopify_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

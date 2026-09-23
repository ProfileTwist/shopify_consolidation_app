<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_id');                 // Shopify balance transaction id
            $table->string('type')->nullable();            // charge | refund | adjustment | fee
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('fee', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);

            // Link back to the order this line reconciles to (nullable: not every line maps to an order).
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_order_external_id')->nullable();

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['payout_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_transactions');
    }
};

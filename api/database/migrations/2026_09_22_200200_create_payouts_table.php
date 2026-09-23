<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_id');            // Shopify payout id
            $table->string('status')->nullable();     // scheduled | in_transit | paid | failed | canceled
            $table->date('issued_at')->nullable();
            $table->string('currency', 8)->default('USD');

            // Reconciliation summary (what the payout is made of).
            $table->decimal('amount', 14, 2)->default(0);      // net deposited
            $table->decimal('gross', 14, 2)->default(0);       // charges before fees
            $table->decimal('fees', 14, 2)->default(0);
            $table->decimal('refunds', 14, 2)->default(0);
            $table->decimal('adjustments', 14, 2)->default(0);
            $table->decimal('reserved', 14, 2)->default(0);

            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

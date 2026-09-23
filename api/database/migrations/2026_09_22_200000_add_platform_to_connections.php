<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('platform')->default('shopify')->after('name');
            $table->string('shop_domain')->nullable()->after('instance_url'); // e.g. mrdaisy-east.myshopify.com
            $table->boolean('payments_enabled')->default(true)->after('status'); // Shopify Payments available?
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['platform', 'shop_domain', 'payments_enabled']);
        });
    }
};

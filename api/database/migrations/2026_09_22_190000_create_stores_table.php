<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // human label, e.g. "Mr Daisy - East"
            $table->string('instance_url')->nullable();
            $table->string('auth_type')->default('mock'); // mock | oauth_password | client_credentials

            // Credentials are encrypted at rest via the model's `encrypted` casts.
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending'); // pending | healthy | error
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};

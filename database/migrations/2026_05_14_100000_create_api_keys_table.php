<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('name')->nullable();
            $table->enum('plan', ['free', 'basic', 'pro', 'ultra'])->default('free');
            $table->unsignedInteger('requests_limit_daily')->default(100);
            $table->unsignedInteger('requests_count_today')->default(0);
            $table->unsignedInteger('rate_limit_per_minute')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('key');
            $table->index('plan');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};

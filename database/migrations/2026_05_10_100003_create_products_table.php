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
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['key', 'subscription', 'giftcard'])->default('key');
            $table->string('platform')->nullable();
            $table->string('region')->default('global');
            $table->string('edition')->nullable();
            $table->string('url');
            $table->string('affiliate_url')->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->integer('discount_percent')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('in_stock')->default(true);
            $table->timestamps();

            $table->index(['game_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

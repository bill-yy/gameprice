<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('release_date')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('steam_app_id')->nullable()->unique();
            $table->json('platforms')->nullable();
            $table->json('genres')->nullable();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('metacritic_score')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('steam_app_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

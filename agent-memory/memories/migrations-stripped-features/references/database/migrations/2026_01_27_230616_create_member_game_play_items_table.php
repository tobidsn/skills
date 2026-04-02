<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_game_play_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_game_play_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained()->onDelete('cascade');
            $table->string('category')->nullable(); // main_dish, side_dish, drink
            $table->uuid('option_id')->nullable();
            $table->bigInteger('food_id')->nullable()->index();
            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_game_play_items');
    }
};

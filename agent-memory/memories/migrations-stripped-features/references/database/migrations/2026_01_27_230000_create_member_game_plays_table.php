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
        Schema::create('member_game_plays', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->string('status')->default('key_generated'); // key_generated, completed, failed
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('customer_id')->constrained()->onDelete('cascade');
            $table->integer('service_score')->default(0);
            $table->integer('energy_spent')->default(0);
            $table->unsignedBigInteger('reward_member_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_game_plays');
    }
};

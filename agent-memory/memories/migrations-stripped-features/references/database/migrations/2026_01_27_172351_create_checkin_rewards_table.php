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
        Schema::create('checkin_rewards', function (Blueprint $table) {
            $table->id();
            $table->integer('day_number')->default(0);
            $table->string('type')->nullable(); // energy, score, reward, multiple
            $table->integer('energy')->default(0);
            $table->integer('score')->default(0);
            $table->bigInteger('reward_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_rewards');
    }
};

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
        Schema::create('member_burn_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('promo_id')->constrained()->onDelete('cascade');
            $table->integer('points');
            $table->string('status');
            $table->string('contestant_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->bigInteger('voucher_id')->nullable();
            $table->string('voucher_code')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_burn_points');
    }
};

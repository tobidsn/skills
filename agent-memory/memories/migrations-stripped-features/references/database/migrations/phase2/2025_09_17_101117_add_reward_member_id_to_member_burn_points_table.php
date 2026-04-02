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
        Schema::table('member_burn_points', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_member_id')->nullable()->after('voucher_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_burn_points', function (Blueprint $table) {
            $table->dropColumn('reward_member_id');
        });
    }
};

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
        Schema::create('user_favorite_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('card_id');

            $table->primary(['user_id','card_id']);
            $table->timestamps();
        });
        
        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('users')) {
            Schema::table('user_favorite_rewards', function (Blueprint $table) {
                $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('rewards_catalog')) {
            Schema::table('user_favorite_rewards', function (Blueprint $table) {
                $table->foreign('card_id')->references('id')->on('rewards_catalog')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_favorite_rewards');
    }
};

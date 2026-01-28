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
        Schema::create('health_cycles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('sitting_minutes');
            $table->integer('standing_minutes');
            $table->integer('cycle_number');
            $table->integer('health_score'); // 0-100
            $table->integer('points_earned'); // 0, 4, 7, or 10
            $table->timestamp('completed_at');
            $table->timestamps();
            
            $table->index(['user_id', 'completed_at']);
        });
        
        // Add foreign key constraint only if users table exists
        if (Schema::hasTable('users')) {
            Schema::table('health_cycles', function (Blueprint $table) {
                $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_cycles');
    }
};

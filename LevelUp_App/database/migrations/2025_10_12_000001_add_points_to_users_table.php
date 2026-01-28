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
        // Only run if users table exists
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Only add columns if they don't already exist
                if (!Schema::hasColumn('users', 'total_points')) {
                    $table->integer('total_points')->default(0)->after('email');
                }
                if (!Schema::hasColumn('users', 'daily_points')) {
                    $table->integer('daily_points')->default(0)->after('total_points');
                }
                if (!Schema::hasColumn('users', 'last_points_date')) {
                    $table->date('last_points_date')->nullable()->after('daily_points');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_points', 'daily_points', 'last_points_date']);
        });
    }
};

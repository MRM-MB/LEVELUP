<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            // Optional friendly label for the desk
            $table->string('name')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('desks', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

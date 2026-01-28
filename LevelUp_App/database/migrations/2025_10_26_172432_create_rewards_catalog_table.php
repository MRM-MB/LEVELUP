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
        Schema::create('rewards_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('card_name');
            $table->integer('points_amount');
            $table->text('card_description')->nullable();
            $table->string('card_image')->nullable();
            $table->boolean('archived')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rewards_catalog');
    }
};

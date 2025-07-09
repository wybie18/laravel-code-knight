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
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['daily_login', 'daily_challenge', 'daily_lesson']);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_streaks');
    }
};

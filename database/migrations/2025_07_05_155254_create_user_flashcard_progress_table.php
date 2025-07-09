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
        Schema::create('user_flashcard_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flashcard_id')->constrained()->onDelete('cascade');
            $table->integer('ease_factor')->default(250);
            $table->integer('interval')->default(1);
            $table->integer('repetitions')->default(0);
            $table->timestamp('next_review_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'flashcard_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_flashcard_progress');
    }
};

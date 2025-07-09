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
        Schema::create('user_quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_question_id')->constrained()->onDelete('cascade');
            $table->json('answer'); // Store user's answer(s)
            $table->boolean('is_correct');
            $table->timestamps();
            $table->unique(['user_id', 'quiz_question_id'], 'user_quiz_answer_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quiz_answers');
    }
};

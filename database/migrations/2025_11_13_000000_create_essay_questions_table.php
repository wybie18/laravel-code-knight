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
        Schema::create('essay_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('rubric')->nullable(); // Grading criteria
            $table->integer('max_points')->default(10);
            $table->integer('min_words')->nullable();
            $table->integer('max_words')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('essay_questions');
    }
};

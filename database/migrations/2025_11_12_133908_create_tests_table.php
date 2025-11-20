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
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->integer('duration_minutes')->nullable(); // Time limit
            $table->integer('total_points')->default(0);
            $table->dateTime('start_time')->nullable(); // When test becomes available
            $table->dateTime('end_time')->nullable(); // Deadline
            $table->enum('status', ['draft', 'scheduled', 'active', 'closed', 'archived'])->default('draft');
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('show_results_immediately')->default(false);
            $table->boolean('allow_review')->default(true);
            $table->integer('max_attempts')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->onDelete('cascade');
            $table->morphs('itemable'); // Polymorphic relation to challenges, quizzes, essays
            $table->integer('order')->default(0);
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        Schema::create('test_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['test_id', 'student_id']);
        });

        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->integer('attempt_number')->default(1);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->integer('time_spent_minutes')->nullable();
            $table->integer('total_score')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'graded', 'abandoned'])->default('in_progress');
            $table->timestamps();
            
            $table->unique(['test_id', 'student_id', 'attempt_number']);
        });

        Schema::create('test_item_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('test_item_id')->constrained()->onDelete('cascade');
            $table->text('answer')->nullable(); // For text/essay answers
            $table->integer('score')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_item_submissions');
        Schema::dropIfExists('test_attempts');
        Schema::dropIfExists('test_students');
        Schema::dropIfExists('test_items');
        Schema::dropIfExists('tests');
    }
};

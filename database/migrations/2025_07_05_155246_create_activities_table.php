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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained('course_modules')->onDelete('cascade'); // Changed from lesson_id
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['content', 'code', 'quiz']);
            $table->foreignId('coding_activity_problem_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('exp_reward')->default(0);
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['course_module_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

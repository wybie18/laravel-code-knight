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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description');
            $table->text('objectives')->nullable();
            $table->string('thumbnail')->nullable();
            $table->foreignId('difficulty_id')->constrained('difficulties');
            $table->foreignId('category_id')->constrained('course_categories');
            $table->integer('exp_reward')->default(0);
            $table->integer('estimated_duration')->nullable(); // in minutes
            $table->boolean('is_published')->default(false);
            $table->foreignId('programming_language_id')->constrained();
            $table->timestamps();
        });

        Schema::create('course_skill_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['course_id', 'skill_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_skill_tag');
    }
};

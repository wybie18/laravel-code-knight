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
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('programming_language_id')->constrained('users')->onDelete('cascade');
            $table->string('course_code', 10)->unique()->nullable()->after('slug');
            $table->enum('visibility', ['public', 'private'])->default('public')->after('is_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'course_code', 'visibility']);
        });
    }
};

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
        Schema::create('challenge_language', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coding_challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('programming_language_id')->constrained()->onDelete('cascade');
            $table->text('starter_code')->nullable();
            $table->text('solution_code')->nullable();
            $table->unique(['coding_challenge_id', 'programming_language_id'], 'challenge_language_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_language');
    }
};

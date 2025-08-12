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
        Schema::create('typing_challenges', function (Blueprint $table) {
            $table->id();
            $table->text('text_content');
            $table->foreignId('programming_language_id')->constrained();
            $table->integer('target_wpm')->default(40);
            $table->decimal('target_accuracy', 5, 2)->default(95.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('typing_challenges');
    }
};

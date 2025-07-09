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
        Schema::create('ctf_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('flag');
            $table->json('file_paths')->nullable();
            $table->foreignId('category_id')->constrained('ctf_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_t_f_challenges');
    }
};

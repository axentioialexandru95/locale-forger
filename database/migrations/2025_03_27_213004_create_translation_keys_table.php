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
        Schema::create('translation_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // e.g., 'homepage.welcome_message'
            $table->text('description')->nullable(); // Optional context for translators
            $table->timestamps();
            
            // Create a unique constraint to prevent duplicate keys within the same project
            $table->unique(['project_id', 'key']);
            
            // Add index for faster lookup
            $table->index('project_id');
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_keys');
    }
};

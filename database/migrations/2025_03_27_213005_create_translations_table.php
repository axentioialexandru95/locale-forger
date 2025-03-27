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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->text('text')->nullable(); // The translated text
            $table->boolean('is_machine_translated')->default(false);
            $table->enum('status', ['draft', 'review', 'final'])->default('draft');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            
            // Create a unique constraint to prevent duplicate translations
            $table->unique(['translation_key_id', 'language_id']);
            
            // Add indexes for faster lookup
            $table->index('translation_key_id');
            $table->index('language_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};

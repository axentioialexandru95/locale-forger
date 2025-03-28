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
        Schema::table('translation_keys', function (Blueprint $table) {
            $table->string('group')->nullable()->after('key');
            
            // Add index for faster lookups by group
            $table->index(['project_id', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('translation_keys', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'group']);
            $table->dropColumn('group');
        });
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->after('id')->nullable()->constrained();
            $table->enum('role', ['admin', 'translator'])->after('password')->default('translator');
            
            // Add index for faster lookups
            $table->index('organization_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['role']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'role']);
        });
    }
};

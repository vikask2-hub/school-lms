<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_records', function (Blueprint $table): void {
            $table->foreignId('creator_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('module_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('creator_id');
        });
    }
};

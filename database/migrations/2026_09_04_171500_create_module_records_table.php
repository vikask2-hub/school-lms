<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_records', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 50)->index();
            $table->string('audience', 20)->default('all')->index();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->dateTime('occurred_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['module', 'student_id']);
            $table->index(['module', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_records');
    }
};

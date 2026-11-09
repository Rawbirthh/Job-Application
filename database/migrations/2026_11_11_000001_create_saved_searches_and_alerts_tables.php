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
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('search', 180)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('job_categories')->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->restrictOnDelete();
            $table->foreignId('workplace_arrangement_id')->nullable()->constrained('workplace_arrangements')->restrictOnDelete();
            $table->foreignId('experience_level_id')->nullable()->constrained('experience_levels')->restrictOnDelete();
            $table->boolean('alerts_enabled')->default(true);
            $table->string('alert_frequency', 16)->default('daily');
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('saved_search_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_search_id')->constrained('saved_searches')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['saved_search_id', 'job_posting_id']);
        });

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::dropIfExists('saved_search_alerts');
        Schema::dropIfExists('saved_searches');
    }
};

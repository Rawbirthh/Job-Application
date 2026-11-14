<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_evaluations')) {
            Schema::create('application_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->constrained('job_applications')->cascadeOnDelete();
                $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('relevant_experience');
                $table->unsignedTinyInteger('technical_skills');
                $table->unsignedTinyInteger('communication');
                $table->unsignedTinyInteger('interview_performance');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->index(['application_id', 'created_at'], 'application_evaluations_application_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_evaluations');
    }
};

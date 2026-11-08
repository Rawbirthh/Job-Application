<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_application_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->foreignId('job_screening_question_id')->constrained('job_screening_questions')->restrictOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('answer_boolean')->nullable();
            $table->string('selected_option', 255)->nullable();
            $table->timestamps();

            $table->unique(['job_application_id', 'job_screening_question_id'], 'jaa_application_question_unique');
            $table->index('job_application_id');
            $table->index('job_screening_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_answers');
    }
};

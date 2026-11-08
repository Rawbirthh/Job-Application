<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_screening_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('screening_questions')->nullOnDelete();
            $table->string('type', 30);
            $table->text('label');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['job_posting_id', 'sort_order']);
            $table->index(['job_posting_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_screening_questions');
    }
};

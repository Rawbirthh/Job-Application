<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_template_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('screening_question_template_id');
            $table->unsignedBigInteger('screening_question_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('screening_question_template_id', 'stq_template_fk')->references('id')->on('screening_question_templates')->cascadeOnDelete();
            $table->foreign('screening_question_id', 'stq_question_fk')->references('id')->on('screening_questions')->cascadeOnDelete();

            $table->unique(['screening_question_template_id', 'screening_question_id'], 'stq_template_question_unique');
            $table->index(['screening_question_template_id', 'sort_order'], 'stq_template_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_template_questions');
    }
};

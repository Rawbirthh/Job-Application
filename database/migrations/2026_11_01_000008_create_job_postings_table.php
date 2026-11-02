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
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->foreignId('category_id')->constrained('job_categories')->restrictOnDelete();
            $table->foreignId('employment_type_id')->constrained('employment_types')->restrictOnDelete();
            $table->foreignId('workplace_arrangement_id')->nullable()->constrained('workplace_arrangements')->restrictOnDelete();
            $table->foreignId('experience_level_id')->nullable()->constrained('experience_levels')->restrictOnDelete();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->boolean('salary_negotiable')->default(false);
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods')->restrictOnDelete();
            $table->string('location', 255)->nullable();
            $table->date('application_deadline')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};

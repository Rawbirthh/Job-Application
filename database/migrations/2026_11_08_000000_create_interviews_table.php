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
        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('job_application_id');
            $table->foreignId('scheduled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('timezone', 64);
            $table->string('mode', 20);
            $table->string('location_details', 500);
            $table->text('candidate_instructions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('requested_scheduled_at')->nullable();
            $table->string('requested_timezone', 64)->nullable();
            $table->string('response_note', 500)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['job_application_id', 'created_at'], 'interviews_application_created_idx');
            $table->foreign('job_application_id')->references('id')->on('job_applications')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};

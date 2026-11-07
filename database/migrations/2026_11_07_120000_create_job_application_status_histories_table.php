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
        if (! Schema::hasTable('job_application_status_histories')) {
            Schema::create('job_application_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status');
                $table->string('to_status');
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['job_application_id', 'created_at'], 'ja_status_hist_app_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_application_status_histories');
    }
};

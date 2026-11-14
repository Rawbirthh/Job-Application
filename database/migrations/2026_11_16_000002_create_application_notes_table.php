<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_notes')) {
            Schema::create('application_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->constrained('job_applications')->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->timestamp('edited_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['application_id', 'created_at'], 'application_notes_application_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_notes');
    }
};

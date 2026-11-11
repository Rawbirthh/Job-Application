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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            // The actor may be deleted; the trail must survive with its snapshot columns.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_email', 255)->nullable();
            $table->string('action', 64);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_label', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 36)->nullable();
            // Append-only table: there is no updated_at and rows are never mutated.
            $table->timestamp('occurred_at');

            // The actor_id foreign key is covered by the (actor_id, occurred_at)
            // composite (leftmost prefix), so no redundant standalone index is added.
            $table->index(['action', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

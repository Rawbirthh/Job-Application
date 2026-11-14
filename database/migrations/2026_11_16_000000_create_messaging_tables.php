<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_conversations')) {
            Schema::create('application_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->unique()->constrained('job_applications')->cascadeOnDelete();
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('application_conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['conversation_id', 'id'], 'conversation_messages_conversation_id_id_idx');
            });
        }

        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('application_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->foreign('last_read_message_id', 'conversation_participants_last_read_fk')
                    ->references('id')
                    ->on('conversation_messages')
                    ->nullOnDelete();
                $table->unique(['conversation_id', 'user_id'], 'conversation_participants_conversation_user_unique');
                $table->index('user_id', 'conversation_participants_user_id_idx');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('application_conversations');
    }
};

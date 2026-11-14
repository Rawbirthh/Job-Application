<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('application_conversations', 'sort_at')) {
            Schema::table('application_conversations', function (Blueprint $table): void {
                $table->timestamp('sort_at')->nullable()->after('last_message_at');
            });
        }

        DB::table('application_conversations')
            ->whereNull('sort_at')
            ->update(['sort_at' => DB::raw('COALESCE(last_message_at, created_at)')]);

        if (! Schema::hasIndex('application_conversations', 'application_conversations_sort_at_idx')) {
            Schema::table('application_conversations', function (Blueprint $table): void {
                $table->index('sort_at', 'application_conversations_sort_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('application_conversations', 'application_conversations_sort_at_idx')) {
            Schema::table('application_conversations', function (Blueprint $table): void {
                $table->dropIndex('application_conversations_sort_at_idx');
            });
        }

        if (Schema::hasColumn('application_conversations', 'sort_at')) {
            Schema::table('application_conversations', function (Blueprint $table): void {
                $table->dropColumn('sort_at');
            });
        }
    }
};

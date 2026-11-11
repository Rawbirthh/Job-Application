<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('reason', 24);
            // Server-managed dedupe key ("job:{id}" / "company:{id}"). Kept non-null so
            // the composite unique below still enforces the rule for open reports:
            // MySQL/SQLite multi-column UNIQUE treats NULL components as distinct, so a
            // nullable subject FK in the key would void the DB backstop.
            $table->string('subject_key', 32);
            $table->text('explanation')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('resolution_action', 32)->nullable();
            $table->text('resolution_message')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            // Dedupe marker: 'open' while the report is pending/investigating, NULL once
            // resolved/dismissed. With subject_key non-null, this unique index blocks two
            // open reports by the same reporter for one subject while still allowing
            // re-reporting after a closed outcome (open_marker NULL rows stay distinct).
            $table->string('open_marker', 16)->nullable()->default('open');
            $table->timestamps();

            $table->unique(['reporter_id', 'subject_key', 'open_marker'], 'reports_open_dedupe');
            $table->index(['status', 'created_at']);
        });

        // XOR subject guard: exactly one of job_posting_id / company_id must be set.
        // SQLite cannot ALTER TABLE ADD CHECK, so on that driver the rule is enforced by
        // application validation (StoreReportRequest) instead of this statement.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE reports ADD CONSTRAINT chk_reports_subject CHECK ((job_posting_id IS NULL) <> (company_id IS NULL))'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE reports DROP CHECK chk_reports_subject');
            } catch (QueryException) {
                // The constraint may not exist (older engines ignore CHECK clauses);
                // dropping the table removes it either way.
            }
        }

        Schema::dropIfExists('reports');
    }
};

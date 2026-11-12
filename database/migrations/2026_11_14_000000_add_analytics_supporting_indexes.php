<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('job_postings', 'job_postings_company_status_idx', ['company_id', 'status']);
        $this->addIndex('job_postings', 'job_postings_created_at_idx', ['created_at']);
        $this->addIndex('job_applications', 'job_applications_created_at_idx', ['created_at']);
        $this->addIndex('job_applications', 'job_applications_posting_status_idx', ['job_posting_id', 'status']);
        $this->addIndex('users', 'users_created_at_idx', ['created_at']);
        $this->addIndex('companies', 'companies_created_at_idx', ['created_at']);
        $this->dropIndex('job_applications', 'job_applications_job_posting_id_index');
    }

    public function down(): void
    {
        if ($this->usesForeignKeyIndexes()) {
            $this->addIndex('job_postings', 'job_postings_company_id_index', ['company_id']);
        }

        $this->addIndex('job_applications', 'job_applications_job_posting_id_index', ['job_posting_id']);

        $this->dropIndex('job_applications', 'job_applications_posting_status_idx');
        $this->dropIndex('job_applications', 'job_applications_created_at_idx');
        $this->dropIndex('job_postings', 'job_postings_created_at_idx');
        $this->dropIndex('job_postings', 'job_postings_company_status_idx');
        $this->dropIndex('users', 'users_created_at_idx');
        $this->dropIndex('companies', 'companies_created_at_idx');
    }

    /**
     * Determine whether the connection maintains foreign key backing indexes.
     */
    private function usesForeignKeyIndexes(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * Add a named index unless the table already carries it.
     *
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $table, string $name, array $columns): void
    {
        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    /**
     * Drop a named index when the table carries it.
     */
    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }
};

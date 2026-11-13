<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('job_skills', 'job_skills_skill_posting_idx', ['skill', 'job_posting_id']);
        $this->addIndex('candidate_skills', 'candidate_skills_skill_profile_idx', ['skill', 'candidate_profile_id']);
        $this->addIndex('job_postings', 'job_postings_location_city_idx', ['location_city']);

        if (! $this->usesForeignKeyIndexes()) {
            $this->addIndex('job_postings', 'job_postings_category_id_idx', ['category_id']);
            $this->addIndex('job_postings', 'job_postings_experience_level_id_idx', ['experience_level_id']);
            $this->addIndex('job_postings', 'job_postings_workplace_arrangement_id_idx', ['workplace_arrangement_id']);
        }
    }

    public function down(): void
    {
        $this->dropIndex('job_postings', 'job_postings_workplace_arrangement_id_idx');
        $this->dropIndex('job_postings', 'job_postings_experience_level_id_idx');
        $this->dropIndex('job_postings', 'job_postings_category_id_idx');
        $this->dropIndex('job_postings', 'job_postings_location_city_idx');
        $this->dropIndex('candidate_skills', 'candidate_skills_skill_profile_idx');
        $this->dropIndex('job_skills', 'job_skills_skill_posting_idx');
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

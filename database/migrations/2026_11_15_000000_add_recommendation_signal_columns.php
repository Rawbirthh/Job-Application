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
        Schema::table('experience_levels', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_years')->nullable()->after('sort_order');
        });

        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->string('location_city', 100)->nullable()->after('location');

            // The three preference columns reference platform catalogue rows.
            //
            // They deliberately carry no database-level foreign key: SQLite
            // refuses to drop a column that participates in a foreign key
            // definition (it requires a full table rebuild), which would make
            // this migration irreversible on the engine the test suite runs on.
            // The referenced rows are catalogue rows that the platform
            // deactivates rather than deletes, their integrity is enforced by
            // the Form Request `exists:` validation, and a dangling id degrades
            // to "no preference" because the matching relation resolves to
            // null.
            $table->unsignedBigInteger('preferred_category_id')->nullable()->after('skills');
            $table->unsignedBigInteger('preferred_experience_level_id')->nullable()->after('preferred_category_id');
            $table->unsignedBigInteger('preferred_workplace_arrangement_id')->nullable()->after('preferred_experience_level_id');
        });

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->string('location_city', 100)->nullable()->after('location');
            $table->json('skills')->nullable()->after('location_city');
        });

        Schema::create('candidate_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->string('skill', 60);
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'skill'], 'candidate_skills_profile_skill_unique');
        });

        Schema::create('job_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('skill', 60);
            $table->timestamps();

            $table->unique(['job_posting_id', 'skill'], 'job_skills_posting_skill_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('candidate_skills');

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropColumn(['skills', 'location_city']);
        });

        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'preferred_workplace_arrangement_id',
                'preferred_experience_level_id',
                'preferred_category_id',
                'location_city',
            ]);
        });

        Schema::table('experience_levels', function (Blueprint $table): void {
            $table->dropColumn('min_years');
        });
    }
};

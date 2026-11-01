<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make the status column nullable if it isn't already
        try {
            DB::statement('ALTER TABLE users MODIFY status VARCHAR(255) NULL');
        } catch (Exception $e) {
            // Column may already be nullable; ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: we only made the column more permissive
    }
};

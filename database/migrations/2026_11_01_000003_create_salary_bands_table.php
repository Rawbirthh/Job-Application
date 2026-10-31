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
        Schema::create('salary_bands', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('currency', 3);
            $table->foreignId('pay_period_id')
                ->index()
                ->constrained('pay_periods')
                ->restrictOnDelete();
            $table->decimal('lower_bound', 12, 2);
            $table->decimal('upper_bound', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['currency', 'pay_period_id', 'label'],
                'salary_bands_currency_period_label_unique'
            );
            $table->unique(
                ['currency', 'pay_period_id', 'lower_bound', 'upper_bound'],
                'salary_bands_currency_period_range_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_bands');
    }
};

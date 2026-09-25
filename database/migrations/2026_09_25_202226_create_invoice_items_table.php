<?php

declare(strict_types=1);

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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->date('segment_start');
            $table->date('segment_end');
            $table->decimal('prorated_base', 12, 2);
            $table->unsignedBigInteger('included_units');
            $table->unsignedBigInteger('billable_usage');
            $table->unsignedBigInteger('overage_units');
            $table->decimal('overage_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

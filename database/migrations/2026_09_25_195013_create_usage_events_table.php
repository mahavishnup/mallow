<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('merchant_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('quantity');
            $table->string('type');
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'usage_date']);
            $table->index(['merchant_id', 'usage_date']);
        });

        // Partial index: only un-aggregated events — the aggregation job's cursor
        // (supported on both PostgreSQL and SQLite).
        DB::statement('CREATE INDEX usage_events_aggregated_at_index ON usage_events (aggregated_at) WHERE aggregated_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};

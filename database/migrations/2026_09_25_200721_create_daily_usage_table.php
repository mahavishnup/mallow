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
        Schema::create('daily_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('total_quantity');
            $table->unsignedInteger('event_count');
            $table->timestamps();

            $table->unique(['customer_id', 'usage_date']);
            $table->index(['merchant_id', 'usage_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_usage');
    }
};

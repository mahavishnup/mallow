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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('base_price', 10, 2);
            $table->unsignedBigInteger('included_units');
            $table->decimal('overage_rate', 10, 4);
            $table->unsignedSmallInteger('billing_cycle_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

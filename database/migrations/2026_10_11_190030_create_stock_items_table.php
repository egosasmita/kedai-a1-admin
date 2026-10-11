<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->nullable()->unique();
            $table->string('name', 160)->unique();
            $table->string('unit', 30);
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('minimum_quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};

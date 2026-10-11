<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['allowance', 'bonus', 'overtime', 'deduction']);
            $table->string('label', 120);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};

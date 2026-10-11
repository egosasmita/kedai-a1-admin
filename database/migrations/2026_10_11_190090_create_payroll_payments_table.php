<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'e_wallet', 'other'])->default('bank_transfer');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index(['payroll_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number', 30)->nullable()->unique();
            $table->string('position', 100);
            $table->date('hired_at')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->enum('employment_status', ['active', 'inactive', 'resigned'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employment_status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};

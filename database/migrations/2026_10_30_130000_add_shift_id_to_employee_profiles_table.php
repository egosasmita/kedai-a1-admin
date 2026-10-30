<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->foreignId('shift_id')
                ->nullable()
                ->after('position')
                ->constrained()
                ->nullOnDelete();
        });

        $defaultShiftId = DB::table('shifts')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');

        if ($defaultShiftId !== null) {
            DB::table('employee_profiles')->whereNull('shift_id')->update([
                'shift_id' => $defaultShiftId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};

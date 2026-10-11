<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('check_in_photo_path')->nullable()->after('check_in_at');
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_in_photo_path');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_in_location_accuracy', 8, 2)->nullable()->after('check_in_longitude');
            $table->string('check_out_photo_path')->nullable()->after('check_out_at');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_out_photo_path');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->decimal('check_out_location_accuracy', 8, 2)->nullable()->after('check_out_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn([
                'check_in_photo_path',
                'check_in_latitude',
                'check_in_longitude',
                'check_in_location_accuracy',
                'check_out_photo_path',
                'check_out_latitude',
                'check_out_longitude',
                'check_out_location_accuracy',
            ]);
        });
    }
};

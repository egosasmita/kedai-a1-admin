<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('tag', 50)->nullable();
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->timestamp('discount_end_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn(['tag', 'discount_price', 'discount_end_time']);
        });
    }
};

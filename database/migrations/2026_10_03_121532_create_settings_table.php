<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('shop_name', 160);

            $table->string('tagline')->nullable();

            $table->string('logo_url', 500)->nullable();

            $table->text('address')->nullable();

            $table->string('phone', 50)->nullable();

            $table->string('instagram', 100)->nullable();

            $table->string('open_hour', 10)->nullable();

            $table->string('close_hour', 10)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

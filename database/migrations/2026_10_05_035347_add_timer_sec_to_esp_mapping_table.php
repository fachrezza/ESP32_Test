<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->unsignedInteger('timer_sec')->default(0)->after('status_since'); // durasi status saat ini (detik)
        });
    }

    public function down(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->dropColumn('timer_sec');
        });
    }
};

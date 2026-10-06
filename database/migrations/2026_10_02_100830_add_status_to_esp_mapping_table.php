<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->string('status', 10)->nullable()->after('nama_mesin');      // RUNNING / MOULD / SETTER / OFF
            $table->timestamp('status_since')->nullable()->after('status');      // sejak kapan status saat ini berlaku
        });
    }

    public function down(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_since']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->timestamp('connected_at')->nullable()->after('ip_address'); // awal sesi koneksi saat ini / terakhir
        });
    }

    public function down(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->dropColumn('connected_at');
        });
    }
};

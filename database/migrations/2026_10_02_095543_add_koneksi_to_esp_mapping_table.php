<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->string('mac_address', 17)->nullable()->unique()->after('id_esp'); // format AA:BB:CC:DD:EE:FF
            $table->string('ip_address', 45)->nullable()->after('nama_mesin');      // IP terakhir yang terlihat
            $table->timestamp('last_seen_at')->nullable()->after('ip_address');     // terakhir kali ESP mengakses API
        });
    }

    public function down(): void
    {
        Schema::table('esp_mapping', function (Blueprint $table) {
            $table->dropUnique(['mac_address']);
            $table->dropColumn(['mac_address', 'ip_address', 'last_seen_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esp_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('id_esp', 50)->unique();           // ID unik tiap ESP32 (mis. chip ID / MAC)
            $table->unsignedTinyInteger('kode_mesin')->index(); // 1, 2, 3, ...
            $table->string('nama_mesin', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esp_mapping');
    }
};

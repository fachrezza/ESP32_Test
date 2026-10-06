<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesin', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 4)->unique();             // UID mesin
            $table->string('nama', 100);
            $table->unsignedTinyInteger('nomor')->unique();   
            $table->boolean('status')->default(false);   
            $table->timestamp('started_at')->nullable();     
            $table->unsignedInteger('timer_sec')->default(0);  
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesin');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ayahs', function (Blueprint $table) {
            // Mushaf identifier: surah_number * 1000 + ayah_number.
            // Example: surah 1, ayah 3 => 1003, displayed as "001003".
            $table->unsignedInteger('id')->primary();

            $table->unsignedTinyInteger('surah_number');
            $table->string('surah_name', 64);
            $table->unsignedSmallInteger('ayah_number');
            $table->text('text_uthmani');
            $table->timestamps();

            // Strictly prevent duplicate verses. Re-imports update in place.
            $table->unique(['surah_number', 'ayah_number'], 'ayahs_surah_ayah_unique');
            $table->index('surah_number');
            $table->index('ayah_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayahs');
    }
};

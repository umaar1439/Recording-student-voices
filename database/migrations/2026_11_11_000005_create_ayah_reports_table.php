<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reports submitted by students when an ayah text looks incorrect.
     */
    public function up(): void
    {
        Schema::create('ayah_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('ayah_id');
            $table->foreign('ayah_id')->references('id')->on('ayahs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One report per student per ayah; a repeat report refreshes it.
            $table->unique(['ayah_id', 'user_id'], 'ayah_reports_ayah_user_unique');
            $table->index(['status', 'created_at'], 'ayah_reports_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ayah_reports');
    }
};

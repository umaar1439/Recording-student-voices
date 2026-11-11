<?php

use App\Models\RecitationTask;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Surah Al-Fatihah is a practice surah: every student may record it, so
     * tasks belonging to it must never remain reserved or completed.
     */
    public function up(): void
    {
        DB::table('recitation_tasks')
            ->whereIn('ayah_id', function ($query): void {
                $query->select('id')->from('ayahs')->where('surah_number', 1);
            })
            ->update([
                'status' => RecitationTask::STATUS_AVAILABLE,
                'assigned_to' => null,
                'assigned_at' => null,
                'approved_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * The previous reservation state cannot be reconstructed safely.
     */
    public function down(): void
    {
        //
    }
};

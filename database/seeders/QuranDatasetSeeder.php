<?php

namespace Database\Seeders;

use App\Models\Ayah;
use App\Models\RecitationTask;
use App\Support\Quran;
use App\Support\QuranDatasetImporter;
use App\Support\QuranSamples;
use Illuminate\Database\Seeder;
use Throwable;

class QuranDatasetSeeder extends Seeder
{
    /**
     * Import the complete Quran dataset when available, otherwise fall back to
     * the curated sample set so the application is always usable.
     */
    public function run(QuranDatasetImporter $importer): void
    {
        $path = $this->datasetPath();

        if ($path !== null) {
            try {
                $result = $importer->import($path);

                $this->command?->info('Imported dataset from '.$path.' — '.$result->summary());
                $this->command?->info('Ayahs stored: '.Ayah::count().' / '.Quran::TOTAL_AYAHS);

                return;
            } catch (Throwable $exception) {
                $this->command?->warn('Dataset import failed: '.$exception->getMessage());
            }
        }

        $inserted = QuranSamples::seed();

        RecitationTask::syncAll();

        $this->command?->warn("Dataset file not found; seeded {$inserted} sample ayahs instead.");
    }

    protected function datasetPath(): ?string
    {
        $candidates = [
            base_path('py/Quran_Full_Data.xlsx'),
            database_path('data/quran_full_data.xlsx'),
            database_path('data/quran_full_data.csv'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

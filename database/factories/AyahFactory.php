<?php

namespace Database\Factories;

use App\Models\Ayah;
use App\Support\Quran;
use App\Support\QuranSamples;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ayah>
 */
class AyahFactory extends Factory
{
    protected $model = Ayah::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$surahNumber, $ayahNumber] = $this->uniqueCoordinate();

        return [
            'id' => Ayah::composeId($surahNumber, $ayahNumber),
            'surah_number' => $surahNumber,
            'surah_name' => Quran::surahFullName($surahNumber),
            'ayah_number' => $ayahNumber,
            'text_uthmani' => fake()->randomElement(QuranSamples::rows())['text_uthmani'],
        ];
    }

    /**
     * Find a surah/ayah pair that is not stored yet, starting from a random
     * position inside the mushaf so multiple factories never clash.
     *
     * @return array{0: int, 1: int}
     */
    protected function uniqueCoordinate(): array
    {
        $total = 6236;
        $start = fake()->numberBetween(1, $total);

        for ($offset = 0; $offset < $total; $offset++) {
            $index = (($start + $offset - 1) % $total) + 1;
            $remaining = $index;

            for ($surah = 1; $surah <= 114; $surah++) {
                $count = Quran::ayahCount($surah);

                if ($remaining <= $count) {
                    break;
                }

                $remaining -= $count;
            }

            $taken = Ayah::query()
                ->where('surah_number', $surah)
                ->where('ayah_number', $remaining)
                ->exists();

            if (! $taken) {
                return [$surah, $remaining];
            }
        }

        return [114, 6];
    }
}

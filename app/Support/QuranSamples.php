<?php

namespace App\Support;

use App\Models\Ayah;

class QuranSamples
{
    /**
     * A curated set of short ayahs (with full tashkeel) that is useful for
     * quickly bootstrapping a recitation dataset.
     *
     * @return list<array{surah_number: int, ayah_number: int, text_uthmani: string}>
     */
    public static function rows(): array
    {
        return [
            // سورة الفاتحة
            ['surah_number' => 1, 'ayah_number' => 1, 'text_uthmani' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ'],
            ['surah_number' => 1, 'ayah_number' => 2, 'text_uthmani' => 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ'],
            ['surah_number' => 1, 'ayah_number' => 3, 'text_uthmani' => 'الرَّحْمَٰنِ الرَّحِيمِ'],
            ['surah_number' => 1, 'ayah_number' => 4, 'text_uthmani' => 'مَالِكِ يَوْمِ الدِّينِ'],
            ['surah_number' => 1, 'ayah_number' => 5, 'text_uthmani' => 'إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ'],
            ['surah_number' => 1, 'ayah_number' => 6, 'text_uthmani' => 'اهْدِنَا الصِّرَاطَ الْمُسْتَقِيمَ'],
            ['surah_number' => 1, 'ayah_number' => 7, 'text_uthmani' => 'صِرَاطَ الَّذِينَ أَنْعَمْتَ عَلَيْهِمْ غَيْرِ الْمَغْضُوبِ عَلَيْهِمْ وَلَا الضَّالِّينَ'],
            // سورة العصر
            ['surah_number' => 103, 'ayah_number' => 1, 'text_uthmani' => 'وَالْعَصْرِ'],
            ['surah_number' => 103, 'ayah_number' => 2, 'text_uthmani' => 'إِنَّ الْإِنسَانَ لَفِي خُسْرٍ'],
            ['surah_number' => 103, 'ayah_number' => 3, 'text_uthmani' => 'إِلَّا الَّذِينَ آمَنُوا وَعَمِلُوا الصَّالِحَاتِ وَتَوَاصَوْا بِالْحَقِّ وَتَوَاصَوْا بِالصَّبْرِ'],
            // سورة الفيل
            ['surah_number' => 105, 'ayah_number' => 1, 'text_uthmani' => 'أَلَمْ تَرَ كَيْفَ فَعَلَ رَبُّكَ بِأَصْحَابِ الْفِيلِ'],
            ['surah_number' => 105, 'ayah_number' => 2, 'text_uthmani' => 'أَلَمْ يَجْعَلْ كَيْدَهُمْ فِي تَضْلِيلٍ'],
            ['surah_number' => 105, 'ayah_number' => 3, 'text_uthmani' => 'وَأَرْسَلَ عَلَيْهِمْ طَيْرًا أَبَابِيلَ'],
            ['surah_number' => 105, 'ayah_number' => 4, 'text_uthmani' => 'تَرْمِيهِم بِحِجَارَةٍ مِّن سِجِّيلٍ'],
            ['surah_number' => 105, 'ayah_number' => 5, 'text_uthmani' => 'فَجَعَلَهُمْ كَعَصْفٍ مَّأْكُولٍ'],
            // سورة قريش
            ['surah_number' => 106, 'ayah_number' => 1, 'text_uthmani' => 'لِإِيلَافِ قُرَيْشٍ'],
            ['surah_number' => 106, 'ayah_number' => 2, 'text_uthmani' => 'إِيلَافِهِمْ رِحْلَةَ الشِّتَاءِ وَالصَّيْفِ'],
            ['surah_number' => 106, 'ayah_number' => 3, 'text_uthmani' => 'فَلْيَعْبُدُوا رَبَّ هَٰذَا الْبَيْتِ'],
            ['surah_number' => 106, 'ayah_number' => 4, 'text_uthmani' => 'الَّذِي أَطْعَمَهُم مِّن جُوعٍ وَآمَنَهُم مِّنْ خَوْفٍ'],
            // سورة الكوثر
            ['surah_number' => 108, 'ayah_number' => 1, 'text_uthmani' => 'إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ'],
            ['surah_number' => 108, 'ayah_number' => 2, 'text_uthmani' => 'فَصَلِّ لِرَبِّكَ وَانْحَرْ'],
            ['surah_number' => 108, 'ayah_number' => 3, 'text_uthmani' => 'إِنَّ شَانِئَكَ هُوَ الْأَبْتَرُ'],
            // سورة الكافرون
            ['surah_number' => 109, 'ayah_number' => 1, 'text_uthmani' => 'قُلْ يَا أَيُّهَا الْكَافِرُونَ'],
            ['surah_number' => 109, 'ayah_number' => 2, 'text_uthmani' => 'لَا أَعْبُدُ مَا تَعْبُدُونَ'],
            ['surah_number' => 109, 'ayah_number' => 3, 'text_uthmani' => 'وَلَا أَنتُمْ عَابِدُونَ مَا أَعْبُدُ'],
            ['surah_number' => 109, 'ayah_number' => 4, 'text_uthmani' => 'وَلَا أَنَا عَابِدٌ مَّا عَبَدتُّمْ'],
            ['surah_number' => 109, 'ayah_number' => 5, 'text_uthmani' => 'وَلَا أَنتُمْ عَابِدُونَ مَا أَعْبُدُ'],
            ['surah_number' => 109, 'ayah_number' => 6, 'text_uthmani' => 'لَكُمْ دِينُكُمْ وَلِيَ دِينِ'],
            // سورة النصر
            ['surah_number' => 110, 'ayah_number' => 1, 'text_uthmani' => 'إِذَا جَاءَ نَصْرُ اللَّهِ وَالْفَتْحُ'],
            ['surah_number' => 110, 'ayah_number' => 2, 'text_uthmani' => 'وَرَأَيْتَ النَّاسَ يَدْخُلُونَ فِي دِينِ اللَّهِ أَفْوَاجًا'],
            ['surah_number' => 110, 'ayah_number' => 3, 'text_uthmani' => 'فَسَبِّحْ بِحَمْدِ رَبِّكَ وَاسْتَغْفِرْهُ ۚ إِنَّهُ كَانَ تَوَّابًا'],
            // سورة المسد
            ['surah_number' => 111, 'ayah_number' => 1, 'text_uthmani' => 'تَبَّتْ يَدَا أَبِي لَهَبٍ وَتَبَّ'],
            ['surah_number' => 111, 'ayah_number' => 2, 'text_uthmani' => 'مَا أَغْنَىٰ عَنْهُ مَالُهُ وَمَا كَسَبَ'],
            ['surah_number' => 111, 'ayah_number' => 3, 'text_uthmani' => 'سَيَصْلَىٰ نَارًا ذَاتَ لَهَبٍ'],
            ['surah_number' => 111, 'ayah_number' => 4, 'text_uthmani' => 'وَامْرَأَتُهُ حَمَّالَةَ الْحَطَبِ'],
            ['surah_number' => 111, 'ayah_number' => 5, 'text_uthmani' => 'فِي جِيدِهَا حَبْلٌ مِّن مَّسَدٍ'],
            // سورة الإخلاص
            ['surah_number' => 112, 'ayah_number' => 1, 'text_uthmani' => 'قُلْ هُوَ اللَّهُ أَحَدٌ'],
            ['surah_number' => 112, 'ayah_number' => 2, 'text_uthmani' => 'اللَّهُ الصَّمَدُ'],
            ['surah_number' => 112, 'ayah_number' => 3, 'text_uthmani' => 'لَمْ يَلِدْ وَلَمْ يُولَدْ'],
            ['surah_number' => 112, 'ayah_number' => 4, 'text_uthmani' => 'وَلَمْ يَكُن لَّهُ كُفُوًا أَحَدٌ'],
            // سورة الفلق
            ['surah_number' => 113, 'ayah_number' => 1, 'text_uthmani' => 'قُلْ أَعُوذُ بِرَبِّ الْفَلَقِ'],
            ['surah_number' => 113, 'ayah_number' => 2, 'text_uthmani' => 'مِن شَرِّ مَا خَلَقَ'],
            ['surah_number' => 113, 'ayah_number' => 3, 'text_uthmani' => 'وَمِن شَرِّ غَاسِقٍ إِذَا وَقَبَ'],
            ['surah_number' => 113, 'ayah_number' => 4, 'text_uthmani' => 'وَمِن شَرِّ النَّفَّاثَاتِ فِي الْعُقَدِ'],
            ['surah_number' => 113, 'ayah_number' => 5, 'text_uthmani' => 'وَمِن شَرِّ حَاسِدٍ إِذَا حَسَدَ'],
            // سورة الناس
            ['surah_number' => 114, 'ayah_number' => 1, 'text_uthmani' => 'قُلْ أَعُوذُ بِرَبِّ النَّاسِ'],
            ['surah_number' => 114, 'ayah_number' => 2, 'text_uthmani' => 'مَلِكِ النَّاسِ'],
            ['surah_number' => 114, 'ayah_number' => 3, 'text_uthmani' => 'إِلَٰهِ النَّاسِ'],
            ['surah_number' => 114, 'ayah_number' => 4, 'text_uthmani' => 'مِن شَرِّ الْوَسْوَاسِ الْخَنَّاسِ'],
            ['surah_number' => 114, 'ayah_number' => 5, 'text_uthmani' => 'الَّذِي يُوَسْوِسُ فِي صُدُورِ النَّاسِ'],
            ['surah_number' => 114, 'ayah_number' => 6, 'text_uthmani' => 'مِنَ الْجِنَّةِ وَالنَّاسِ'],
        ];
    }

    /**
     * Insert the sample ayahs without overwriting any existing rows.
     *
     * @return int number of inserted rows
     */
    public static function seed(): int
    {
        $inserted = 0;

        foreach (self::rows() as $row) {
            $ayah = Ayah::firstOrCreate(
                [
                    'surah_number' => $row['surah_number'],
                    'ayah_number' => $row['ayah_number'],
                ],
                ['text_uthmani' => $row['text_uthmani']],
            );

            if ($ayah->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return $inserted;
    }
}

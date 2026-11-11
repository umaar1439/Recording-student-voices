<?php

namespace App\Support;

/**
 * Builds a deterministic, intentionally corrupted version of a Quran verse.
 *
 * The mutation is used for the "wrong recitation" training samples: the
 * student is asked to recite the displayed (altered) text exactly, producing
 * an audio sample paired with a deliberately incorrect transcript.
 *
 * The mutation is deterministic per ayah so the same wrong task always shows
 * the same text across requests, reviews, exports and re-imports.
 */
class WrongAyahMutator
{
    /**
     * Fallback words used when a verse cannot be mutated by swapping words
     * (for example a single-word verse such as "يس").
     *
     * @var list<string>
     */
    protected const REPLACEMENTS = [
        'ٱلْجَنَّةِ',
        'ٱلنَّارِ',
        'ٱلْكِتَٰبِ',
        'ٱلْأَرْضِ',
        'ٱلسَّمَٰوَٰتِ',
        'ٱلرَّحْمَٰنِ',
    ];

    /**
     * Return a deliberately wrong version of the given Uthmani text.
     */
    public static function mutate(string $text, int $seed): string
    {
        $text = trim($text);

        if ($text === '') {
            return $text;
        }

        /** @var list<string> $words */
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $count = count($words);

        if ($count === 1) {
            return $words[0].' '.self::replacement($words[0], $seed);
        }

        // Swap two adjacent words. This is a realistic recitation mistake
        // (wrong word order) and keeps the verse recognisable as Quran.
        for ($offset = 0; $offset < $count - 1; $offset++) {
            $index = (abs($seed) + $offset) % ($count - 1);

            if ($words[$index] === $words[$index + 1]) {
                continue;
            }

            [$words[$index], $words[$index + 1]] = [$words[$index + 1], $words[$index]];

            return implode(' ', $words);
        }

        // Every word is identical (very unlikely): replace the last word.
        $last = $count - 1;
        $words[$last] = self::replacement($words[$last], $seed);

        return implode(' ', $words);
    }

    /**
     * Pick a replacement word that differs from the original one.
     */
    protected static function replacement(string $original, int $seed): string
    {
        $total = count(self::REPLACEMENTS);
        $start = abs($seed) % $total;

        for ($offset = 0; $offset < $total; $offset++) {
            $candidate = self::REPLACEMENTS[($start + $offset) % $total];

            if ($candidate !== $original) {
                return $candidate;
            }
        }

        return self::REPLACEMENTS[0];
    }
}

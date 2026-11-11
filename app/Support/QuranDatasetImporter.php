<?php

namespace App\Support;

use App\Models\Ayah;
use App\Models\RecitationTask;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Imports the full Quran dataset produced by the Python scraper
 * (`Quran_Full_Data.xlsx`) or any equivalent CSV/XLSX file.
 *
 * The importer is dependency free: XLSX files are streamed with ZipArchive +
 * XMLReader, CSV files with fgetcsv. Rows are validated against the standard
 * 6,236 ayah map, duplicates are ignored and existing verses are updated in
 * place so the operation is fully idempotent.
 */
class QuranDatasetImporter
{
    public const CHUNK_SIZE = 500;

    public const MAX_REPORTED_ERRORS = 25;

    /**
     * Fallback column order matching the generated workbook:
     * ID, surah name, surah number, ayah number, text.
     *
     * @var array<string, int>
     */
    private const DEFAULT_COLUMNS = [
        'id' => 0,
        'surah_name' => 1,
        'surah_number' => 2,
        'ayah_number' => 3,
        'text_uthmani' => 4,
    ];

    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'id' => ['id', 'ayahid', 'ayahcode', 'code', 'المعرف', 'معرفالايه', 'معرفالايهid', 'الرقم'],
        'surah_name' => ['surahname', 'name', 'اسمالسوره', 'اسمالسورهالكريمه', 'السوره'],
        'surah_number' => ['surahnumber', 'surahno', 'surah', 'sura', 'suranum', 'رقمالسوره', 'السورهرقم'],
        'ayah_number' => ['ayahnumber', 'ayahno', 'ayah', 'verse', 'versenumber', 'verseno', 'رقمالايه', 'الايهرقم'],
        'text_uthmani' => ['text', 'textuthmani', 'uthmani', 'نصالايه', 'النص', 'الايه', 'نصالايهبالرسمالعثماني'],
    ];

    /**
     * Import the dataset located at $path.
     *
     * @param  bool  $updateExisting  update surah name / text of existing rows
     * @param  bool  $dryRun  validate and count without writing anything
     *
     * @throws RuntimeException
     */
    public function import(string $path, bool $updateExisting = true, bool $dryRun = false): DatasetImportResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("تعذّر قراءة ملف البيانات: {$path}");
        }

        if (! $dryRun && ! Schema::hasTable('recitation_tasks')) {
            throw new RuntimeException('جدول مهام التلاوة غير موجود. نفّذ php artisan migrate ثم أعد الاستيراد.');
        }

        $result = new DatasetImportResult($path, $dryRun);

        $rows = $this->detectFormat($path) === 'xlsx'
            ? $this->xlsxRows($path)
            : $this->csvRows($path);

        /** @var array<string, int>|null $columns */
        $columns = null;

        /** @var array<string, true> $seen */
        $seen = [];

        /** @var array<int, array<string, mixed>> $buffer */
        $buffer = [];

        $now = Carbon::now();

        $flush = function () use (&$buffer, $result, $updateExisting, $dryRun, $now): void {
            if ($buffer === []) {
                return;
            }

            $this->persist(array_values($buffer), $result, $updateExisting, $dryRun, $now);

            $buffer = [];
        };

        foreach ($rows as $rowNumber => $cells) {
            if ($columns === null) {
                $detected = $this->detectColumns($cells);

                if ($detected !== null) {
                    $columns = $detected;

                    continue;
                }

                $columns = self::DEFAULT_COLUMNS;
            }

            $row = $this->parseRow($cells, $columns, $rowNumber, $result);

            if ($row === null) {
                continue;
            }

            $key = $row['surah_number'].':'.$row['ayah_number'];

            if (isset($seen[$key])) {
                $result->skipped++;
                $this->addNote($result, "السطر {$rowNumber}: تكرار للآية ".Quran::formatAyahId($row['id']).' داخل الملف، تم تجاهله.');

                continue;
            }

            $seen[$key] = true;
            $buffer[$row['id']] = $row;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $flush();
            }
        }

        $flush();

        if (! $dryRun) {
            // Keep the correct/wrong assignment pool in sync with the dataset.
            RecitationTask::syncAll();
        }

        return $result;
    }

    /**
     * Write (or count) one chunk of validated rows.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function persist(
        array $rows,
        DatasetImportResult $result,
        bool $updateExisting,
        bool $dryRun,
        CarbonInterface $now,
    ): void {
        $ids = array_column($rows, 'id');

        /** @var array<int, Ayah> $existing */
        $existing = Ayah::query()
            ->whereIn('id', $ids)
            ->get(['id', 'surah_name', 'text_uthmani'])
            ->keyBy('id')
            ->all();

        $inserts = [];
        $updates = [];

        foreach ($rows as $row) {
            $current = $existing[$row['id']] ?? null;

            if ($current === null) {
                $result->inserted++;
                $inserts[] = $row + ['created_at' => $now, 'updated_at' => $now];
            } elseif (! $updateExisting) {
                $result->skipped++;
            } elseif ($current->text_uthmani !== $row['text_uthmani'] || $current->surah_name !== $row['surah_name']) {
                $result->updated++;

                // Exact comparison (including every harakah) — only report the
                // ayah as replaced when its text itself changed.
                if ($current->text_uthmani !== $row['text_uthmani']) {
                    $result->replaced[] = Quran::formatAyahId($row['id']);
                }

                $updates[] = $row + ['created_at' => $now, 'updated_at' => $now];
            } else {
                $result->unchanged++;
            }
        }

        if ($dryRun) {
            return;
        }

        if ($inserts !== []) {
            Ayah::query()->insertOrIgnore($inserts);
        }

        if ($updates !== []) {
            Ayah::query()->upsert(
                $updates,
                ['id'],
                ['surah_name', 'text_uthmani', 'updated_at'],
            );
        }
    }

    /**
     * Validate a raw row and convert it to the database representation.
     *
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     * @return array{id: int, surah_number: int, surah_name: string, ayah_number: int, text_uthmani: string}|null
     */
    protected function parseRow(array $cells, array $columns, int $rowNumber, DatasetImportResult $result): ?array
    {
        $surahRaw = $this->cell($cells, $columns, 'surah_number');
        $ayahRaw = $this->cell($cells, $columns, 'ayah_number');
        $textRaw = $this->cell($cells, $columns, 'text_uthmani');
        $nameRaw = $this->cell($cells, $columns, 'surah_name');
        $idRaw = $this->cell($cells, $columns, 'id');

        if ($surahRaw === '' && $ayahRaw === '' && $textRaw === '') {
            return null;
        }

        $surahNumber = $this->toInt($surahRaw);
        $ayahNumber = $this->toInt($ayahRaw);

        if ($surahNumber === null || $surahNumber < 1 || $surahNumber > 114) {
            return $this->reject($result, $rowNumber, 'رقم السورة غير صالح: "'.$surahRaw.'".');
        }

        $maxAyahs = Quran::ayahCount($surahNumber);

        if ($ayahNumber === null || $ayahNumber < 1) {
            return $this->reject($result, $rowNumber, 'رقم الآية غير صالح: "'.$ayahRaw.'".');
        }

        if ($maxAyahs > 0 && $ayahNumber > $maxAyahs) {
            return $this->reject(
                $result,
                $rowNumber,
                "السورة {$surahNumber} تحتوي على {$maxAyahs} آية فقط (ورد رقم {$ayahNumber}).",
            );
        }

        $text = Ayah::normalizeText($textRaw);

        if ($text === '') {
            return $this->reject($result, $rowNumber, 'نص الآية فارغ.');
        }

        if (mb_strlen($text) > 2000) {
            return $this->reject($result, $rowNumber, 'نص الآية يتجاوز 2000 حرف.');
        }

        $id = Quran::ayahId($surahNumber, $ayahNumber);

        if ($idRaw !== '') {
            $providedId = $this->toInt($idRaw);

            if ($providedId === null) {
                return $this->reject($result, $rowNumber, 'معرّف الآية غير رقمي: "'.$idRaw.'".');
            }

            if ($providedId !== $id) {
                return $this->reject(
                    $result,
                    $rowNumber,
                    "المعرّف {$providedId} لا يطابق ({$surahNumber}:{$ayahNumber}) المتوقع ".Quran::formatAyahId($id).'.',
                );
            }
        }

        return [
            'id' => $id,
            'surah_number' => $surahNumber,
            'surah_name' => Ayah::normalizeSurahName($nameRaw, $surahNumber),
            'ayah_number' => $ayahNumber,
            'text_uthmani' => $text,
        ];
    }

    protected function reject(DatasetImportResult $result, int $rowNumber, string $message): null
    {
        $this->addError($result, $rowNumber, $message);

        return null;
    }

    protected function addError(DatasetImportResult $result, int $rowNumber, string $message): void
    {
        $result->invalid++;

        $this->addNote($result, "السطر {$rowNumber}: {$message}");
    }

    /**
     * Record an informational note (duplicates, skipped rows) without marking
     * the row as invalid.
     */
    protected function addNote(DatasetImportResult $result, string $message): void
    {
        if (count($result->errors) < self::MAX_REPORTED_ERRORS) {
            $result->errors[] = $message;
        }
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    protected function cell(array $cells, array $columns, string $key): string
    {
        $index = $columns[$key] ?? null;

        if ($index === null) {
            return '';
        }

        return trim((string) ($cells[$index] ?? ''));
    }

    /**
     * Parse a numeric cell, accepting ASCII and Arabic-Indic digits.
     */
    protected function toInt(string $value): ?int
    {
        $value = strtr(trim($value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        if ($value === '' || ! preg_match('/^\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Detect the file format from its magic bytes, so Livewire temporary
     * uploads (which have no extension) are handled correctly.
     */
    protected function detectFormat(string $path): string
    {
        $handle = fopen($path, 'rb');
        $magic = $handle === false ? '' : (string) fread($handle, 4);

        if ($handle !== false) {
            fclose($handle);
        }

        return str_starts_with($magic, "PK\x03\x04") ? 'xlsx' : 'csv';
    }

    /**
     * Detect the header row and map the known columns. Returns null when the
     * row does not look like a header (then positional columns are used).
     *
     * @param  list<string>  $cells
     * @return array<string, int>|null
     */
    protected function detectColumns(array $cells): ?array
    {
        $columns = [];

        foreach ($cells as $index => $value) {
            $key = $this->headerKey((string) $value);

            if ($key !== null && ! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        if (! isset($columns['surah_number'], $columns['ayah_number'], $columns['text_uthmani'])) {
            return null;
        }

        return $columns;
    }

    protected function headerKey(string $header): ?string
    {
        $normalized = $this->normalizeHeader($header);

        if ($normalized === '') {
            return null;
        }

        foreach (self::HEADER_ALIASES as $key => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Normalise Arabic/English header labels: remove tashkeel, unify alef and
     * taa marbuta forms and strip spaces and punctuation.
     */
    protected function normalizeHeader(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $value);
        $value = str_replace(['ة'], 'ه', $value);
        $value = str_replace(['ى'], 'ي', $value);
        $value = mb_strtolower(trim($value));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    /**
     * @return Generator<int, list<string>>
     */
    protected function csvRows(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("تعذّر فتح ملف CSV: {$path}");
        }

        $firstLine = (string) fgets($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $rowNumber = 0;

        try {
            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $rowNumber++;

                $cells = array_map(fn ($value) => trim((string) $value), $cells);

                if ($rowNumber === 1 && isset($cells[0])) {
                    $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]);
                }

                if (implode('', $cells) === '') {
                    continue;
                }

                yield $rowNumber => array_values($cells);
            }
        } finally {
            fclose($handle);
        }
    }

    protected function detectDelimiter(string $line): string
    {
        $candidates = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($candidates);

        $delimiter = (string) array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }

    /**
     * Stream the first worksheet of an XLSX workbook. Supports shared strings
     * and inline strings (the format produced by pandas / openpyxl).
     *
     * @return Generator<int, list<string>>
     */
    protected function xlsxRows(string $path): Generator
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('إضافة ZIP غير مفعّلة على الخادم، تعذّر قراءة ملف Excel.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('تعذّر فتح ملف Excel، تأكد من أن الملف غير تالف.');
        }

        try {
            $sheetPath = $this->firstWorksheetPath($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($sheetPath);
        } finally {
            $zip->close();
        }

        if (! is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('الملف لا يحتوي على ورقة عمل قابلة للقراءة.');
        }

        $reader = XMLReader::XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);

        if (! $reader instanceof XMLReader) {
            throw new RuntimeException('تعذّر تحليل محتوى ملف Excel.');
        }

        $rowNumber = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $rowNumber++;
                $rowXml = $reader->readOuterXml();
                $row = simplexml_load_string($rowXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

                if ($row === false) {
                    continue;
                }

                $cells = $this->xlsxCells($row, $sharedStrings);

                if ($cells !== []) {
                    yield $rowNumber => $cells;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Resolve the path of the first worksheet inside the archive.
     */
    protected function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (is_string($workbook) && is_string($relationships)) {
            $book = simplexml_load_string($workbook, SimpleXMLElement::class, LIBXML_NONET);
            $rels = simplexml_load_string($relationships, SimpleXMLElement::class, LIBXML_NONET);

            if ($book !== false && $rels !== false) {
                $sheet = $book->sheets->sheet[0] ?? null;
                $attributes = $sheet === null ? null : $sheet->attributes('r', true);
                $relationshipId = $attributes === null ? '' : (string) $attributes->id;

                if ($relationshipId !== '') {
                    foreach ($rels->Relationship as $relationship) {
                        if ((string) $relationship['Id'] !== $relationshipId) {
                            continue;
                        }

                        $target = (string) $relationship['Target'];
                        $candidate = str_starts_with($target, '/')
                            ? ltrim($target, '/')
                            : 'xl/'.ltrim($target, '/');

                        if ($zip->locateName($candidate) !== false) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return list<string>
     */
    protected function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if ($document === false) {
            return [];
        }

        $strings = [];

        foreach ($document->si as $item) {
            $strings[] = $this->richText($item);
        }

        return $strings;
    }

    /**
     * Flatten rich text runs into a single string.
     */
    protected function richText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $value = '';

        foreach ($node->r as $run) {
            $value .= (string) $run->t;
        }

        return $value;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<string>
     */
    protected function xlsxCells(SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];
        $position = 0;

        foreach ($row->c as $cell) {
            $reference = (string) $cell['r'];
            $index = $reference === '' ? $position : $this->columnIndex($reference);
            $position = $index + 1;
            $type = (string) $cell['t'];

            $value = match ($type) {
                'inlineStr' => isset($cell->is) ? $this->richText($cell->is) : '',
                's' => $sharedStrings[(int) $cell->v] ?? '',
                'b' => (string) $cell->v === '1' ? '1' : '',
                'e' => '',
                default => (string) $cell->v,
            };

            $cells[$index] = trim($value);
        }

        if ($cells === []) {
            return [];
        }

        ksort($cells);

        return array_values($cells);
    }

    /**
     * Convert an A1 style cell reference to a zero based column index.
     */
    protected function columnIndex(string $reference): int
    {
        $letters = strtoupper((string) preg_replace('/[^A-Z]/i', '', $reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}

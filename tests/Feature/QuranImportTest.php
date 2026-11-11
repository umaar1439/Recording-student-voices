<?php

namespace Tests\Feature;

use App\Livewire\Admin\AyahManager;
use App\Models\Ayah;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use App\Support\Quran;
use App\Support\QuranDatasetImporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class QuranImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    protected array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    protected function importer(): QuranDatasetImporter
    {
        return app(QuranDatasetImporter::class);
    }

    protected function writeCsv(string $contents): string
    {
        $base = tempnam(sys_get_temp_dir(), 'quran-import-');
        $path = $base.'.csv';
        rename($base, $path);
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_csv_import_inserts_verses_with_six_digit_identifiers(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,"بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ"
        001002,سورة الفاتحة,1,2,"الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ"
        001003,,1,3,"الرَّحْمَٰنِ الرَّحِيمِ"
        CSV);

        $result = $this->importer()->import($path);

        $this->assertSame(3, $result->inserted);
        $this->assertSame(0, $result->invalid);
        $this->assertSame(3, Ayah::count());

        $ayah = Ayah::findOrFail(1003);

        $this->assertSame('001003', $ayah->code);
        $this->assertSame('سورة الفاتحة', $ayah->surah_name);
        $this->assertSame('الرَّحْمَٰنِ الرَّحِيمِ', $ayah->text_uthmani);
        $this->assertSame('سورة الفاتحة · الآية 3', $ayah->reference);

        // Each imported verse produces a correct and a wrong task.
        $this->assertSame(6, RecitationTask::count());
        $this->assertSame('001003', RecitationTask::where('ayah_id', 1003)->where('type', 'correct')->value('code'));
        $this->assertSame('001003x', RecitationTask::where('ayah_id', 1003)->where('type', 'wrong')->value('code'));
    }

    public function test_reimport_updates_existing_verses_without_duplicating_them(): void
    {
        $first = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,بِسْمِ اللَّهِ
        001002,سورة الفاتحة,1,2,الْحَمْدُ لِلَّهِ
        001003,سورة الفاتحة,1,3,الرَّحْمَٰنِ الرَّحِيمِ
        CSV);

        $this->importer()->import($first);

        $second = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,بِسْمِ اللَّهِ
        001002,سورة الفاتحة,1,2,الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ
        001003,سورة الفاتحة,1,3,الرَّحْمَٰنِ الرَّحِيمِ
        001002,سورة الفاتحة,1,2,تكرار
        CSV);

        $result = $this->importer()->import($second);

        $this->assertSame(0, $result->inserted);
        $this->assertSame(1, $result->updated);
        $this->assertSame(2, $result->unchanged);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->invalid);
        $this->assertSame(3, Ayah::count());
        $this->assertSame('الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ', Ayah::findOrFail(1002)->text_uthmani);
        $this->assertSame(['001002'], $result->replaced);
    }

    public function test_reimport_reports_an_ayah_whose_harakat_changed(): void
    {
        $first = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
        CSV);

        $this->importer()->import($first);

        $second = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ
        CSV);

        $result = $this->importer()->import($second);

        $this->assertSame(1, $result->updated);
        $this->assertSame(['001001'], $result->replaced);
        $this->assertSame('بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ', Ayah::findOrFail(1001)->text_uthmani);
    }

    public function test_invalid_rows_are_rejected_and_reported(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        115001,سورة خاطئة,115,1,نص
        001008,سورة الفاتحة,1,8,نص
        001000,سورة الفاتحة,1,0,نص
        999001,سورة الفاتحة,1,1,نص
        001002,سورة الفاتحة,1,2,"   "
        103001,سورة العصر,103,1,وَالْعَصْرِ
        CSV);

        $result = $this->importer()->import($path);

        $this->assertSame(1, $result->inserted);
        $this->assertSame(5, $result->invalid);
        $this->assertCount(5, $result->errors);
        $this->assertSame(1, Ayah::count());
        $this->assertTrue(Ayah::query()->whereKey(103001)->exists());
    }

    public function test_no_update_mode_keeps_stored_text(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,النص الأول
        CSV);

        $this->importer()->import($path);

        $changed = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,النص الثاني
        CSV);

        $result = $this->importer()->import($changed, updateExisting: false);

        $this->assertSame(0, $result->inserted);
        $this->assertSame(1, $result->skipped);
        $this->assertSame('النص الأول', Ayah::findOrFail(1001)->text_uthmani);
    }

    public function test_model_generates_identifier_and_surah_name(): void
    {
        $ayah = Ayah::create([
            'surah_number' => 2,
            'ayah_number' => 255,
            'text_uthmani' => 'ٱللَّهُ لَآ إِلَٰهَ إِلَّا هُوَ ٱلۡحَىُّ ٱلۡقَيُّومُ',
        ]);

        $this->assertSame(2255, $ayah->id);
        $this->assertSame('002255', $ayah->code);
        $this->assertSame('سورة البقرة', $ayah->surah_name);
    }

    public function test_database_rejects_duplicate_verse_coordinates(): void
    {
        Ayah::create([
            'surah_number' => 1,
            'ayah_number' => 1,
            'text_uthmani' => 'النص الأصلي',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Ayah::query()->insert([
            'id' => 999999,
            'surah_number' => 1,
            'surah_name' => 'سورة الفاتحة',
            'ayah_number' => 1,
            'text_uthmani' => 'تكرار',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_xlsx_import_reads_inline_strings(): void
    {
        $path = $this->writeXlsx([
            ['103001', 'سورة العصر', 103, 1, 'وَالْعَصْرِ'],
            ['103002', 'سورة العصر', 103, 2, 'إِنَّ الْإِنسَانَ لَفِي خُسْرٍ'],
        ]);

        $result = $this->importer()->import($path);

        $this->assertSame(2, $result->inserted);
        $this->assertSame(0, $result->invalid);
        $this->assertSame(103002, Ayah::query()->max('id'));
        $this->assertSame('وَالْعَصْرِ', Ayah::findOrFail(103001)->text_uthmani);
    }

    public function test_xlsx_import_reads_shared_strings(): void
    {
        $path = $this->writeXlsx([
            ['106001', 'سورة قريش', 106, 1, 'لِإِيلَافِ قُرَيْشٍ'],
            ['106002', 'سورة قريش', 106, 2, 'إِيلَافِهِمْ رِحْلَةَ الشِّتَاءِ وَالصَّيْفِ'],
        ], useSharedStrings: true);

        $result = $this->importer()->import($path);

        $this->assertSame(2, $result->inserted);
        $this->assertSame(0, $result->invalid);
        $this->assertSame('لِإِيلَافِ قُرَيْشٍ', Ayah::findOrFail(106001)->text_uthmani);
    }

    public function test_import_command_loads_a_dataset(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        108001,سورة الكوثر,108,1,إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ
        CSV);

        $this->artisan('quran:import', ['path' => $path])->assertSuccessful();

        $this->assertSame(1, Ayah::count());
        $this->assertSame(108001, Ayah::query()->value('id'));
    }

    public function test_import_command_dry_run_writes_nothing(): void
    {
        $path = $this->writeCsv(<<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        108001,سورة الكوثر,108,1,إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ
        CSV);

        $this->artisan('quran:import', ['path' => $path, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Ayah::count());
        $this->assertSame(0, RecitationTask::count());
    }

    public function test_admin_can_edit_verse_text_without_changing_its_identifier(): void
    {
        Ayah::create([
            'surah_number' => 1,
            'ayah_number' => 1,
            'text_uthmani' => 'النص الأصلي',
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AyahManager::class)
            ->call('openEdit', 1001)
            ->set('text_uthmani', 'النص المحدَّث')
            ->call('save')
            ->assertHasNoErrors();

        $ayah = Ayah::findOrFail(1001);

        $this->assertSame('النص المحدَّث', $ayah->text_uthmani);
        $this->assertSame('النص المحدَّث', RecitationTask::where('code', '001001')->value('text_uthmani'));
        $this->assertNotSame('النص المحدَّث', RecitationTask::where('code', '001001x')->value('text_uthmani'));
    }

    public function test_admin_can_move_a_verse_when_it_has_no_recordings(): void
    {
        Ayah::create([
            'surah_number' => 1,
            'ayah_number' => 1,
            'text_uthmani' => 'نص الآية',
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AyahManager::class)
            ->call('openEdit', 1001)
            ->set('ayah_number', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Ayah::find(1001));
        $this->assertSame(1002, Ayah::query()->value('id'));
        $this->assertTrue(RecitationTask::where('code', '001002')->exists());
        $this->assertTrue(RecitationTask::where('code', '001002x')->exists());
    }

    public function test_admin_cannot_move_a_verse_that_has_recordings(): void
    {
        $ayah = Ayah::create([
            'surah_number' => 1,
            'ayah_number' => 1,
            'text_uthmani' => 'نص الآية',
        ]);

        Recording::factory()->for($ayah)->create();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AyahManager::class)
            ->call('openEdit', 1001)
            ->set('ayah_number', 2)
            ->call('save')
            ->assertHasErrors(['ayah_number']);

        $this->assertSame(1001, Ayah::query()->value('id'));
    }

    public function test_admin_can_upload_a_dataset_through_the_management_screen(): void
    {
        $csv = <<<'CSV'
        معرف الآية (ID),اسم السورة,رقم السورة,رقم الآية,نص الآية
        001001,سورة الفاتحة,1,1,بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ
        001002,سورة الفاتحة,1,2,الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ
        CSV;

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AyahManager::class)
            ->set('dataset', UploadedFile::fake()->createWithContent('quran.csv', $csv))
            ->call('importDataset')
            ->assertHasNoErrors()
            ->assertSet('lastImport.inserted', 2)
            ->assertSee('آخر استيراد');

        $this->assertSame(2, Ayah::count());
        $this->assertSame('001001', Ayah::findOrFail(1001)->code);
        $this->assertSame(4, RecitationTask::count());
        $this->assertTrue(RecitationTask::where('code', '001002x')->exists());
    }

    public function test_packaged_workbook_imports_the_complete_mushaf(): void
    {
        $path = base_path('py/Quran_Full_Data.xlsx');

        if (! is_file($path)) {
            $this->markTestSkipped('The packaged Quran workbook is not available.');
        }

        $result = $this->importer()->import($path);

        $this->assertSame(Quran::TOTAL_AYAHS, $result->inserted);
        $this->assertSame(0, $result->invalid);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(Quran::TOTAL_AYAHS, Ayah::count());
        $this->assertSame(114, Ayah::query()->distinct()->count('surah_number'));
        $this->assertSame(1001, Ayah::query()->min('id'));
        $this->assertSame(114006, Ayah::query()->max('id'));

        $kursi = Ayah::findOrFail(2255);

        $this->assertSame('002255', $kursi->code);
        $this->assertSame('سورة البقرة', $kursi->surah_name);
        $this->assertSame(255, $kursi->ayah_number);
        $this->assertGreaterThan(200, mb_strlen($kursi->text_uthmani));
        $this->assertStringContainsString('هُوَ', $kursi->text_uthmani);

        $this->assertSame('سورة الناس', Ayah::findOrFail(114006)->surah_name);

        // The complete mushaf yields 12,472 tasks with unique correct/wrong codes.
        $this->assertSame(Quran::TOTAL_AYAHS * 2, RecitationTask::count());
        $this->assertSame('001001x', RecitationTask::where('ayah_id', 1001)->where('type', 'wrong')->value('code'));
        $this->assertSame('114006', RecitationTask::where('ayah_id', 114006)->where('type', 'correct')->value('code'));

        $identicalPairs = RecitationTask::query()
            ->from('recitation_tasks as correct_tasks')
            ->join('recitation_tasks as wrong_tasks', function ($join): void {
                $join->on('wrong_tasks.ayah_id', '=', 'correct_tasks.ayah_id')
                    ->where('wrong_tasks.type', '=', 'wrong');
            })
            ->where('correct_tasks.type', 'correct')
            ->whereColumn('correct_tasks.text_uthmani', 'wrong_tasks.text_uthmani')
            ->count();

        $this->assertSame(0, $identicalPairs);
    }

    /**
     * Build a minimal XLSX workbook with inline or shared strings.
     *
     * @param  list<array{0: string, 1: string, 2: int, 3: int, 4: string}>  $rows
     */
    protected function writeXlsx(array $rows, bool $useSharedStrings = false): string
    {
        $path = sys_get_temp_dir().'/quran-import-'.uniqid('', true).'.xlsx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="القرآن" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');

        $sheetRows = [
            ['معرف الآية (ID)', 'اسم السورة', 'رقم السورة', 'رقم الآية', 'نص الآية'],
            ...$rows,
        ];

        $shared = [];

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($sheetRows as $index => $row) {
            $rowNumber = $index + 1;
            $xml .= '<row r="'.$rowNumber.'">';

            foreach (array_values($row) as $column => $value) {
                $reference = chr(65 + $column).$rowNumber;

                if (($column === 2 || $column === 3) && $index > 0) {
                    $xml .= '<c r="'.$reference.'" t="n"><v>'.(int) $value.'</v></c>';
                } elseif ($useSharedStrings) {
                    $shared[(string) $value] ??= count($shared);
                    $xml .= '<c r="'.$reference.'" t="s"><v>'.$shared[(string) $value].'</v></c>';
                } else {
                    $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t>'
                        .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                        .'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

        if ($useSharedStrings) {
            $strings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">';

            foreach (array_keys($shared) as $value) {
                $strings .= '<si><t xml:space="preserve">'
                    .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></si>';
            }

            $strings .= '</sst>';

            $zip->addFromString('xl/sharedStrings.xml', $strings);
        }

        $zip->close();

        $this->tempFiles[] = $path;

        return $path;
    }
}

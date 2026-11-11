<?php

namespace App\Console\Commands;

use App\Models\Ayah;
use App\Models\Recording;
use App\Support\Quran;
use App\Support\QuranDatasetImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportQuranDataset extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quran:import
        {path? : مسار ملف Excel أو CSV (الافتراضي: py/Quran_Full_Data.xlsx)}
        {--fresh : حذف الآيات الحالية قبل الاستيراد}
        {--no-update : عدم تحديث نصوص الآيات الموجودة}
        {--dry-run : فحص الملف وحساب النتائج دون كتابة في قاعدة البيانات}
        {--force : السماح بالحذف حتى مع وجود تسجيلات مرتبطة}';

    /**
     * @var string
     */
    protected $description = 'استيراد المصحف الكامل (6236 آية) من ملف Excel/CSV إلى جدول الآيات';

    public function handle(QuranDatasetImporter $importer): int
    {
        $path = $this->argument('path') ?: $this->defaultPath();

        if (! is_string($path) || ! is_file($path)) {
            $this->error('لم يتم العثور على ملف البيانات.');
            $this->line('مرّر المسار صراحةً، مثال: php artisan quran:import py/Quran_Full_Data.xlsx');

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');
        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = ! $this->option('no-update');
        $recordings = Recording::count();

        if ($fresh && ! $dryRun && $recordings > 0 && ! $this->option('force')) {
            $this->error("توجد {$recordings} تسجيلًا مرتبطًا بالآيات الحالية، وسيتم حذفها مع الآيات.");
            $this->line('أعد التنفيذ مع --force إذا كنت متأكدًا.');

            return self::FAILURE;
        }

        if ($fresh && ! $dryRun) {
            if ($recordings > 0) {
                $this->warn("سيتم حذف {$recordings} تسجيلًا مرتبطًا بالآيات المحذوفة (cascade).");
            }

            $confirmed = $this->option('force')
                || $this->confirm('هل تريد حذف جميع الآيات الحالية قبل الاستيراد؟', false);

            if (! $confirmed) {
                return self::FAILURE;
            }
        }

        $this->info('جارٍ قراءة الملف: '.$path);

        try {
            if ($dryRun) {
                $result = $importer->import($path, $updateExisting, true);
            } else {
                $result = DB::transaction(function () use ($importer, $path, $fresh, $updateExisting) {
                    if ($fresh) {
                        Ayah::query()->delete();
                    }

                    return $importer->import($path, $updateExisting);
                });
            }
        } catch (Throwable $exception) {
            $this->error('فشل الاستيراد: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('وضع الفحص: لم تُكتب أي بيانات.');
        }

        $this->newLine();
        $this->table(
            ['جديد', 'محدَّث', 'دون تغيير', 'متجاهل', 'غير صالح', 'إجمالي الصفوف'],
            [[
                $result->inserted,
                $result->updated,
                $result->unchanged,
                $result->skipped,
                $result->invalid,
                $result->totalRows(),
            ]],
        );

        $this->info(sprintf(
            'الآيات في قاعدة البيانات: %s من %s.',
            number_format(Ayah::count()),
            number_format(Quran::TOTAL_AYAHS),
        ));

        if ($result->replaced !== []) {
            $this->newLine();
            $this->warn(sprintf('تم استبدال نص %d آية:', count($result->replaced)));

            foreach (array_slice($result->replaced, 0, 50) as $code) {
                $this->line(' • '.$code);
            }

            if (count($result->replaced) > 50) {
                $this->line(' • … و'.(count($result->replaced) - 50).' أخرى.');
            }
        }

        if ($result->errors !== []) {
            $this->newLine();
            $this->warn('أول الأخطاء المكتشفة:');

            foreach ($result->errors as $error) {
                $this->line(' • '.$error);
            }
        }

        return self::SUCCESS;
    }

    protected function defaultPath(): string
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

        return $candidates[0];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DatasetExportController extends Controller
{
    /**
     * Download a complete ZIP archive: audio files grouped by correct/wrong,
     * a JSON manifest and a CSV mapping file.
     */
    public function zip(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);
        $recordings = $this->recordings($filters);

        abort_if($recordings->isEmpty(), 404, 'لا توجد تسجيلات مقبولة مطابقة للمعايير المحددة.');

        abort_unless(class_exists(ZipArchive::class), 500, 'إضافة ZIP غير مفعّلة على الخادم.');

        $paths = $this->archivePaths($recordings);

        $disk = Storage::disk('local');
        $disk->makeDirectory('tmp');

        $zipPath = @tempnam($disk->path('tmp'), 'quran-dataset-');

        abort_if($zipPath === false, 500, 'تعذّر إنشاء ملف الأرشيف المؤقت.');

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'تعذّر إنشاء ملف الأرشيف.');
        }

        $included = collect();

        foreach ($recordings as $recording) {
            if (! $disk->exists($recording->audio_path)) {
                continue;
            }

            $entry = $paths[$recording->id] ?? $this->fallbackArchivePath($recording);

            if (! $zip->addFile($disk->path($recording->audio_path), $entry)) {
                continue;
            }

            $included->push($recording);
        }

        $zip->addFromString('dataset.csv', $this->csvContent($included, $paths));
        $zip->addFromString('manifest.json', $this->jsonContent($included, $filters, $request->user(), $paths));
        $zip->addFromString('README.txt', $this->readmeContent());
        $zip->close();

        return response()
            ->download($zipPath, 'quran-dataset-'.now()->format('Ymd-His').'.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Download only the JSON mapping file.
     */
    public function json(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $recordings = $this->recordings($filters);

        abort_if($recordings->isEmpty(), 404, 'لا توجد تسجيلات مقبولة مطابقة للمعايير المحددة.');

        $paths = $this->archivePaths($recordings);

        return response()
            ->json(
                $this->manifest($recordings, $filters, $request->user(), $paths),
                200,
                [],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            )
            ->header('Content-Disposition', 'attachment; filename="quran-dataset-'.now()->format('Ymd-His').'.json"');
    }

    /**
     * Download only the CSV mapping file (UTF-8 with BOM for Excel).
     */
    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $recordings = $this->recordings($filters);

        abort_if($recordings->isEmpty(), 404, 'لا توجد تسجيلات مقبولة مطابقة للمعايير المحددة.');

        $content = $this->csvContent($recordings, $this->archivePaths($recordings));

        return response()->streamDownload(
            fn () => print $content,
            'quran-dataset-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Validate and normalise the export filters.
     *
     * @return array{type: ?string, surah: ?int, student: ?string, from: ?string, to: ?string}
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:correct,wrong,all'],
            'surah' => ['nullable', 'integer', 'between:1,114'],
            'student' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $type = $validated['type'] ?? null;

        return [
            'type' => in_array($type, Recording::TYPES, true) ? $type : null,
            'surah' => isset($validated['surah']) ? (int) $validated['surah'] : null,
            'student' => filled($validated['student'] ?? null) ? trim($validated['student']) : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{type: ?string, surah: ?int, student: ?string, from: ?string, to: ?string}  $filters
     * @return Collection<int, Recording>
     */
    protected function recordings(array $filters): Collection
    {
        return Recording::query()
            ->approved()
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['surah'], function (Builder $query, int $surah) {
                $query->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', $surah));
            })
            ->when($filters['student'], function (Builder $query, string $student) {
                $term = '%'.$student.'%';
                $query->whereHas('user', function (Builder $user) use ($term) {
                    $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('reviewed_at', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('reviewed_at', '<=', $to))
            ->with(['user:id,name,email', 'ayah', 'task', 'reviewer:id,name'])
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }

    /**
     * Build a deterministic archive entry for every recording, using the
     * custom task identifier and grouping files by correct/wrong:
     * audio/correct/001001.wav, audio/wrong/001001x.wav.
     *
     * @param  Collection<int, Recording>  $recordings
     * @return array<int, string>
     */
    protected function archivePaths(Collection $recordings): array
    {
        $counts = $recordings->countBy(fn (Recording $recording) => $recording->taskCode());

        $paths = [];

        foreach ($recordings as $recording) {
            $code = $recording->taskCode();

            $paths[$recording->id] = 'audio/'.$recording->type.'/'.$code
                .($counts[$code] > 1 ? '-r'.$recording->id : '')
                .'.'.$recording->fileExtension();
        }

        return $paths;
    }

    protected function fallbackArchivePath(Recording $recording): string
    {
        return 'audio/'.$recording->type.'/'.$recording->taskCode().'-r'.$recording->id.'.'.$recording->fileExtension();
    }

    /**
     * Build the JSON dataset mapping document.
     *
     * @param  Collection<int, Recording>  $recordings
     * @param  array{type: ?string, surah: ?int, student: ?string, from: ?string, to: ?string}  $filters
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    protected function manifest(Collection $recordings, array $filters, User $user, array $paths): array
    {
        $records = $recordings->map(fn (Recording $recording) => [
            'file' => $paths[$recording->id] ?? $this->fallbackArchivePath($recording),
            'task_id' => $recording->recitation_task_id,
            'task_code' => $recording->taskCode(),
            'type' => $recording->type,
            'status' => $recording->status,
            'ayah_id' => $recording->ayah->id,
            'ayah_code' => $recording->ayah->code,
            'reference' => $recording->ayah->reference,
            'surah_number' => $recording->ayah->surah_number,
            'surah_name' => $recording->ayah->surah_name,
            'ayah_number' => $recording->ayah->ayah_number,
            'text_uthmani' => $recording->originalText(),
            'recited_text' => $recording->displayText(),
            'student' => [
                'id' => $recording->user_id,
                'name' => $recording->user?->name,
            ],
            'duration_ms' => $recording->duration_ms,
            'reviewed_at' => $recording->reviewed_at?->toIso8601String(),
            'reviewed_by' => $recording->reviewer?->name,
            'created_at' => $recording->created_at?->toIso8601String(),
        ])->values()->all();

        return [
            'dataset' => 'quran-recitation-dataset',
            'version' => '1.1.0',
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $user->name,
            'filters' => $filters,
            'counts' => [
                'total' => count($records),
                'correct' => $recordings->where('type', Recording::TYPE_CORRECT)->count(),
                'wrong' => $recordings->where('type', Recording::TYPE_WRONG)->count(),
            ],
            'recordings' => $records,
        ];
    }

    /**
     * @param  Collection<int, Recording>  $recordings
     * @param  array{type: ?string, surah: ?int, student: ?string, from: ?string, to: ?string}  $filters
     * @param  array<int, string>  $paths
     */
    protected function jsonContent(Collection $recordings, array $filters, User $user, array $paths): string
    {
        return json_encode(
            $this->manifest($recordings, $filters, $user, $paths),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';
    }

    /**
     * @param  Collection<int, Recording>  $recordings
     * @param  array<int, string>  $paths
     */
    protected function csvContent(Collection $recordings, array $paths): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, [
            'file', 'task_code', 'type', 'status', 'ayah_id', 'ayah_code', 'reference',
            'surah_number', 'surah_name', 'ayah_number', 'text_uthmani', 'recited_text',
            'student_id', 'student_name', 'student_email',
            'duration_ms', 'reviewed_at', 'reviewed_by',
        ], ',', '"', '');

        foreach ($recordings as $recording) {
            fputcsv($stream, [
                $paths[$recording->id] ?? $this->fallbackArchivePath($recording),
                $recording->taskCode(),
                $recording->type,
                $recording->status,
                $recording->ayah->id,
                $recording->ayah->code,
                $recording->ayah->reference,
                $recording->ayah->surah_number,
                $recording->ayah->surah_name,
                $recording->ayah->ayah_number,
                $recording->originalText(),
                $recording->displayText(),
                $recording->user_id,
                $this->csvSafe($recording->user?->name),
                $this->csvSafe($recording->user?->email),
                $recording->duration_ms,
                $recording->reviewed_at?->toIso8601String(),
                $this->csvSafe($recording->reviewer?->name),
            ], ',', '"', '');
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * Protect spreadsheet applications from formula injection.
     */
    protected function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    protected function readmeContent(): string
    {
        return implode("\n", [
            'حزمة بيانات التلاوات القرآنية',
            '=============================',
            '',
            'audio/correct : ملفات WAV للتلاوات الصحيحة، مثل 001001.wav.',
            'audio/wrong   : ملفات WAV للتلاوات الخاطئة، مثل 001001x.wav.',
            'dataset.csv   : ملف ربط بجميع الملفات والنصوص والبيانات الوصفية.',
            'manifest.json : نفس البيانات بصيغة JSON جاهزة لتدريب النماذج.',
            '',
            'task_code     : المعرّف المخصص، 001001 للصحيحة و001001x للخاطئة.',
            'text_uthmani  : النص الأصلي للآية بالرسم العثماني.',
            'recited_text  : النص الذي تلاّه الطالب (يختلف عن الأصل في العيّنة الخاطئة).',
            '',
            'كل ملف صوتي مرفق برقم السورة والآية ونوع التسجيل وبيانات الطالب',
            'ومدة التسجيل. جميع الملفات الصوتية بصيغة WAV أحادية 16kHz.',
            '',
            'تم إنشاء هذه الحزمة آليًا من منصة جمع بيانات التلاوة.',
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Ayah;
use App\Models\AyahReport;
use App\Models\Recording;
use App\Support\Quran;
use App\Support\QuranDatasetImporter;
use App\Support\QuranSamples;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class AyahManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $surahFilter = 'all';

    public bool $onlyReported = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public int|string $surah_number = 1;

    public int|string $ayah_number = 1;

    public string $text_uthmani = '';

    /**
     * The uploaded Excel/CSV file handled by Livewire.
     *
     * @var mixed
     */
    public $dataset = null;

    /**
     * Summary of the most recent dataset import.
     *
     * @var array<string, mixed>|null
     */
    public ?array $lastImport = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->onlyReported = request()->boolean('reported');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'surahFilter'], true)) {
            $this->resetPage();
        }
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->surah_number = is_numeric($this->surahFilter) ? (int) $this->surahFilter : 1;
        $this->ayah_number = 1;
        $this->text_uthmani = '';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $ayah = Ayah::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $ayah->id;
        $this->surah_number = $ayah->surah_number;
        $this->ayah_number = $ayah->ayah_number;
        $this->text_uthmani = $ayah->text_uthmani;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'surah_number' => ['required', 'integer', 'between:1,114'],
            'ayah_number' => ['required', 'integer', 'min:1', 'max:286'],
            'text_uthmani' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], [
            'surah_number' => 'رقم السورة',
            'ayah_number' => 'رقم الآية',
            'text_uthmani' => 'نص الآية بالرسم العثماني',
        ]);

        $surahNumber = (int) $this->surah_number;
        $ayahNumber = (int) $this->ayah_number;
        $text = trim($this->text_uthmani);
        $maxAyahs = Quran::ayahCount($surahNumber);

        if ($maxAyahs > 0 && $ayahNumber > $maxAyahs) {
            $this->addError('ayah_number', "سورة {$this->surahName($surahNumber)} تحتوي على {$maxAyahs} آية فقط.");

            return;
        }

        $duplicate = Ayah::query()
            ->where('surah_number', $surahNumber)
            ->where('ayah_number', $ayahNumber)
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('ayah_number', 'هذه الآية مسجلة مسبقًا في قاعدة البيانات.');

            return;
        }

        if ($this->editingId !== null) {
            $existing = Ayah::withCount('recordings')->findOrFail($this->editingId);

            $coordinateChanged = $existing->surah_number !== $surahNumber
                || $existing->ayah_number !== $ayahNumber;

            if ($coordinateChanged && $existing->recordings_count > 0) {
                $this->addError(
                    'ayah_number',
                    'لا يمكن تغيير السورة أو رقم الآية لوجود تسجيلات مرتبطة بها. حرّر نص الآية فقط.',
                );

                return;
            }

            $existing->update([
                'surah_number' => $surahNumber,
                'ayah_number' => $ayahNumber,
                'text_uthmani' => $text,
            ]);
            $message = 'تم تحديث الآية بنجاح.';
        } else {
            Ayah::create([
                'surah_number' => $surahNumber,
                'ayah_number' => $ayahNumber,
                'text_uthmani' => $text,
            ]);
            $message = 'تمت إضافة الآية بنجاح.';
        }

        $this->showModal = false;
        $this->reset('editingId', 'text_uthmani');
        unset($this->ayahs, $this->stats);
        $this->resetPage();

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function toggleReported(): void
    {
        $this->onlyReported = ! $this->onlyReported;
        unset($this->ayahs);
        $this->resetPage();
    }

    /**
     * Mark every pending report for an ayah as reviewed.
     */
    public function resolveReports(int $ayahId): void
    {
        AyahReport::query()
            ->where('ayah_id', $ayahId)
            ->pending()
            ->update([
                'status' => AyahReport::STATUS_RESOLVED,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        unset($this->ayahs, $this->stats);

        $this->dispatch('notify', message: 'تمت معالجة بلاغات هذه الآية.', type: 'success');
    }

    public function delete(int $id): void
    {
        $ayah = Ayah::withCount('recordings')->findOrFail($id);

        if ($ayah->recordings_count > 0) {
            $this->dispatch(
                'notify',
                message: 'لا يمكن حذف الآية لوجود تسجيلات مرتبطة بها. احذف التسجيلات أولًا.',
                type: 'error',
            );

            return;
        }

        $ayah->delete();
        unset($this->ayahs, $this->stats);
        $this->resetPage();

        $this->dispatch('notify', message: 'تم حذف الآية.', type: 'success');
    }

    public function importSamples(): void
    {
        $inserted = QuranSamples::seed();

        unset($this->ayahs, $this->stats);
        $this->resetPage();

        $this->dispatch(
            'notify',
            message: $inserted > 0
                ? "تمت إضافة {$inserted} آية من العينات الجاهزة."
                : 'كل الآيات الجاهزة موجودة مسبقًا.',
            type: 'success',
        );
    }

    /**
     * Import the full Quran dataset (XLSX or CSV) uploaded by the admin.
     */
    public function importDataset(QuranDatasetImporter $importer): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate([
            'dataset' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:20480'],
        ], [
            'dataset.required' => 'اختر ملف Excel أو CSV أولًا.',
            'dataset.file' => 'الملف المُرسل غير صالح.',
            'dataset.extensions' => 'الصيغ المدعومة: xlsx أو csv.',
            'dataset.max' => 'حجم الملف يتجاوز الحد المسموح (20 ميجابايت).',
        ]);

        $path = (string) $this->dataset->getRealPath();

        try {
            // Keep the ayah rows and the recitation task pool in one unit so a
            // failure never leaves a partially imported dataset behind.
            $result = DB::transaction(fn () => $importer->import($path));
        } catch (Throwable $exception) {
            $this->reset('dataset');
            $this->addError('dataset', 'تعذّر استيراد الملف: '.$exception->getMessage());

            return;
        }

        $this->reset('dataset');
        $this->lastImport = $result->toArray();

        unset($this->ayahs, $this->stats);
        $this->resetPage();

        $this->dispatch(
            'notify',
            message: 'اكتمل الاستيراد — '.$result->summary(),
            type: $result->hasChanges() ? 'success' : 'error',
        );
    }

    protected function surahName(int $number): string
    {
        return Quran::surahName($number);
    }

    /**
     * @return LengthAwarePaginator<int, Ayah>
     */
    #[Computed]
    public function ayahs(): LengthAwarePaginator
    {
        return Ayah::query()
            ->withCount([
                'recordings',
                'reports as pending_reports_count' => fn ($query) => $query->pending(),
            ])
            ->when($this->onlyReported, fn ($query) => $query->whereHas(
                'reports',
                fn ($reports) => $reports->pending(),
            ))
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('text_uthmani', 'like', $term)
                        ->orWhere('surah_name', 'like', $term);
                });
            })
            ->when($this->surahFilter !== 'all', fn ($query) => $query->where('surah_number', (int) $this->surahFilter))
            ->ordered()
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $ayahs = Ayah::count();

        return [
            'ayahs' => $ayahs,
            'expected' => Quran::TOTAL_AYAHS,
            'coverage' => (int) round($ayahs / Quran::TOTAL_AYAHS * 100),
            'surahs' => Ayah::query()->distinct()->count('surah_number'),
            'recorded' => Recording::query()->distinct()->count('ayah_id'),
            'reports' => AyahReport::query()->pending()->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.ayah-manager', [
            'surahs' => Quran::surahOptions(),
        ]);
    }
}

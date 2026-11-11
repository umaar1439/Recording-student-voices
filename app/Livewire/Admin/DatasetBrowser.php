<?php

namespace App\Livewire\Admin;

use App\Models\RecitationTask;
use App\Models\Recording;
use App\Support\Quran;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DatasetBrowser extends Component
{
    use WithPagination;

    public string $type = 'all';

    public string $surah = 'all';

    public string $student = '';

    public string $from = '';

    public string $to = '';

    public bool $showResetModal = false;

    public string $resetConfirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'surah', 'student', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('type', 'surah', 'student', 'from', 'to');
        $this->type = 'all';
        $this->surah = 'all';
        $this->resetPage();
        $this->resetValidation();
    }

    public function openResetModal(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->resetValidation();
        $this->resetConfirmation = '';
        $this->showResetModal = true;
    }

    public function cancelReset(): void
    {
        $this->showResetModal = false;
        $this->reset('resetConfirmation');
        $this->resetValidation();
    }

    /**
     * Delete every recording and its audio, then return the whole task pool to
     * "available" so students can start a new recitation round. This is
     * intentionally irreversible, hence the typed confirmation.
     */
    public function resetPlatform(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->validate([
            'resetConfirmation' => ['required', 'string', Rule::in(['تصفير'])],
        ], [
            'resetConfirmation.required' => 'اكتب كلمة «تصفير» لتأكيد إعادة ضبط المنصة.',
            'resetConfirmation.in' => 'كلمة التأكيد غير صحيحة؛ اكتب «تصفير» بالضبط.',
        ], [
            'resetConfirmation' => 'كلمة التأكيد',
        ]);

        $deleted = DB::transaction(function (): int {
            $deleted = Recording::query()->count();

            Recording::query()->delete();
            RecitationTask::resetPool();

            return $deleted;
        });

        Storage::disk('local')->deleteDirectory('recordings');

        $this->showResetModal = false;
        $this->reset('resetConfirmation');
        $this->resetValidation();
        unset($this->recordings, $this->stats, $this->typeBreakdown, $this->totalRecordings);

        $this->dispatch(
            'notify',
            message: 'تم تصفير المنصة وحذف '.number_format($deleted).' تسجيلًا، وأصبحت كل المهام متاحة من جديد.',
            type: 'success',
        );
    }

    /**
     * @return array<string, string>
     */
    public function exportQuery(): array
    {
        return array_filter([
            'type' => $this->type !== 'all' ? $this->type : null,
            'surah' => $this->surah !== 'all' ? $this->surah : null,
            'student' => trim($this->student) !== '' ? trim($this->student) : null,
            'from' => $this->from !== '' ? $this->from : null,
            'to' => $this->to !== '' ? $this->to : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return LengthAwarePaginator<int, Recording>
     */
    #[Computed]
    public function recordings(): LengthAwarePaginator
    {
        return Recording::query()
            ->approved()
            ->with(['user:id,name,email', 'ayah', 'task', 'reviewer:id,name'])
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->when($this->surah !== 'all', function ($query) {
                $query->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', (int) $this->surah));
            })
            ->when(trim($this->student) !== '', function ($query) {
                $term = '%'.trim($this->student).'%';
                $query->whereHas('user', function ($user) use ($term) {
                    $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->from !== '', fn ($query) => $query->whereDate('reviewed_at', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('reviewed_at', '<=', $this->to))
            ->latest('reviewed_at')
            ->paginate(10);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $base = Recording::query()->approved()->when($this->surah !== 'all', function ($query) {
            $query->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', (int) $this->surah));
        });

        return [
            'approved' => (clone $base)->count(),
            'correct' => (clone $base)->where('type', Recording::TYPE_CORRECT)->count(),
            'wrong' => (clone $base)->where('type', Recording::TYPE_WRONG)->count(),
            'students' => (clone $base)->distinct()->count('user_id'),
            'duration' => (int) ((clone $base)->sum('duration_ms') / 1000),
        ];
    }

    /**
     * @return Collection<int, \stdClass>
     */
    #[Computed]
    public function typeBreakdown(): Collection
    {
        return Recording::query()
            ->approved()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get();
    }

    #[Computed]
    public function totalRecordings(): int
    {
        return Recording::query()->count();
    }

    public function render(): View
    {
        return view('livewire.admin.dataset-browser', [
            'surahs' => Quran::surahOptions(),
            'user' => auth()->user(),
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Recording;
use App\Support\Quran;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function render(): View
    {
        return view('livewire.admin.dataset-browser', [
            'surahs' => Quran::surahOptions(),
            'user' => auth()->user(),
        ]);
    }
}

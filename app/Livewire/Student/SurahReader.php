<?php

namespace App\Livewire\Student;

use App\Models\Ayah;
use App\Models\AyahReport;
use App\Models\Recording;
use App\Support\Quran;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SurahReader extends Component
{
    use WithPagination;

    /**
     * The surah being read, kept in the query string so links are shareable.
     */
    #[Url(as: 'surah', history: true)]
    public string $surah = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isStudent(), 403, 'هذه الصفحة مخصصة للطلاب فقط.');

        $requested = request()->integer('surah');

        $this->surah = (string) (
            $requested >= 1 && $requested <= 114
                ? $requested
                : (Ayah::query()->min('surah_number') ?? 1)
        );
    }

    public function updatedSurah(string $value): void
    {
        if (! is_numeric($value) || (int) $value < 1 || (int) $value > 114) {
            $this->surah = (string) (Ayah::query()->min('surah_number') ?? 1);
        }

        $this->resetPage();
    }

    /**
     * Flag an ayah whose text may contain a spelling, harakat or tajweed
     * mistake. Admins see pending reports in the ayah manager.
     */
    public function report(int $ayahId): void
    {
        $user = auth()->user();

        abort_unless($user?->isStudent(), 403);

        $ayah = Ayah::find($ayahId);

        if ($ayah === null) {
            $this->dispatch('notify', message: 'الآية غير موجودة.', type: 'error');

            return;
        }

        $alreadyPending = AyahReport::query()
            ->where('ayah_id', $ayah->id)
            ->where('user_id', $user->id)
            ->pending()
            ->exists();

        AyahReport::updateOrCreate(
            ['ayah_id' => $ayah->id, 'user_id' => $user->id],
            ['status' => AyahReport::STATUS_PENDING, 'resolved_at' => null],
        );

        $this->dispatch(
            'notify',
            message: $alreadyPending
                ? 'بلاغك عن هذه الآية مسجّل بالفعل وسيراجعه المشرف.'
                : 'تم إرسال إشعار للمشرفين بأن هذه الآية تحتاج مراجعة. شكرًا لك.',
            type: $alreadyPending ? 'info' : 'success',
        );
    }

    /**
     * @return LengthAwarePaginator<int, Ayah>
     */
    #[Computed]
    public function ayahs(): LengthAwarePaginator
    {
        return Ayah::query()
            ->forSurah($this->surah)
            ->ordered()
            ->paginate(20);
    }

    /**
     * Aggregate recording status per ayah for the authenticated student.
     *
     * @return array<int, string> ayah id => approved|pending|rejected
     */
    #[Computed]
    public function recordings(): array
    {
        return Recording::query()
            ->where('user_id', auth()->id())
            ->whereIn('ayah_id', $this->ayahs->pluck('id'))
            ->get(['ayah_id', 'status'])
            ->groupBy('ayah_id')
            ->map(function ($items): string {
                $statuses = $items->pluck('status')->unique();

                if ($statuses->contains(Recording::STATUS_APPROVED)) {
                    return Recording::STATUS_APPROVED;
                }

                if ($statuses->contains(Recording::STATUS_PENDING)) {
                    return Recording::STATUS_PENDING;
                }

                return Recording::STATUS_REJECTED;
            })
            ->all();
    }

    /**
     * @return array{total: int, recorded: int, percent: int}
     */
    #[Computed]
    public function progress(): array
    {
        $total = Ayah::query()->forSurah($this->surah)->count();

        $recorded = Recording::query()
            ->where('user_id', auth()->id())
            ->whereIn('ayah_id', Ayah::query()->forSurah($this->surah)->select('id'))
            ->distinct()
            ->count('ayah_id');

        return [
            'total' => $total,
            'recorded' => $recorded,
            'percent' => $total > 0 ? (int) round($recorded / $total * 100) : 0,
        ];
    }

    public function render(): View
    {
        $surahNumber = (int) ($this->surah ?: 1);

        return view('livewire.student.surah-reader', [
            'surahs' => Quran::surahOptions(),
            'surahName' => Quran::surahFullName($surahNumber),
        ]);
    }
}

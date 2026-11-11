<?php

namespace App\Livewire\Teacher;

use App\Models\Recording;
use App\Models\User;
use App\Support\Quran;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ReviewQueue extends Component
{
    use WithPagination;

    public string $tab = Recording::STATUS_PENDING;

    public string $type = 'all';

    public string $surah = 'all';

    public string $search = '';

    public ?int $reviewingId = null;

    public string $decision = '';

    public string $note = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canReview(), 403, 'هذه الصفحة مخصصة للمعلمين والإدارة.');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'type', 'surah', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, Recording::STATUSES, true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->type = 'all';
        $this->surah = 'all';
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Approve a recording immediately.
     */
    public function approve(int $id): void
    {
        $this->decide($id, Recording::STATUS_APPROVED);
    }

    /**
     * Open the rejection modal so the teacher can leave a short note.
     */
    public function openReject(int $id): void
    {
        $recording = Recording::find($id);
        $user = auth()->user();

        if (! $recording || ! $user instanceof User || ! $recording->isReviewableBy($user)) {
            $this->dispatch('notify', message: 'هذا التسجيل غير متاح لمراجعتك.', type: 'error');

            return;
        }

        if (! $recording->isPending()) {
            $this->dispatch('notify', message: 'هذا التسجيل تمت مراجعته بالفعل.', type: 'error');

            return;
        }

        $this->reviewingId = $recording->id;
        $this->decision = Recording::STATUS_REJECTED;
        $this->note = '';
    }

    public function confirmReject(): void
    {
        $this->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'note' => 'ملاحظة الرفض',
        ]);

        if ($this->reviewingId === null) {
            return;
        }

        $this->decide($this->reviewingId, Recording::STATUS_REJECTED, $this->note);

        $this->reviewingId = null;
        $this->decision = '';
        $this->note = '';
    }

    public function cancelReject(): void
    {
        $this->reviewingId = null;
        $this->decision = '';
        $this->note = '';
        $this->resetValidation();
    }

    protected function decide(int $id, string $status, string $note = ''): void
    {
        $recording = Recording::find($id);
        $user = auth()->user();

        if (! $recording || ! $user instanceof User || ! $recording->isReviewableBy($user)) {
            $this->dispatch('notify', message: 'هذا التسجيل غير متاح لمراجعتك.', type: 'error');

            return;
        }

        if (! $recording->isPending()) {
            $this->dispatch('notify', message: 'هذا التسجيل تمت مراجعته بالفعل.', type: 'error');

            return;
        }

        $task = $recording->task;
        $repeatable = $task?->isRepeatable() ?? false;

        DB::transaction(function () use ($recording, $status, $note): void {
            $recording->update([
                'status' => $status,
                'reviewer_id' => auth()->id(),
                'reviewed_at' => now(),
                'review_notes' => $note !== '' ? $note : null,
            ]);

            // Approved tasks are completed forever; rejected tasks return to
            // the shared pool so another student can record them again. The
            // practice surah never leaves the pool.
            $task = $recording->task;

            if ($task === null) {
                return;
            }

            if ($status === Recording::STATUS_APPROVED) {
                $task->approve();
            } else {
                $task->release();
            }
        });

        unset($this->recordings, $this->counts);

        $message = match (true) {
            $status === Recording::STATUS_APPROVED => 'تم قبول التسجيل وإضافته إلى مجموعة البيانات.',
            $repeatable => 'تم رفض التسجيل، ويمكن للطالب إعادة تسجيل سورة الفاتحة.',
            default => 'تم رفض التسجيل، وأُعيدت المهمة إلى قائمة المهام المتاحة لطالب آخر.',
        };

        $this->dispatch(
            'notify',
            message: $message,
            type: $status === Recording::STATUS_APPROVED ? 'success' : 'info',
        );
    }

    /**
     * @return LengthAwarePaginator<int, Recording>
     */
    #[Computed]
    public function recordings(): LengthAwarePaginator
    {
        return $this->queueQuery()
            ->with(['user:id,name,email', 'ayah', 'task', 'reviewer:id,name'])
            ->when($this->tab !== 'all', fn ($query) => $query->where('status', $this->tab))
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->when($this->surah !== 'all', function ($query) {
                $query->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', (int) $this->surah));
            })
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('user', function ($user) use ($term) {
                        $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                    })->orWhereHas('ayah', function ($ayah) use ($term) {
                        $ayah->where('text_uthmani', 'like', $term);
                    })->orWhereHas('task', function ($task) use ($term) {
                        $task->where('code', 'like', $term)->orWhere('text_uthmani', 'like', $term);
                    });
                });
            })
            ->latest()
            ->paginate(8);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $base = $this->queueQuery();

        return [
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
            'rejected' => (clone $base)->where('status', Recording::STATUS_REJECTED)->count(),
            'correct' => (clone $base)->pending()->where('type', Recording::TYPE_CORRECT)->count(),
            'wrong' => (clone $base)->pending()->where('type', Recording::TYPE_WRONG)->count(),
        ];
    }

    /**
     * Base query for the review queue. Teachers only see recordings belonging
     * to students assigned to them; administrators see the whole queue.
     *
     * @return Builder<Recording>
     */
    protected function queueQuery(): Builder
    {
        $query = Recording::query();
        $user = auth()->user();

        if ($user instanceof User && ! $user->isAdmin()) {
            $query->whereHas('user', fn (Builder $student) => $student->where('teacher_id', $user->id));
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function surahOptions(): array
    {
        return Quran::surahOptions();
    }

    public function render(): View
    {
        return view('livewire.teacher.review-queue', [
            'surahs' => $this->surahOptions(),
        ]);
    }
}

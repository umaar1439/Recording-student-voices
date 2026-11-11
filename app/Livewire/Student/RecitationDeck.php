<?php

namespace App\Livewire\Student;

use App\Models\Ayah;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use App\Support\Quran;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class RecitationDeck extends Component
{
    use WithFileUploads;

    public string $surahFilter = 'all';

    public string $typeFilter = 'all';

    /** @var list<string> */
    public array $skipped = [];

    /**
     * The temporary upload handled by Livewire.
     *
     * @var mixed
     */
    public $audio = null;

    public int $durationMs = 0;

    /**
     * The teacher selected by the student for reviewing this submission.
     */
    public ?int $teacherId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isStudent(), 403, 'هذه الصفحة مخصصة للطلاب فقط.');

        $user = auth()->user();
        $this->teacherId = $user?->teacher_id;

        $surah = request()->integer('surah');

        if ($surah >= 1 && $surah <= 114) {
            $this->surahFilter = (string) $surah;
        } elseif ($user instanceof User && $this->hasRemainingFatihahTasks($user)) {
            $this->surahFilter = (string) RecitationTask::REPEATABLE_SURAH_NUMBER;
        }

        $this->reserveNextTask();
    }

    public function updatedSurahFilter(): void
    {
        $this->skipped = [];
        $this->reset('audio', 'durationMs');
        $this->releaseReservations();
        $this->reserveNextTask();
    }

    public function updatedTypeFilter(): void
    {
        $this->skipped = [];
        $this->reset('audio', 'durationMs');
        $this->releaseReservations();
        $this->reserveNextTask();
    }

    public function setTypeFilter(string $type): void
    {
        if (! in_array($type, ['all', ...Recording::TYPES], true)) {
            return;
        }

        $this->typeFilter = $type;
        $this->updatedTypeFilter();
    }

    public function resetSkipped(): void
    {
        $this->skipped = [];
        $this->reset('audio', 'durationMs');
        $this->releaseReservations();
        $this->reserveNextTask();
    }

    public function skip(string $key): void
    {
        $task = $this->tasks->firstWhere('key', $key);

        if ($task === null) {
            return;
        }

        $user = auth()->user();

        if ($user instanceof User) {
            $task['task']->releaseReservation($user);
        }

        if (! in_array($key, $this->skipped, true)) {
            $this->skipped[] = $key;
        }

        $this->reset('audio', 'durationMs');
        unset($this->tasks);
        $this->reserveNextTask();
    }

    /**
     * Persist the recorded audio for the given task.
     *
     * The task is claimed atomically (available => pending), so it instantly
     * disappears from every student's queue once a submission is accepted.
     */
    public function submit(string $key, int $durationMs = 0, ?string $uploadToken = null): bool
    {
        $user = auth()->user();

        abort_unless($user?->isStudent(), 403);

        $task = RecitationTask::query()->with('ayah')->where('code', $key)->first();

        if ($task === null || ! $task->isAvailable() || $task->ayah === null) {
            $this->discardUploadedAudio($uploadToken, $user->id);
            $this->reset('audio', 'durationMs');
            $this->addError('audio', 'هذه المهمة لم تعد متاحة، حدّث البطاقات ثم حاول مجددًا.');
            unset($this->tasks);
            $this->reserveNextTask();

            return false;
        }

        $this->durationMs = max(0, min($durationMs, 600_000));

        $existing = Recording::query()
            ->where('user_id', $user->id)
            ->where('ayah_id', $task->ayah_id)
            ->where('type', $task->type)
            ->latest('id')
            ->first();

        $retryingRejectedRepeatable = $existing !== null
            && $task->isRepeatable()
            && $existing->status === Recording::STATUS_REJECTED;

        if ($existing !== null && ! $retryingRejectedRepeatable) {
            $this->discardUploadedAudio($uploadToken, $user->id);
            $task->releaseReservation($user);
            $this->reset('audio', 'durationMs');
            $this->addError('audio', $existing->status === Recording::STATUS_REJECTED
                ? 'لا يمكن إعادة تسجيل هذه المهمة بعد رفضها.'
                : 'سبق أن سجّلت هذه المهمة، انتقل إلى البطاقة التالية.');
            unset($this->tasks);
            $this->reserveNextTask();

            return false;
        }

        $teacherId = $this->validateTeacherSelection();

        if ($uploadToken !== null) {
            $upload = Cache::get('recording-upload:'.$uploadToken);

            if (
                ! is_array($upload)
                || (int) ($upload['user_id'] ?? 0) !== $user->id
                || ! isset($upload['path'])
                || ! Storage::disk('local')->exists($upload['path'])
            ) {
                $this->reset('audio', 'durationMs');
                $this->addError('audio', 'انتهت صلاحية رفع التسجيل، يُرجى إعادة التسجيل والمحاولة مرة أخرى.');
                unset($this->tasks);

                return false;
            }

            Cache::forget('recording-upload:'.$uploadToken);
            $path = $upload['path'];
        } else {
            $this->validate([
                'audio' => ['required', 'file', 'mimes:wav,webm,weba,mp3,mpga,ogg,oga,m4a,mp4,aac', 'max:20480'],
            ], [
                'audio.required' => 'لم يصل أي تسجيل صوتي، يُرجى إعادة التسجيل والمحاولة مرة أخرى.',
                'audio.file' => 'الملف المُرسل غير صالح.',
                'audio.mimes' => 'صيغة الصوت غير مدعومة. الصيغ المسموحة: WAV، webm، mp3، ogg، m4a.',
                'audio.max' => 'حجم التسجيل يتجاوز الحد المسموح به (20 ميجابايت).',
            ]);

            $path = $this->audio->store('recordings/'.$user->id, 'local');
        }

        $replacedPath = $existing?->audio_path;

        try {
            $created = DB::transaction(function () use ($existing, $task, $user, $path, $teacherId): bool {
                if (! $task->claim($user)) {
                    return false;
                }

                if ($existing !== null) {
                    $updated = Recording::query()
                        ->whereKey($existing->id)
                        ->where('status', Recording::STATUS_REJECTED)
                        ->update([
                            'recitation_task_id' => $task->id,
                            'audio_path' => $path,
                            'duration_ms' => $this->durationMs > 0 ? $this->durationMs : null,
                            'status' => Recording::STATUS_PENDING,
                            'reviewer_id' => null,
                            'review_notes' => null,
                            'reviewed_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    if ($updated !== 1) {
                        return false;
                    }
                } else {
                    Recording::create([
                        'user_id' => $user->id,
                        'ayah_id' => $task->ayah_id,
                        'recitation_task_id' => $task->id,
                        'audio_path' => $path,
                        'type' => $task->type,
                        'status' => Recording::STATUS_PENDING,
                        'duration_ms' => $this->durationMs > 0 ? $this->durationMs : null,
                    ]);
                }

                if ($teacherId !== null && (int) $user->teacher_id !== $teacherId) {
                    $user->forceFill(['teacher_id' => $teacherId])->save();
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);
            $this->reset('audio', 'durationMs');
            $this->addError('audio', 'سبق أن سجّلت هذه المهمة، انتقل إلى البطاقة التالية.');
            unset($this->tasks);

            return false;
        }

        if (! $created) {
            Storage::disk('local')->delete($path);
            $this->reset('audio', 'durationMs');
            $this->addError('audio', $existing !== null
                ? 'تمت مراجعة تسجيلك السابق للتو، حدّث البطاقات ثم حاول مجددًا.'
                : 'سبق أن حجز طالب آخر هذه المهمة، حدّث البطاقات ثم اختر مهمة أخرى.');
            unset($this->tasks);
            $this->reserveNextTask();

            return false;
        }

        if ($existing !== null && is_string($replacedPath) && $replacedPath !== $path) {
            Storage::disk('local')->delete($replacedPath);
        }

        $this->reset('audio', 'durationMs');
        $this->skipped = array_values(array_diff($this->skipped, [$key]));
        unset($this->tasks);

        if (
            $this->surahFilter === (string) RecitationTask::REPEATABLE_SURAH_NUMBER
            && ! $this->hasRemainingFatihahTasks($user)
        ) {
            $this->surahFilter = 'all';
        }

        $this->reserveNextTask();

        $this->dispatch('recording-saved');
        $this->dispatch(
            'notify',
            message: 'تم إرسال تسجيلك بنجاح، سيتم مراجعته من قِبل المُعلّم.',
            type: 'success',
        );

        return true;
    }

    /**
     * Validate the selected teacher. The field is required as soon as at least
     * one teacher exists; otherwise submissions are left for admin review.
     */
    protected function validateTeacherSelection(): ?int
    {
        $hasTeachers = $this->teachers->isNotEmpty();

        if (! $hasTeachers && $this->teacherId === null) {
            return null;
        }

        $this->validate([
            'teacherId' => [
                $hasTeachers ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', User::ROLE_TEACHER),
                ),
            ],
        ], [
            'teacherId.required' => 'اختر المعلم المشرف قبل إرسال التسجيل.',
            'teacherId.integer' => 'المعلم المحدد غير صالح.',
            'teacherId.exists' => 'المعلم المحدد غير موجود.',
        ], [
            'teacherId' => 'المعلم المشرف',
        ]);

        return $this->teacherId;
    }

    /**
     * Delete an audio file uploaded through the raw WAV endpoint, but only when
     * the token belongs to the current student.
     */
    protected function discardUploadedAudio(?string $token, int $userId): void
    {
        if ($token === null) {
            return;
        }

        $upload = Cache::get('recording-upload:'.$token);

        if (! is_array($upload) || (int) ($upload['user_id'] ?? 0) !== $userId || ! isset($upload['path'])) {
            return;
        }

        Cache::forget('recording-upload:'.$token);
        Storage::disk('local')->delete($upload['path']);
    }

    /**
     * The available tasks for the current filters.
     *
     * Only tasks in the shared "available" state are considered. Any task the
     * student has already submitted (pending or approved) never returns to
     * their own queue; a rejected task returns only when it belongs to the
     * repeatable practice surah. A task reserved by another student is hidden
     * until the reservation expires.
     *
     * @return Collection<int, array{key: string, code: string, task: RecitationTask, ayah: Ayah, type: string, max_seconds: int, reserved_until: Carbon|null}>
     */
    #[Computed]
    public function tasks(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $tasks = $this->availableTasksQuery($user)
            ->with('ayah')
            ->orderByRaw('case when recitation_tasks.assigned_to = ? then 0 else 1 end', [$user->id])
            ->orderBy('ayahs.surah_number')
            ->orderBy('ayahs.ayah_number')
            ->orderBy('recitation_tasks.type')
            ->limit(80)
            ->get()
            ->map(fn (RecitationTask $task): array => [
                'key' => $task->code,
                'code' => $task->code,
                'task' => $task,
                'ayah' => $task->ayah,
                'type' => $task->type,
                'max_seconds' => RecitationTask::recordingLimitFor($task->text_uthmani),
                'reserved_until' => $task->isReservedBy($user) ? $task->reservationExpiresAt() : null,
            ])
            ->all();

        usort($tasks, function (array $a, array $b): int {
            $aSkipped = in_array($a['key'], $this->skipped, true) ? 1 : 0;
            $bSkipped = in_array($b['key'], $this->skipped, true) ? 1 : 0;

            return $aSkipped <=> $bSkipped;
        });

        return collect($tasks);
    }

    /**
     * Reserve the first task the student may receive. The atomic update makes
     * two students loading the deck at the same time race safely: only one of
     * them can hold the ayah.
     */
    protected function reserveNextTask(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $alreadyReserved = $this->availableTasksQuery($user)
            ->where('recitation_tasks.assigned_to', $user->id)
            ->where('recitation_tasks.assigned_at', '>', now()->subMinutes(RecitationTask::RESERVATION_MINUTES))
            ->exists();

        if ($alreadyReserved) {
            return;
        }

        $candidates = $this->availableTasksQuery($user)
            ->when(
                $this->skipped !== [],
                fn (Builder $query) => $query->whereNotIn('recitation_tasks.code', $this->skipped),
            )
            ->orderBy('ayahs.surah_number')
            ->orderBy('ayahs.ayah_number')
            ->orderBy('recitation_tasks.type')
            ->limit(20)
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->reserve($user)) {
                unset($this->tasks);

                return;
            }
        }
    }

    /**
     * Release any untouched reservation held by the current student.
     */
    protected function releaseReservations(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        RecitationTask::query()
            ->where('status', RecitationTask::STATUS_AVAILABLE)
            ->where('assigned_to', $user->id)
            ->update([
                'assigned_to' => null,
                'assigned_at' => null,
                'updated_at' => now(),
            ]);

        unset($this->tasks);
    }

    /**
     * Base query for tasks the current student can receive.
     *
     * @return Builder<RecitationTask>
     */
    protected function availableTasksQuery(User $user): Builder
    {
        return RecitationTask::query()
            ->available()
            ->reservableBy($user)
            ->withoutSubmissionBy($user)
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->surahFilter !== 'all', function (Builder $query): void {
                $query->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', (int) $this->surahFilter));
            })
            ->join('ayahs', 'ayahs.id', '=', 'recitation_tasks.ayah_id')
            ->select('recitation_tasks.*');
    }

    /**
     * Whether the current student still has an available Al-Fatihah task under
     * the active recitation type. Al-Fatihah is the mandatory first stop; once
     * it is exhausted the deck continues with the shared Quran pool.
     */
    protected function hasRemainingFatihahTasks(User $user): bool
    {
        return RecitationTask::query()
            ->available()
            ->reservableBy($user)
            ->withoutSubmissionBy($user)
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', RecitationTask::REPEATABLE_SURAH_NUMBER))
            ->exists();
    }

    /**
     * @return Collection<int, array{key: string, code: string, task: RecitationTask, ayah: Ayah, type: string}>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return $this->tasks->slice(1, 4)->values();
    }

    /**
     * Progress for the current filter selection (student centric).
     *
     * @return array{done: int, total: int, percent: int, remaining: int, available: int, pending: int, approved: int}
     */
    #[Computed]
    public function progress(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [
                'done' => 0,
                'total' => 0,
                'percent' => 0,
                'remaining' => 0,
                'available' => 0,
                'pending' => 0,
                'approved' => 0,
            ];
        }

        $userId = $user->id;

        $base = RecitationTask::query()
            ->when($this->typeFilter !== 'all', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->surahFilter !== 'all', function (Builder $query): void {
                $query->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', (int) $this->surahFilter));
            });

        $total = (clone $base)->count();

        $done = (clone $base)
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where(function (Builder $repeatable) use ($userId): void {
                        $repeatable
                            ->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', RecitationTask::REPEATABLE_SURAH_NUMBER))
                            ->whereHas('recordings', fn (Builder $recording) => $recording
                                ->where('user_id', $userId)
                                ->where('status', Recording::STATUS_APPROVED));
                    })
                    ->orWhere(function (Builder $other) use ($userId): void {
                        $other
                            ->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', '!=', RecitationTask::REPEATABLE_SURAH_NUMBER))
                            ->whereHas('recordings', fn (Builder $recording) => $recording->where('user_id', $userId));
                    });
            })
            ->count();

        $remaining = (clone $base)
            ->available()
            ->reservableBy($user)
            ->withoutSubmissionBy($user)
            ->count();

        return [
            'done' => $done,
            'total' => $total,
            'remaining' => $remaining,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'available' => $remaining,
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
        ];
    }

    /**
     * Teachers available for the student to select.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function teachers(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_TEACHER)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array<int, string>
     */
    public function surahOptions(): array
    {
        return Quran::surahOptions();
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $userId = auth()->id();

        return [
            'approved' => Recording::where('user_id', $userId)->approved()->count(),
            'pending' => Recording::where('user_id', $userId)->pending()->count(),
            'today' => Recording::where('user_id', $userId)->whereDate('created_at', today())->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.student.recitation-deck', [
            'surahs' => $this->surahOptions(),
            'stats' => $this->stats(),
        ]);
    }
}

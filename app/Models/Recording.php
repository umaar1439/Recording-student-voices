<?php

namespace App\Models;

use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'ayah_id',
    'recitation_task_id',
    'audio_path',
    'type',
    'status',
    'reviewer_id',
    'duration_ms',
    'review_notes',
    'reviewed_at',
])]
class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory;

    public const TYPE_CORRECT = 'correct';

    public const TYPE_WRONG = 'wrong';

    /** @var list<string> */
    public const TYPES = [self::TYPE_CORRECT, self::TYPE_WRONG];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Ayah, $this>
     */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }

    /**
     * The assignment pool entry this recording was submitted for.
     *
     * @return BelongsTo<RecitationTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(RecitationTask::class, 'recitation_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * The custom task code: "001001" for a correct sample and "001001x" for
     * a wrong recitation sample.
     */
    public function taskCode(): string
    {
        if ($this->task !== null) {
            return $this->task->code;
        }

        $code = $this->ayah?->code ?? (string) $this->ayah_id;

        return $this->type === self::TYPE_WRONG ? $code.'x' : $code;
    }

    /**
     * The text the student was asked to recite (altered for wrong tasks).
     */
    public function displayText(): string
    {
        return $this->task?->text_uthmani ?? $this->ayah?->text_uthmani ?? '';
    }

    /**
     * The authentic Uthmani text of the ayah, independent of the task type.
     */
    public function originalText(): string
    {
        return $this->ayah?->text_uthmani ?? '';
    }

    /**
     * @param  Builder<Recording>  $query
     * @return Builder<Recording>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<Recording>  $query
     * @return Builder<Recording>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @param  Builder<Recording>  $query
     * @return Builder<Recording>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return in_array($type, self::TYPES, true) ? $query->where('type', $type) : $query;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Students' own teachers may review them; admins may review everyone.
     */
    public function isReviewableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher()
            && $this->user !== null
            && (int) $this->user->teacher_id === (int) $user->id;
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_CORRECT => 'تلاوة صحيحة',
            self::TYPE_WRONG => 'تلاوة خاطئة',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'بانتظار المراجعة',
            self::STATUS_APPROVED => 'مقبول',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    public function audioUrl(): string
    {
        return route('recordings.audio', $this);
    }

    public function fileExtension(): string
    {
        return strtolower(pathinfo($this->audio_path, PATHINFO_EXTENSION) ?: 'webm');
    }

    /**
     * A human readable duration such as "00:12".
     */
    public function durationLabel(): string
    {
        $seconds = (int) round(((int) $this->duration_ms) / 1000);
        $seconds = max($seconds, 0);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Unique, filesystem safe file base name used when exporting datasets.
     */
    public function exportBaseName(): string
    {
        return $this->taskCode();
    }

    public function audioExists(): bool
    {
        return Storage::disk('local')->exists($this->audio_path);
    }

    protected static function booted(): void
    {
        static::deleted(function (Recording $recording) {
            if ($recording->audio_path !== '') {
                Storage::disk('local')->delete($recording->audio_path);
            }
        });
    }
}

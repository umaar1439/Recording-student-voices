<?php

namespace App\Models;

use App\Support\Quran;
use App\Support\WrongAyahMutator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single recitation assignment: one ayah, either the correct Uthmani text
 * ("001001") or a deliberately corrupted version ("001001x").
 *
 * Tasks form the platform's assignment pool. A task is claimed when a student
 * submits a recording (`available` => `pending`), permanently completed when
 * the teacher approves it (`pending` => `approved`) and released back to the
 * pool when it is rejected (`pending` => `available`).
 *
 * Surah Al-Fatihah is a repeatable practice surah: its tasks are never reserved
 * exclusively and never leave the pool, because every student must be able to
 * record them regardless of what other students have already submitted.
 */
#[Fillable([
    'ayah_id',
    'type',
    'code',
    'text_uthmani',
    'status',
    'assigned_to',
    'assigned_at',
    'approved_at',
])]
class RecitationTask extends Model
{
    public const TYPE_CORRECT = 'correct';

    public const TYPE_WRONG = 'wrong';

    /** @var list<string> */
    public const TYPES = [self::TYPE_CORRECT, self::TYPE_WRONG];

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_AVAILABLE, self::STATUS_PENDING, self::STATUS_APPROVED];

    /**
     * Suffix appended to the ayah identifier for wrong recitation samples.
     */
    public const WRONG_SUFFIX = 'x';

    /**
     * Surah Al-Fatihah (1) is open to every student at any time.
     */
    public const REPEATABLE_SURAH_NUMBER = 1;

    /**
     * Number of rows written per upsert statement. Kept small so the query
     * stays below SQLite's bound parameter limit.
     */
    protected const UPSERT_CHUNK = 100;

    /**
     * How long an ayah stays reserved for the student who received it before it
     * returns to the shared pool.
     */
    public const RESERVATION_MINUTES = 20;

    /**
     * Recording duration grows with the ayah text, with a hard ceiling low
     * enough to keep a 16 kHz mono WAV below the 20 MB upload limit.
     */
    public const MIN_RECORDING_SECONDS = 30;

    public const MAX_RECORDING_SECONDS = 600;

    public const RECORDING_BASE_SECONDS = 20;

    public const RECORDING_SECONDS_PER_LETTER = 0.5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ayah_id' => 'integer',
            'assigned_to' => 'integer',
            'assigned_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ayah, $this>
     */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }

    /**
     * The student currently assigned to submit this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Recording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    /**
     * The custom dataset identifier: "001001" for a correct task and
     * "001001x" for a wrong one.
     */
    public static function codeFor(int|Ayah $ayah, string $type): string
    {
        $id = $ayah instanceof Ayah ? (int) $ayah->id : $ayah;

        $code = Quran::formatAyahId($id);

        return $type === self::TYPE_WRONG ? $code.self::WRONG_SUFFIX : $code;
    }

    /**
     * The text the student must recite for the given task type.
     */
    public static function textFor(Ayah $ayah, string $type): string
    {
        return $type === self::TYPE_WRONG
            ? WrongAyahMutator::mutate($ayah->text_uthmani, (int) $ayah->id)
            : $ayah->text_uthmani;
    }

    /**
     * @return array{ayah_id: int, type: string, code: string, text_uthmani: string}
     */
    public static function attributesFor(Ayah $ayah, string $type): array
    {
        return [
            'ayah_id' => (int) $ayah->id,
            'type' => $type,
            'code' => self::codeFor($ayah, $type),
            'text_uthmani' => self::textFor($ayah, $type),
        ];
    }

    /**
     * Create or refresh the two tasks belonging to a single ayah.
     *
     * Existing rows keep their workflow state; only the identifier and the
     * displayed texts are refreshed.
     */
    public static function syncForAyah(Ayah $ayah): void
    {
        $now = now();
        $rows = [];

        foreach (self::TYPES as $type) {
            $rows[] = self::attributesFor($ayah, $type) + [
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        static::query()->upsert(
            $rows,
            ['ayah_id', 'type'],
            ['code', 'text_uthmani', 'updated_at'],
        );
    }

    /**
     * Generate (or refresh) the correct/wrong task pair for every stored ayah.
     *
     * @return int number of task rows synchronised
     */
    public static function syncAll(): int
    {
        $synced = 0;
        $buffer = [];
        $now = now();

        foreach (Ayah::query()->select(['id', 'text_uthmani'])->lazyById(self::UPSERT_CHUNK, 'id') as $ayah) {
            foreach (self::TYPES as $type) {
                $buffer[] = self::attributesFor($ayah, $type) + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($buffer) >= self::UPSERT_CHUNK) {
                static::flush($buffer);
                $synced += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            static::flush($buffer);
            $synced += count($buffer);
        }

        return $synced;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected static function flush(array $rows): void
    {
        static::query()->upsert(
            $rows,
            ['ayah_id', 'type'],
            ['code', 'text_uthmani', 'updated_at'],
        );
    }

    /**
     * Estimate a generous recording window from the length of the text. A page
     * length ayah needs several minutes, while a short ayah should not be cut
     * off at an arbitrary fixed limit.
     */
    public static function recordingLimitFor(string $text): int
    {
        $letters = preg_replace(
            '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\s]+/u',
            '',
            $text,
        );

        $letterCount = mb_strlen(is_string($letters) ? $letters : $text);

        $seconds = self::RECORDING_BASE_SECONDS
            + (int) ceil($letterCount * self::RECORDING_SECONDS_PER_LETTER);

        return max(self::MIN_RECORDING_SECONDS, min(self::MAX_RECORDING_SECONDS, $seconds));
    }

    /**
     * Whether the task is currently held by another student.
     */
    public function isFreeFor(User $student): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        if ($this->assigned_to === null || $this->assigned_at === null) {
            return true;
        }

        if ((int) $this->assigned_to === (int) $student->id) {
            return true;
        }

        return $this->assigned_at->lessThanOrEqualTo(self::reservationCutoff());
    }

    /**
     * Whether this task is reserved for the given student right now.
     */
    public function isReservedBy(User $student): bool
    {
        return $this->isAvailable()
            && (int) $this->assigned_to === (int) $student->id
            && $this->assigned_at !== null
            && $this->assigned_at->greaterThan(self::reservationCutoff());
    }

    public function reservationExpiresAt(): ?Carbon
    {
        if ($this->assigned_at === null || ! $this->isAvailable()) {
            return null;
        }

        return $this->assigned_at->copy()->addMinutes(self::RESERVATION_MINUTES);
    }

    /**
     * Atomically reserve an available task for a student. Returns false when
     * another student holds a live reservation.
     */
    public function reserve(User $student): bool
    {
        if ($this->isRepeatable()) {
            return false;
        }

        $now = now();

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_AVAILABLE)
            ->reservableBy($student)
            ->update([
                'assigned_to' => $student->id,
                'assigned_at' => $now,
                'approved_at' => null,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * Return an untouched reservation to the shared pool (e.g. when skipped).
     */
    public function releaseReservation(User $student): void
    {
        if ($this->isRepeatable()) {
            return;
        }

        static::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_AVAILABLE)
            ->where('assigned_to', $student->id)
            ->update([
                'assigned_to' => null,
                'assigned_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Atomically move an available task into review for the given student.
     */
    public function claim(User $student): bool
    {
        if ($this->isRepeatable()) {
            return true;
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_AVAILABLE)
            ->reservableBy($student)
            ->update([
                'status' => self::STATUS_PENDING,
                'assigned_to' => $student->id,
                'assigned_at' => now(),
                'approved_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * Permanently complete the task once its recording is approved.
     */
    public function approve(): void
    {
        if ($this->isRepeatable()) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
        ])->save();
    }

    /**
     * Return a rejected task to the shared available pool.
     */
    public function release(): void
    {
        if ($this->isRepeatable()) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_AVAILABLE,
            'assigned_to' => null,
            'assigned_at' => null,
            'approved_at' => null,
        ])->save();
    }

    /**
     * Return every task to the shared pool for a fresh recitation round.
     */
    public static function resetPool(): void
    {
        static::query()->update([
            'status' => self::STATUS_AVAILABLE,
            'assigned_to' => null,
            'assigned_at' => null,
            'approved_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Whether this task belongs to the always-available practice surah.
     */
    public function isRepeatable(): bool
    {
        return Quran::coordinatesFromId((int) $this->ayah_id)[0] === self::REPEATABLE_SURAH_NUMBER;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function typeLabel(): string
    {
        return Recording::typeLabels()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_AVAILABLE => 'متاحة',
            self::STATUS_PENDING => 'بانتظار المراجعة',
            self::STATUS_APPROVED => 'مكتملة',
        ][$this->status] ?? $this->status;
    }

    /**
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Hide tasks the student has already recorded, except repeatable tasks
     * whose only recording was rejected so the student may try again.
     *
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopeWithoutSubmissionBy(Builder $query, User $student): Builder
    {
        return $query->where(function (Builder $outer) use ($student): void {
            $outer
                ->whereNotExists(function ($sub) use ($student): void {
                    $sub->selectRaw('1')
                        ->from('recordings')
                        ->whereColumn('recordings.ayah_id', 'recitation_tasks.ayah_id')
                        ->whereColumn('recordings.type', 'recitation_tasks.type')
                        ->where('recordings.user_id', $student->id)
                        ->whereIn('recordings.status', [Recording::STATUS_PENDING, Recording::STATUS_APPROVED]);
                })
                ->where(function (Builder $inner) use ($student): void {
                    $inner
                        ->whereHas('ayah', fn (Builder $ayah) => $ayah->where('surah_number', self::REPEATABLE_SURAH_NUMBER))
                        ->orWhereNotExists(function ($sub) use ($student): void {
                            $sub->selectRaw('1')
                                ->from('recordings')
                                ->whereColumn('recordings.ayah_id', 'recitation_tasks.ayah_id')
                                ->whereColumn('recordings.type', 'recitation_tasks.type')
                                ->where('recordings.user_id', $student->id);
                        });
                });
        });
    }

    /**
     * Tasks a student may receive: free, their own reservation, or a task whose
     * previous reservation has expired.
     *
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopeReservableBy(Builder $query, User $student): Builder
    {
        $cutoff = self::reservationCutoff();

        return $query->where(function (Builder $inner) use ($student, $cutoff): void {
            $inner->whereNull('assigned_to')
                ->orWhereNull('assigned_at')
                ->orWhere('assigned_at', '<=', $cutoff)
                ->orWhere('assigned_to', $student->id);
        });
    }

    /**
     * @param  Builder<RecitationTask>  $query
     * @return Builder<RecitationTask>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return in_array($type, self::TYPES, true) ? $query->where('type', $type) : $query;
    }

    protected static function reservationCutoff(): Carbon
    {
        return now()->subMinutes(self::RESERVATION_MINUTES);
    }
}
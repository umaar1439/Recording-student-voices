<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'teacher_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    /**
     * All supported roles.
     *
     * @var list<string>
     */
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_TEACHER, self::ROLE_STUDENT];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'teacher_id' => 'integer',
        ];
    }

    /**
     * The teacher responsible for reviewing this student's recordings.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(self::class, 'teacher_id');
    }

    /**
     * Students assigned to this teacher.
     *
     * @return HasMany<User, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(self::class, 'teacher_id');
    }

    /**
     * Recordings submitted by the user (as a student).
     *
     * @return HasMany<Recording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    /**
     * Recordings reviewed by the user (as a teacher / admin).
     *
     * @return HasMany<Recording, $this>
     */
    public function reviewedRecordings(): HasMany
    {
        return $this->hasMany(Recording::class, 'reviewer_id');
    }

    /**
     * Ayah text reports submitted by the user (as a student).
     *
     * @return HasMany<AyahReport, $this>
     */
    public function ayahReports(): HasMany
    {
        return $this->hasMany(AyahReport::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * Whether the user may review recordings.
     */
    public function canReview(): bool
    {
        return $this->isAdmin() || $this->isTeacher();
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? $this->role;
    }

    /**
     * Arabic labels for every role.
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMIN => 'مدير النظام',
            self::ROLE_TEACHER => 'مُعلّم',
            self::ROLE_STUDENT => 'طالب',
        ];
    }

    /**
     * Human friendly initials for avatars.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name)) ?: [];

        return mb_substr(implode('', array_map(fn ($part) => mb_substr($part, 0, 1), array_slice($parts, 0, 2))), 0, 2);
    }
}

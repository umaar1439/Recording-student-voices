<?php

namespace Database\Factories;

use App\Models\Ayah;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recording>
 */
class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            // Reuse an existing verse when the full mushaf is already imported.
            'ayah_id' => Ayah::query()->inRandomOrder()->value('id') ?? Ayah::factory(),
            'audio_path' => 'recordings/demo/'.fake()->uuid().'.wav',
            'type' => fake()->randomElement(Recording::TYPES),
            'recitation_task_id' => function (array $attributes): ?int {
                $ayahId = (int) $attributes['ayah_id'];
                $type = (string) $attributes['type'];

                $taskId = RecitationTask::query()
                    ->where('ayah_id', $ayahId)
                    ->where('type', $type)
                    ->value('id');

                if ($taskId !== null) {
                    return (int) $taskId;
                }

                $ayah = Ayah::find($ayahId);

                if ($ayah === null) {
                    return null;
                }

                return (int) RecitationTask::query()->create(
                    RecitationTask::attributesFor($ayah, $type),
                )->id;
            },
            'status' => Recording::STATUS_PENDING,
            'duration_ms' => fake()->numberBetween(2500, 20000),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Recording $recording): void {
            $task = $recording->task;

            if ($task === null) {
                return;
            }

            // Al-Fatihah tasks stay in the shared pool regardless of how many
            // recordings (pending, approved or rejected) reference them.
            if ($task->isRepeatable()) {
                $task->forceFill([
                    'status' => RecitationTask::STATUS_AVAILABLE,
                    'assigned_to' => null,
                    'assigned_at' => null,
                    'approved_at' => null,
                ])->save();

                return;
            }

            $state = match ($recording->status) {
                Recording::STATUS_APPROVED => [
                    'status' => RecitationTask::STATUS_APPROVED,
                    'assigned_to' => $recording->user_id,
                    'assigned_at' => $recording->created_at,
                    'approved_at' => $recording->reviewed_at ?? now(),
                ],
                Recording::STATUS_PENDING => [
                    'status' => RecitationTask::STATUS_PENDING,
                    'assigned_to' => $recording->user_id,
                    'assigned_at' => $recording->created_at ?? now(),
                    'approved_at' => null,
                ],
                default => [
                    'status' => RecitationTask::STATUS_AVAILABLE,
                    'assigned_to' => null,
                    'assigned_at' => null,
                    'approved_at' => null,
                ],
            };

            $task->forceFill($state)->save();
        });
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Recording::STATUS_APPROVED,
            'reviewer_id' => User::factory()->teacher(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Recording::STATUS_REJECTED,
            'reviewer_id' => User::factory()->teacher(),
            'reviewed_at' => now(),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Student\RecitationDeck;
use App\Livewire\Teacher\ReviewQueue;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
        Storage::fake('local');
    }

    protected function nonRepeatableTask(string $type = RecitationTask::TYPE_CORRECT): RecitationTask
    {
        return RecitationTask::query()
            ->where('type', $type)
            ->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', '!=', RecitationTask::REPEATABLE_SURAH_NUMBER))
            ->orderBy('id')
            ->firstOrFail();
    }

    protected function fatihahTask(string $type = RecitationTask::TYPE_CORRECT): RecitationTask
    {
        return RecitationTask::query()
            ->where('type', $type)
            ->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', RecitationTask::REPEATABLE_SURAH_NUMBER))
            ->orderBy('id')
            ->firstOrFail();
    }

    public function test_teacher_can_approve_a_pending_recording(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $task = $this->nonRepeatableTask();
        $recording = Recording::factory()
            ->for($student)
            ->for($task->ayah)
            ->ofType(RecitationTask::TYPE_CORRECT)
            ->create();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('approve', $recording->id)
            ->assertHasNoErrors();

        $recording->refresh();

        $this->assertSame(Recording::STATUS_APPROVED, $recording->status);
        $this->assertSame($teacher->id, $recording->reviewer_id);
        $this->assertNotNull($recording->reviewed_at);
        $this->assertSame(RecitationTask::STATUS_APPROVED, $recording->task->status);
        $this->assertNotNull($recording->task->approved_at);
    }

    public function test_teacher_can_reject_a_recording_with_a_note(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $task = $this->nonRepeatableTask();
        $recording = Recording::factory()
            ->for($student)
            ->for($task->ayah)
            ->ofType(RecitationTask::TYPE_CORRECT)
            ->create();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('openReject', $recording->id)
            ->assertSet('reviewingId', $recording->id)
            ->set('note', 'المدّ في الكلمة غير صحيح.')
            ->call('confirmReject')
            ->assertHasNoErrors()
            ->assertSet('reviewingId', null);

        $recording->refresh();

        $this->assertSame(Recording::STATUS_REJECTED, $recording->status);
        $this->assertSame('المدّ في الكلمة غير صحيح.', $recording->review_notes);
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $recording->task->status);
        $this->assertNull($recording->task->assigned_to);
    }

    public function test_rejected_task_returns_to_the_pool_for_another_student(): void
    {
        $teacher = User::factory()->teacher()->create();
        $first = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $second = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $task = $this->nonRepeatableTask();

        Livewire::actingAs($first)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('first.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $recording = Recording::sole();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('openReject', $recording->id)
            ->set('note', 'أعد التسجيل.')
            ->call('confirmReject');

        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->fresh()->status);
        $this->assertNull($task->fresh()->assigned_to);

        // The student who submitted it never receives the task again.
        Livewire::actingAs($first)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertDontSee($task->code);

        // Another student can pick it up from the shared pool.
        Livewire::actingAs($second)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertSee($task->code)
            ->set('audio', UploadedFile::fake()->create('second.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $task->refresh();

        $this->assertSame(RecitationTask::STATUS_PENDING, $task->status);
        $this->assertSame($second->id, $task->assigned_to);
        $this->assertSame(2, Recording::count());
    }

    public function test_approved_task_disappears_permanently_from_the_pool(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $other = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $task = $this->nonRepeatableTask(RecitationTask::TYPE_WRONG);

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('wrong.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('approve', Recording::sole()->id);

        $this->assertSame(RecitationTask::STATUS_APPROVED, $task->fresh()->status);

        Livewire::actingAs($other)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_WRONG)
            ->assertDontSee($task->code);
    }

    public function test_rejection_note_must_not_exceed_the_limit(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $recording = Recording::factory()->for($student)->create();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('openReject', $recording->id)
            ->set('note', str_repeat('ط', 501))
            ->call('confirmReject')
            ->assertHasErrors(['note' => 'max']);

        $this->assertSame(Recording::STATUS_PENDING, $recording->fresh()->status);
    }

    public function test_already_reviewed_recordings_are_ignored(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $recording = Recording::factory()->for($student)->approved()->create(['reviewer_id' => null, 'reviewed_at' => null]);

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('approve', $recording->id);

        $this->assertNull($recording->fresh()->reviewer_id);
    }

    public function test_status_tabs_and_filters_update_the_queue(): void
    {
        $teacher = User::factory()->teacher()->create();
        $students = User::factory()->student()->count(3)->create(['teacher_id' => $teacher->id]);

        Recording::factory()->for($students[0])->create();
        Recording::factory()->for($students[1])->create();
        Recording::factory()->for($students[2])->approved()->create();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->assertSet('tab', Recording::STATUS_PENDING)
            ->call('setTab', Recording::STATUS_APPROVED)
            ->assertSet('tab', Recording::STATUS_APPROVED)
            ->call('setTab', 'invalid')
            ->assertSet('tab', Recording::STATUS_APPROVED)
            ->set('search', 'لا يوجد تطابق')
            ->assertSee('لا توجد تسجيلات')
            ->call('clearFilters')
            ->assertSet('search', '');
    }

    public function test_rejected_al_fatihah_recording_can_be_resubmitted(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $teacher->id]);
        $task = $this->fatihahTask();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('fatihah-first.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $recording = Recording::sole();

        Livewire::actingAs($teacher)
            ->test(ReviewQueue::class)
            ->call('openReject', $recording->id)
            ->set('note', 'أعد تسجيل السورة كاملة.')
            ->call('confirmReject');

        $this->assertSame(Recording::STATUS_REJECTED, $recording->fresh()->status);
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->fresh()->status);

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertSee($task->code)
            ->set('audio', UploadedFile::fake()->create('fatihah-second.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $recording->refresh();

        $this->assertSame(1, Recording::count());
        $this->assertSame(Recording::STATUS_PENDING, $recording->status);
        $this->assertNull($recording->reviewer_id);
        $this->assertNull($recording->reviewed_at);
        $this->assertNull($recording->review_notes);
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->fresh()->status);
    }

    public function test_teacher_only_sees_recordings_from_assigned_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $ownStudent = User::factory()->student()->create([
            'name' => 'طالب هذا المعلم',
            'teacher_id' => $teacher->id,
        ]);
        $otherStudent = User::factory()->student()->create([
            'name' => 'طالب معلم آخر',
            'teacher_id' => $otherTeacher->id,
        ]);

        $ownRecording = Recording::factory()->for($ownStudent)->create();
        $otherRecording = Recording::factory()->for($otherStudent)->create();

        $component = Livewire::actingAs($teacher)->test(ReviewQueue::class);

        $component
            ->assertSee($ownStudent->name)
            ->assertDontSee($otherStudent->name);

        $this->assertSame(1, $component->instance()->counts['pending']);

        $component->call('approve', $otherRecording->id);
        $this->assertSame(Recording::STATUS_PENDING, $otherRecording->fresh()->status);

        $component->call('approve', $ownRecording->id);
        $this->assertSame(Recording::STATUS_APPROVED, $ownRecording->fresh()->status);
    }

    public function test_admin_sees_recordings_from_every_teacher(): void
    {
        $firstTeacher = User::factory()->teacher()->create();
        $secondTeacher = User::factory()->teacher()->create();
        $firstStudent = User::factory()->student()->create([
            'name' => 'طالب المعلم الأول',
            'teacher_id' => $firstTeacher->id,
        ]);
        $secondStudent = User::factory()->student()->create([
            'name' => 'طالب المعلم الثاني',
            'teacher_id' => $secondTeacher->id,
        ]);

        Recording::factory()->for($firstStudent)->create();
        Recording::factory()->for($secondStudent)->create();

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(ReviewQueue::class);

        $component
            ->assertSee($firstStudent->name)
            ->assertSee($secondStudent->name);

        $this->assertSame(2, $component->instance()->counts['pending']);
    }
}

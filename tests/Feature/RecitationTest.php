<?php

namespace Tests\Feature;

use App\Livewire\Student\RecitationDeck;
use App\Models\Ayah;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
        Storage::fake('local');
    }

    protected function taskFor(Ayah $ayah, string $type = RecitationTask::TYPE_CORRECT): RecitationTask
    {
        return RecitationTask::query()
            ->where('ayah_id', $ayah->id)
            ->where('type', $type)
            ->firstOrFail();
    }

    /**
     * A task from a surah other than Al-Fatihah, which follows the normal
     * reserve/claim/approve lifecycle.
     */
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

    /**
     * Build a minimal valid 16 kHz mono PCM WAV payload.
     */
    protected function wavContent(int $frames = 320): string
    {
        $data = str_repeat("\x00\x00", $frames);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '
            .pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', 16000).pack('V', 32000)
            .pack('v', 2).pack('v', 16)
            .'data'.pack('V', strlen($data)).$data;
    }

    public function test_raw_wav_upload_endpoint_stores_the_file_and_returns_a_token(): void
    {
        $student = User::factory()->student()->create();
        $ayah = Ayah::ordered()->firstOrFail();
        $task = $this->taskFor($ayah);
        $content = $this->wavContent();

        $response = $this->actingAs($student)->call(
            'POST',
            route('student.recordings.upload'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'audio/wav', 'HTTP_ACCEPT' => 'application/json'],
            $content,
        );

        $response->assertOk()->assertJsonStructure(['token']);

        $token = $response->json('token');

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->call('submit', $task->code, 2000, $token)
            ->assertHasNoErrors();

        $recording = Recording::sole();

        $this->assertSame($task->id, $recording->recitation_task_id);
        $this->assertSame('wav', $recording->fileExtension());
        Storage::disk('local')->assertExists($recording->audio_path);
        $this->assertSame($content, Storage::disk('local')->get($recording->audio_path));
    }

    public function test_raw_wav_upload_rejects_non_wav_payloads_and_non_students(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->call(
            'POST',
            route('student.recordings.upload'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'audio/wav', 'HTTP_ACCEPT' => 'application/json'],
            'not-a-wav-file',
        )->assertStatus(422);

        $this->actingAs(User::factory()->teacher()->create())->call(
            'POST',
            route('student.recordings.upload'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'audio/wav', 'HTTP_ACCEPT' => 'application/json'],
            $this->wavContent(),
        )->assertForbidden();
    }

    public function test_raw_wav_upload_rejects_a_non_16khz_wav(): void
    {
        $student = User::factory()->student()->create();
        $content = $this->wavContent();

        // Rewrite the sample rate to 44.1 kHz while keeping a valid WAV header.
        $content = substr_replace($content, pack('V', 44100), 24, 4);

        $this->actingAs($student)->call(
            'POST',
            route('student.recordings.upload'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'audio/wav', 'HTTP_ACCEPT' => 'application/json'],
            $content,
        )->assertStatus(422);
    }

    public function test_each_ayah_has_a_correct_and_a_wrong_task_with_distinct_codes(): void
    {
        $ayah = Ayah::ordered()->firstOrFail();

        $correct = $this->taskFor($ayah, RecitationTask::TYPE_CORRECT);
        $wrong = $this->taskFor($ayah, RecitationTask::TYPE_WRONG);

        $this->assertSame($ayah->code, $correct->code);
        $this->assertSame($ayah->code.'x', $wrong->code);
        $this->assertSame($ayah->text_uthmani, $correct->text_uthmani);
        $this->assertNotSame($correct->text_uthmani, $wrong->text_uthmani);
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $correct->status);
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $wrong->status);
    }

    public function test_student_can_submit_a_valid_wav_recording_and_the_task_disappears(): void
    {
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();
        $ayah = $task->ayah;

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->set('audio', UploadedFile::fake()->create('recitation.wav', 200, 'audio/wav'))
            ->call('submit', $task->code, 4200)
            ->assertHasNoErrors()
            ->assertReturned(true)
            ->assertDontSee($task->code);

        $recording = Recording::sole();

        $this->assertSame($student->id, $recording->user_id);
        $this->assertSame($ayah->id, $recording->ayah_id);
        $this->assertSame($task->id, $recording->recitation_task_id);
        $this->assertSame(Recording::STATUS_PENDING, $recording->status);
        $this->assertSame(4200, $recording->duration_ms);

        Storage::disk('local')->assertExists($recording->audio_path);

        $task->refresh();

        // The task leaves the shared pool and is assigned to this student.
        $this->assertSame(RecitationTask::STATUS_PENDING, $task->status);
        $this->assertSame($student->id, $task->assigned_to);
    }

    public function test_student_can_record_a_wrong_task_using_the_altered_text(): void
    {
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask(RecitationTask::TYPE_WRONG);
        $ayah = $task->ayah;

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_WRONG)
            ->set('audio', UploadedFile::fake()->create('wrong.wav', 120, 'audio/wav'))
            ->call('submit', $task->code, 3000)
            ->assertHasNoErrors();

        $recording = Recording::sole();

        $this->assertSame(Recording::TYPE_WRONG, $recording->type);
        $this->assertSame($ayah->code.'x', $recording->taskCode());
        $this->assertSame($task->text_uthmani, $recording->displayText());
        $this->assertSame($ayah->text_uthmani, $recording->originalText());
    }

    public function test_a_task_cannot_be_submitted_by_a_second_student_once_claimed(): void
    {
        $first = User::factory()->student()->create();
        $second = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        $this->assertTrue($task->claim($first));

        Livewire::actingAs($second)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('late.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasErrors(['audio'])
            ->assertReturned(false);

        $this->assertSame(0, Recording::count());
        $this->assertSame($first->id, $task->fresh()->assigned_to);
    }

    public function test_recording_requires_an_audio_file(): void
    {
        $student = User::factory()->student()->create();
        $ayah = Ayah::ordered()->firstOrFail();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->call('submit', $this->taskFor($ayah)->code)
            ->assertHasErrors(['audio' => 'required']);

        $this->assertSame(0, Recording::count());
    }

    public function test_student_cannot_record_the_same_ayah_twice(): void
    {
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();
        $ayah = $task->ayah;

        Recording::factory()->for($student)->for($ayah)->ofType(Recording::TYPE_CORRECT)->create();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('duplicate.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasErrors(['audio']);

        $this->assertSame(1, Recording::count());
    }

    public function test_al_fatihah_tasks_remain_available_for_every_student(): void
    {
        $first = User::factory()->student()->create();
        $second = User::factory()->student()->create();
        $task = $this->fatihahTask();

        Livewire::actingAs($first)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->set('audio', UploadedFile::fake()->create('first-fatihah.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $task->refresh();

        // Al-Fatihah is never reserved, claimed or completed by a submission.
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->status);
        $this->assertNull($task->assigned_to);

        Livewire::actingAs($second)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertSee($task->code)
            ->set('audio', UploadedFile::fake()->create('second-fatihah.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $this->assertSame(2, Recording::query()->where('recitation_task_id', $task->id)->count());
        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->fresh()->status);
    }

    public function test_student_without_a_teacher_must_choose_one_before_submitting(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('audio', UploadedFile::fake()->create('unassigned.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasErrors(['teacherId' => 'required']);

        $this->assertSame(0, Recording::count());
        $this->assertNull($student->fresh()->teacher_id);
    }

    public function test_deck_submission_persists_the_selected_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('teacherId', $teacher->id)
            ->set('audio', UploadedFile::fake()->create('assigned.wav', 100, 'audio/wav'))
            ->call('submit', $task->code)
            ->assertHasNoErrors();

        $this->assertSame($teacher->id, $student->fresh()->teacher_id);
        $this->assertSame(1, Recording::count());
    }

    public function test_student_can_skip_a_task_and_restore_it(): void
    {
        $student = User::factory()->student()->create();
        $ayah = Ayah::ordered()->firstOrFail();
        $key = $this->taskFor($ayah)->code;

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->call('skip', $key)
            ->assertSet('skipped', [$key])
            ->call('resetSkipped')
            ->assertSet('skipped', []);
    }

    public function test_ayah_type_filter_is_validated(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->call('setTypeFilter', 'unknown-type')
            ->assertSet('typeFilter', 'all')
            ->call('setTypeFilter', Recording::TYPE_WRONG)
            ->assertSet('typeFilter', Recording::TYPE_WRONG);
    }

    public function test_non_students_cannot_open_the_deck(): void
    {
        $teacher = User::factory()->teacher()->create();

        Livewire::actingAs($teacher)
            ->test(RecitationDeck::class)
            ->assertForbidden();
    }

    public function test_deck_can_be_opened_for_a_specific_surah(): void
    {
        $student = User::factory()->student()->create();

        Livewire::actingAs($student)
            ->withQueryParams(['surah' => 103])
            ->test(RecitationDeck::class)
            ->assertSet('surahFilter', '103')
            ->assertSee('وَالْعَصْرِ')
            ->assertSee('103001x');
    }

    public function test_a_task_is_reserved_for_one_student_and_hidden_from_others(): void
    {
        $first = User::factory()->student()->create();
        $second = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        Livewire::actingAs($first)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertSee($task->code);

        $this->assertSame($first->id, $task->fresh()->assigned_to);

        Livewire::actingAs($second)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertDontSee($task->code);
    }

    public function test_expired_reservation_returns_the_task_to_the_pool(): void
    {
        $first = User::factory()->student()->create();
        $second = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        Livewire::actingAs($first)->test(RecitationDeck::class);

        $this->assertSame($first->id, $task->fresh()->assigned_to);

        $this->travel(RecitationTask::RESERVATION_MINUTES + 1)->minutes();

        Livewire::actingAs($second)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->assertSee($task->code);

        $this->assertSame($second->id, $task->fresh()->assigned_to);
    }

    public function test_skipping_a_task_releases_its_reservation(): void
    {
        $student = User::factory()->student()->create();
        $tasks = RecitationTask::query()
            ->where('type', RecitationTask::TYPE_CORRECT)
            ->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', '!=', RecitationTask::REPEATABLE_SURAH_NUMBER))
            ->orderBy('id')
            ->limit(2)
            ->get();

        Livewire::actingAs($student)
            ->test(RecitationDeck::class)
            ->set('typeFilter', RecitationTask::TYPE_CORRECT)
            ->call('skip', $tasks[0]->code)
            ->assertSet('skipped', [$tasks[0]->code]);

        $this->assertNull($tasks[0]->fresh()->assigned_to);

        // The next task is handed to the same student immediately.
        $this->assertSame($student->id, $tasks[1]->fresh()->assigned_to);
    }

    public function test_recording_limit_grows_with_the_ayah_length(): void
    {
        $short = RecitationTask::recordingLimitFor('الٓمٓ');
        $long = RecitationTask::recordingLimitFor(str_repeat('بِسْمِ ', 200));
        $huge = RecitationTask::recordingLimitFor(str_repeat('ب', 5000));

        $this->assertSame(RecitationTask::MIN_RECORDING_SECONDS, $short);
        $this->assertGreaterThan($short, $long);
        $this->assertSame(RecitationTask::MAX_RECORDING_SECONDS, $huge);
    }
}

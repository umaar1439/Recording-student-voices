<?php

namespace Tests\Feature;

use App\Livewire\Admin\DatasetBrowser;
use App\Models\RecitationTask;
use App\Models\Recording;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
        Storage::fake('local');
    }

    protected function nonRepeatableTask(): RecitationTask
    {
        return RecitationTask::query()
            ->where('type', RecitationTask::TYPE_CORRECT)
            ->whereHas('ayah', fn ($ayah) => $ayah->where('surah_number', '!=', RecitationTask::REPEATABLE_SURAH_NUMBER))
            ->orderBy('id')
            ->firstOrFail();
    }

    public function test_admin_can_reset_the_platform_and_start_a_new_recitation_round(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        $recording = Recording::factory()
            ->for($student)
            ->for($task->ayah)
            ->ofType($task->type)
            ->create();

        Storage::disk('local')->put($recording->audio_path, 'fake-audio-bytes');

        $this->assertSame(RecitationTask::STATUS_PENDING, $task->fresh()->status);

        Livewire::actingAs($admin)
            ->test(DatasetBrowser::class)
            ->call('openResetModal')
            ->assertSet('showResetModal', true)
            ->set('resetConfirmation', 'تصفير')
            ->call('resetPlatform')
            ->assertHasNoErrors()
            ->assertSet('showResetModal', false);

        $this->assertSame(0, Recording::count());
        Storage::disk('local')->assertMissing($recording->audio_path);

        $task->refresh();

        $this->assertSame(RecitationTask::STATUS_AVAILABLE, $task->status);
        $this->assertNull($task->assigned_to);
        $this->assertNull($task->assigned_at);
        $this->assertNull($task->approved_at);
    }

    public function test_reset_requires_the_exact_confirmation_word(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $task = $this->nonRepeatableTask();

        Recording::factory()
            ->for($student)
            ->for($task->ayah)
            ->ofType($task->type)
            ->create();

        Livewire::actingAs($admin)
            ->test(DatasetBrowser::class)
            ->call('openResetModal')
            ->set('resetConfirmation', 'حذف')
            ->call('resetPlatform')
            ->assertHasErrors(['resetConfirmation']);

        $this->assertSame(1, Recording::count());
        $this->assertSame(RecitationTask::STATUS_PENDING, $task->fresh()->status);
    }

    public function test_non_admins_cannot_open_the_reset_screen(): void
    {
        Livewire::actingAs(User::factory()->student()->create())
            ->test(DatasetBrowser::class)
            ->assertForbidden();
    }
}

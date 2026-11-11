<?php

namespace Tests\Feature;

use App\Models\Ayah;
use App\Models\Recording;
use App\Models\User;
use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QuranSamples::seed();
    }

    public function test_guest_can_view_welcome_and_auth_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('مِحراب التلاوة');
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $this->get(route('student.recite'))->assertRedirect(route('login'));
        $this->get(route('student.read'))->assertRedirect(route('login'));
        $this->get(route('teacher.queue'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_can_view_recitation_pages_only(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.recite'))->assertOk();
        $this->actingAs($student)->get(route('student.read'))->assertOk();
        $this->actingAs($student)->get(route('student.history'))->assertOk();
        $this->actingAs($student)->get(route('teacher.queue'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_teacher_can_review_but_not_manage_admin_pages(): void
    {
        Recording::factory()->create();

        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('teacher.queue'))->assertOk();
        $this->actingAs($teacher)->get(route('student.read'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_view_every_management_page(): void
    {
        Recording::factory()->approved()->create();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('لوحة التحكم');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.ayahs.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.datasets.index'))->assertOk();
        $this->actingAs($admin)->get(route('teacher.queue'))->assertOk();
    }

    public function test_dashboard_redirects_users_to_their_role_home(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($student)->get(route('dashboard'))->assertRedirect(route('student.recite'));
        $this->actingAs($teacher)->get(route('dashboard'))->assertRedirect(route('teacher.queue'));
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_audio_route_only_streams_to_owner_or_assigned_reviewer(): void
    {
        $recording = Recording::factory()->create();
        $other = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $recording->user->update(['teacher_id' => $teacher->id]);

        $this->actingAs($recording->user)->get(route('recordings.audio', $recording))->assertNotFound();
        $this->actingAs($other)->get(route('recordings.audio', $recording))->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->get(route('recordings.audio', $recording))->assertForbidden();
        $this->actingAs($teacher)->get(route('recordings.audio', $recording))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get(route('recordings.audio', $recording))->assertNotFound();
    }

    public function test_ayah_reference_is_localised(): void
    {
        $ayah = Ayah::firstOrFail();

        $this->assertStringContainsString('سورة', $ayah->reference);
        $this->assertStringContainsString((string) $ayah->ayah_number, $ayah->reference);
    }
}

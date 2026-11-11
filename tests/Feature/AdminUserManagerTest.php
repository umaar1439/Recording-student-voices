<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_student_assigned_to_a_teacher(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'طالب جديد')
            ->set('email', 'managed-student@example.com')
            ->set('password', 'strong-password')
            ->set('role', User::ROLE_STUDENT)
            ->set('teacherId', $teacher->id)
            ->call('save')
            ->assertHasNoErrors();

        $student = User::where('email', 'managed-student@example.com')->sole();

        $this->assertSame(User::ROLE_STUDENT, $student->role);
        $this->assertSame($teacher->id, $student->teacher_id);
    }

    public function test_teacher_is_required_for_new_students_when_teachers_exist(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->teacher()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'طالب بدون معلم')
            ->set('email', 'no-teacher@example.com')
            ->set('password', 'strong-password')
            ->set('role', User::ROLE_STUDENT)
            ->call('save')
            ->assertHasErrors(['teacherId' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'no-teacher@example.com']);
    }

    public function test_admin_can_change_the_teacher_assigned_to_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        $oldTeacher = User::factory()->teacher()->create();
        $newTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['teacher_id' => $oldTeacher->id]);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openEdit', $student->id)
            ->assertSet('teacherId', $oldTeacher->id)
            ->set('teacherId', $newTeacher->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($newTeacher->id, $student->fresh()->teacher_id);
    }

    public function test_admin_cannot_assign_a_non_teacher_to_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->teacher()->create();
        $notATeacher = User::factory()->student()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'طالب جديد')
            ->set('email', 'bad-teacher@example.com')
            ->set('password', 'strong-password')
            ->set('role', User::ROLE_STUDENT)
            ->set('teacherId', $notATeacher->id)
            ->call('save')
            ->assertHasErrors(['teacherId' => 'exists']);
    }
}

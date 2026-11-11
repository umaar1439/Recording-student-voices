<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_log_in_and_is_redirected_to_the_deck(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'student@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($student);

        $this->get(route('dashboard'))->assertRedirect(route('student.recite'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_register_as_a_student(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'طالب جديد',
            'email' => 'new-student@example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ]);

        $response->assertRedirect(route('student.recite'));

        $user = User::where('email', 'new-student@example.com')->sole();

        $this->assertSame(User::ROLE_STUDENT, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_a_teacher_when_teachers_exist(): void
    {
        User::factory()->teacher()->create();

        $this->post(route('register.store'), [
            'name' => 'طالب بدون معلم',
            'email' => 'unassigned@example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ])->assertSessionHasErrors('teacher_id');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unassigned@example.com']);
    }

    public function test_registration_assigns_the_selected_teacher(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'المعلم المشرف']);

        $this->get(route('register'))->assertOk()->assertSee($teacher->name);

        $this->post(route('register.store'), [
            'name' => 'طالب جديد',
            'email' => 'assigned-student@example.com',
            'teacher_id' => $teacher->id,
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ])->assertRedirect(route('student.recite'));

        $student = User::where('email', 'assigned-student@example.com')->sole();

        $this->assertSame($teacher->id, $student->teacher_id);
        $this->assertAuthenticatedAs($student);
    }

    public function test_registration_rejects_a_user_that_is_not_a_teacher(): void
    {
        $notATeacher = User::factory()->student()->create();

        $this->post(route('register.store'), [
            'name' => 'طالب جديد',
            'email' => 'invalid-teacher@example.com',
            'teacher_id' => $notATeacher->id,
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ])->assertSessionHasErrors('teacher_id');

        $this->assertGuest();
    }

    public function test_registration_requires_a_unique_email_and_confirmed_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register.store'), [
            'name' => 'طالب',
            'email' => 'taken@example.com',
            'password' => 'strong-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->student()->create([
            'email' => 'throttle@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => 'throttle@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'throttle@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}

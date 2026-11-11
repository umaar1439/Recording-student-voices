<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_teacher_account(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->set('name', 'أستاذ جديد')
            ->set('email', '  NEW.TEACHER@Example.com ')
            ->set('password', 'password123')
            ->set('role', User::ROLE_TEACHER)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $user = User::query()->where('email', 'new.teacher@example.com')->sole();

        $this->assertSame('أستاذ جديد', $user->name);
        $this->assertSame(User::ROLE_TEACHER, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_admin_can_update_a_user_and_keep_the_existing_password(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create(['password' => 'old-password']);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openEdit', $student->id)
            ->set('name', 'اسم محدَّث')
            ->set('role', User::ROLE_TEACHER)
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        $student->refresh();

        $this->assertSame('اسم محدَّث', $student->name);
        $this->assertSame(User::ROLE_TEACHER, $student->role);
        $this->assertTrue(Hash::check('old-password', $student->password));
    }

    public function test_non_admins_cannot_manage_users(): void
    {
        $teacher = User::factory()->teacher()->create();

        Livewire::actingAs($teacher)
            ->test(UserManager::class)
            ->assertForbidden();
    }

    /**
     * Livewire morphs a single root element; when the modal was rendered as a
     * sibling root the "new user" button appeared to do nothing in the browser.
     */
    public function test_user_modal_stays_inside_the_component_root(): void
    {
        $admin = User::factory()->admin()->create();

        $html = Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreate')
            ->assertSee('إضافة المستخدم')
            ->assertSee('user-form')
            ->html();

        $html = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);

        $dom = new \DOMDocument;
        @$dom->loadHTML($html, LIBXML_NOERROR);

        $body = $dom->getElementsByTagName('body')->item(0);
        $roots = 0;

        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $roots++;
            }
        }

        $this->assertSame(1, $roots);
    }
}

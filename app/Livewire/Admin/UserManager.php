<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = 'all';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = User::ROLE_STUDENT;

    public ?int $teacherId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter'], true)) {
            $this->resetPage();
        }
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = User::ROLE_STUDENT;
        $this->teacherId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role;
        $this->teacherId = $user->teacher_id !== null ? (int) $user->teacher_id : null;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->email = mb_strtolower(trim($this->email));
        $hasTeachers = $this->teachers->isNotEmpty();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'role' => ['required', Rule::in(User::ROLES)],
            'teacherId' => [
                Rule::requiredIf($this->role === User::ROLE_STUDENT && $hasTeachers),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', User::ROLE_TEACHER),
                ),
            ],
            'password' => [
                $this->editingId ? 'nullable' : 'required',
                'string',
                Password::min(8),
            ],
        ], [
            'teacherId.required' => 'اختر المعلم المشرف للطالب.',
            'teacherId.exists' => 'المعلم المحدد غير موجود.',
        ], [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'role' => 'الدور',
            'teacherId' => 'المعلم المشرف',
            'password' => 'كلمة المرور',
        ]);

        $teacherId = $this->role === User::ROLE_STUDENT ? $this->teacherId : null;

        if ($this->editingId !== null) {
            $user = User::findOrFail($this->editingId);

            if ($user->id === auth()->id() && $this->role !== User::ROLE_ADMIN) {
                $this->addError('role', 'لا يمكنك إزالة صلاحية مدير النظام عن حسابك الحالي.');

                return;
            }

            $user->name = $this->name;
            $user->email = $this->email;
            $user->role = $this->role;
            $user->teacher_id = $teacherId;

            if ($this->password !== '') {
                $user->password = $this->password;
            }

            $user->save();

            $message = 'تم تحديث بيانات المستخدم بنجاح.';
        } else {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'role' => $this->role,
                'teacher_id' => $teacherId,
            ]);

            $message = 'تمت إضافة المستخدم بنجاح.';
        }

        $this->showModal = false;
        $this->reset('name', 'email', 'password', 'role', 'teacherId', 'editingId');
        unset($this->users, $this->counts);
        $this->resetPage();

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function updateRole(int $id, string $role): void
    {
        if (! in_array($role, User::ROLES, true)) {
            return;
        }

        $user = User::findOrFail($id);

        if ($user->id === auth()->id() && $role !== User::ROLE_ADMIN) {
            $this->dispatch('notify', message: 'لا يمكنك إزالة صلاحية مدير النظام عن حسابك الحالي.', type: 'error');

            return;
        }

        $user->update(['role' => $role]);

        unset($this->users, $this->counts);

        $this->dispatch('notify', message: 'تم تحديث دور المستخدم.', type: 'success');
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'لا يمكنك حذف حسابك الحالي.', type: 'error');

            return;
        }

        $user->recordings()->get()->each->delete();
        $user->delete();

        unset($this->users, $this->counts);
        $this->resetPage();

        $this->dispatch('notify', message: 'تم حذف المستخدم وكل تسجيلاته.', type: 'success');
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('teacher:id,name')
            ->withCount('recordings')
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($this->roleFilter !== 'all', fn ($query) => $query->where('role', $this->roleFilter))
            ->orderBy('role')
            ->latest('id')
            ->paginate(10);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'all' => User::count(),
            'admin' => User::where('role', User::ROLE_ADMIN)->count(),
            'teacher' => User::where('role', User::ROLE_TEACHER)->count(),
            'student' => User::where('role', User::ROLE_STUDENT)->count(),
        ];
    }

    /**
     * Teachers that can be assigned to a student.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function teachers(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_TEACHER)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.admin.user-manager', [
            'roleLabels' => User::roleLabels(),
        ]);
    }
}

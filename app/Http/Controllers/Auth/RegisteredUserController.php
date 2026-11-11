<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'teachers' => User::query()
                ->where('role', User::ROLE_TEACHER)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Register a new student account. Teacher / admin accounts can only be
     * created from the admin dashboard.
     */
    public function store(Request $request): RedirectResponse
    {
        $hasTeachers = User::query()->where('role', User::ROLE_TEACHER)->exists();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'teacher_id' => [
                Rule::requiredIf($hasTeachers),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', User::ROLE_TEACHER),
                ),
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'teacher_id.required' => 'اختر المعلم المشرف على تسجيلاتك.',
            'teacher_id.exists' => 'المعلم المحدد غير موجود.',
        ], [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'teacher_id' => 'المعلم المشرف',
            'password' => 'كلمة المرور',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_STUDENT,
            'teacher_id' => $validated['teacher_id'] ?? null,
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.recite')
            ->with('status', 'تم إنشاء حسابك بنجاح، بارك الله فيك!');
    }
}

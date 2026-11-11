<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send every authenticated user to the workspace that matches their role.
     */
    public function index(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return match ($user->role) {
            User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_TEACHER => redirect()->route('teacher.queue'),
            default => redirect()->route('student.recite'),
        };
    }
}

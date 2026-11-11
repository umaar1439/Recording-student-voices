<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ayah;
use App\Models\AyahReport;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $counts = [
            'users' => User::count(),
            'students' => User::where('role', User::ROLE_STUDENT)->count(),
            'teachers' => User::where('role', User::ROLE_TEACHER)->count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'ayahs' => Ayah::count(),
            'recordings' => Recording::count(),
            'pending' => Recording::pending()->count(),
            'approved' => Recording::approved()->count(),
            'rejected' => Recording::where('status', Recording::STATUS_REJECTED)->count(),
            'correct' => Recording::where('type', Recording::TYPE_CORRECT)->count(),
            'wrong' => Recording::where('type', Recording::TYPE_WRONG)->count(),
            'reports' => AyahReport::pending()->count(),
        ];

        $approvedTotal = max($counts['approved'], 1);

        $stats = [
            'approval_rate' => (int) round($counts['approved'] / max($counts['recordings'], 1) * 100),
            'rejection_rate' => (int) round($counts['rejected'] / max($counts['recordings'], 1) * 100),
            'correct_share' => (int) round($counts['correct'] / max($counts['recordings'], 1) * 100),
            'wrong_share' => (int) round($counts['wrong'] / max($counts['recordings'], 1) * 100),
        ];

        $latestRecordings = Recording::with(['user:id,name', 'ayah', 'task'])
            ->latest()
            ->limit(6)
            ->get();

        $topStudents = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->withCount([
                'recordings as approved_count' => fn ($query) => $query->approved(),
            ])
            ->orderByDesc('approved_count')
            ->limit(5)
            ->get();

        $surahCoverage = Ayah::query()
            ->selectRaw('surah_number, max(surah_name) as surah_name, count(*) as total')
            ->groupBy('surah_number')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('admin.dashboard', [
            'counts' => $counts,
            'stats' => $stats,
            'approvedTotal' => $approvedTotal,
            'latestRecordings' => $latestRecordings,
            'topStudents' => $topStudents,
            'surahCoverage' => $surahCoverage,
        ]);
    }
}

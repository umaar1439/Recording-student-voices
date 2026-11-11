<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ReviewController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $base = Recording::query();

        if ($user instanceof User && ! $user->isAdmin()) {
            $base->whereHas('user', fn (Builder $student) => $student->where('teacher_id', $user->id));
        }

        $counts = [
            'pending' => (clone $base)->pending()->count(),
            'approved' => (clone $base)->approved()->count(),
            'rejected' => (clone $base)->where('status', Recording::STATUS_REJECTED)->count(),
            'correct' => (clone $base)->pending()->where('type', Recording::TYPE_CORRECT)->count(),
            'wrong' => (clone $base)->pending()->where('type', Recording::TYPE_WRONG)->count(),
        ];

        return view('teacher.queue', ['counts' => $counts]);
    }
}

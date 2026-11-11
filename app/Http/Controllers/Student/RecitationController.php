<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RecitationController extends Controller
{
    public function index(Request $request): View
    {
        $recordings = $request->user()
            ->recordings()
            ->with(['ayah', 'task'])
            ->latest()
            ->paginate(12);

        $summary = [
            'total' => $request->user()->recordings()->count(),
            'pending' => $request->user()->recordings()->pending()->count(),
            'approved' => $request->user()->recordings()->approved()->count(),
            'rejected' => $request->user()->recordings()->where('status', Recording::STATUS_REJECTED)->count(),
        ];

        return view('student.history', [
            'recordings' => $recordings,
            'summary' => $summary,
        ]);
    }
}

<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AyahManagementController;
use App\Http\Controllers\Admin\DatasetController;
use App\Http\Controllers\Admin\DatasetExportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RecordingAudioController;
use App\Http\Controllers\Student\RecitationController;
use App\Http\Controllers\Student\RecordingUploadController;
use App\Http\Controllers\Teacher\ReviewController;
use App\Models\Ayah;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome', [
        'stats' => [
            'ayahs' => Ayah::count(),
            'recordings' => Recording::approved()->count(),
            'students' => User::where('role', User::ROLE_STUDENT)->count(),
        ],
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::get('/recordings/{recording}/audio', RecordingAudioController::class)
    ->middleware('auth')
    ->name('recordings.audio');

/*
|--------------------------------------------------------------------------
| Student workspace
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::view('/', 'student.recite')->name('recite');
        Route::view('/read', 'student.read')->name('read');
        Route::get('/history', [RecitationController::class, 'index'])->name('history');
        Route::post('/recordings/audio', RecordingUploadController::class)->name('recordings.upload');
    });

/*
|--------------------------------------------------------------------------
| Teacher workspace (admins can review as well)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:teacher,admin'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('queue');
    });

/*
|--------------------------------------------------------------------------
| Admin workspace
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/ayahs', [AyahManagementController::class, 'index'])->name('ayahs.index');
        Route::get('/datasets', [DatasetController::class, 'index'])->name('datasets.index');

        Route::get('/datasets/export', [DatasetExportController::class, 'zip'])->name('datasets.export');
        Route::get('/datasets/export/json', [DatasetExportController::class, 'json'])->name('datasets.export.json');
        Route::get('/datasets/export/csv', [DatasetExportController::class, 'csv'])->name('datasets.export.csv');
    });

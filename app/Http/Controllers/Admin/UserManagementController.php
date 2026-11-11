<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.users');
    }
}

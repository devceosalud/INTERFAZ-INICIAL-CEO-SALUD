<?php

namespace App\Http\Controllers\admin\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    //
    public function index()
    {
        $role = auth()->user()->getRoleNames();
        return view('admin.dashboard.index', [
            'role' => $role
        ]);
    }
}

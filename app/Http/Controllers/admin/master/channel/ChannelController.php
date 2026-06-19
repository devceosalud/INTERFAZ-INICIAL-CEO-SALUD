<?php

namespace App\Http\Controllers\admin\master\channel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }
}

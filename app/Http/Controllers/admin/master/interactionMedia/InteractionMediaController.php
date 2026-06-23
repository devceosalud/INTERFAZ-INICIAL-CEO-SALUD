<?php

namespace App\Http\Controllers\admin\master\interactionMedia;

use App\Http\Controllers\Controller;
use App\Models\InteractionMedium;
use Illuminate\Http\Request;

class InteractionMediaController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        $interactionMedia = InteractionMedium::where('estado', 'ACTIVO')->get();
        return view('admin.master.interaction-media.index', [
            'interactionMedia' => $interactionMedia
        ]);
    }
}

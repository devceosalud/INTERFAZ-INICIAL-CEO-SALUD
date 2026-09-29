<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class MvpAccessController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}

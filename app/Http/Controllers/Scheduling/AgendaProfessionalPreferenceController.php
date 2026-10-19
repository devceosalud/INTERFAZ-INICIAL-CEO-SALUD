<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Services\Scheduling\AgendaProfessionalPreferences;
use Illuminate\Http\Request;
final class AgendaProfessionalPreferenceController extends Controller
{
    public function update(Request $r, AgendaProfessionalPreferences $preferences)
    {
        $d=$r->validate(['doctor_id'=>'required|integer','action'=>'required|in:select,favorite,unfavorite']);
        return response()->json($preferences->change($r->user(),(int)$d['doctor_id'],$d['action']))->header('Cache-Control','private, no-store');
    }
}

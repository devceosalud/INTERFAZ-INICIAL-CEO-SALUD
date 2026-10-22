<?php
namespace App\Http\Controllers\admin\master\user;

use App\Http\Controllers\Controller;
use App\Models\{Doctor,User,UserAccountEvent};
use App\Services\UserDoctorLink;
use Illuminate\Http\Request;

final class UserDoctorLinkController extends Controller
{
    public function __construct(){ $this->middleware(['auth','role:ADMINISTRADOR']); }
    public function edit(int $user)
    {
        User::findOrFail($user);
        return redirect()->route('admin.user.edit',$user);
    }

    public function update(int $user,Request $r,UserDoctorLink $service)
    {
        abort_if(array_diff(array_keys($r->all()),['doctor_id','expected_doctor_id','expected_version','request_key','reason','confirmed','identity_confirmed','_token']),422);
        $data=$r->validate(['doctor_id'=>'present|nullable|integer|exists:doctors,id','expected_doctor_id'=>'present|nullable|integer',
            'expected_version'=>'required|integer|min:0','request_key'=>'required|uuid','reason'=>'required|string|min:3|max:500',
            'confirmed'=>'required|accepted','identity_confirmed'=>'required|accepted']);
        $target=$service->change($user,$r->user(),$data);
        return response()->json(['message'=>'VinculaciÃ³n guardada y sesiones de esa cuenta invalidadas.',
            'doctor_id'=>$target->doctor_id,'auth_version'=>$target->auth_version])->header('Cache-Control','private, no-store');
    }
}

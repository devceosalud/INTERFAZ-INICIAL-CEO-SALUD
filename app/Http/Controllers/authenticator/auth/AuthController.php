<?php

namespace App\Http\Controllers\authenticator\auth;

use App\Http\Controllers\Controller;
use App\Support\Scheduling\MedicalLanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    //
    public function index()
    {
        return view('authenticator.index');
    }

    //LOGIN DE PRUEBA , AJUSTAR CON ATEEMP DESPUES PARA EL AUTHENTICADO
    public function store(Request $request)
    {
        //dd($request->all());

        //validaciones
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password') + ['is_active'=>true], $request->boolean('remember'))) {
            return back()->with('mensaje', 'Tus credenciales estan incorrectas');
        }

        $request->session()->regenerate();
        $request->session()->put('ceosalud_auth_version',(int)Auth::user()->auth_version);

        // Preserve the established Julio redirect and add verified medical-only users.
        // Administrative and operational accounts keep their existing landing.
        $user=Auth::user();
        if((int)$user->id===2 && $user->hasAllRoles(['ADMINISTRADOR','MEDICO'])
            && config('scheduling.enabled')
            && $user->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
            && $user->can(\App\Support\Scheduling\SchedulingCapability::VIEW) || MedicalLanding::admissionFor($user)){
            return redirect()->route('scheduling.mvp.admission');
        }

        return redirect()->route('admin.dashboard.index');
    }


    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

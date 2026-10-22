<?php

namespace App\Http\Controllers\authenticator\auth;

use App\Http\Controllers\Controller;
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

        // Navigation preference for a verified existing account; never grants access.
        if (\App\Support\Authorization\AdmissionHomeRedirect::applies(Auth::user())) {
            return redirect()->route('scheduling.mvp.admission');
        }

        if(Auth::user()->hasRole('MEDICO') && !Auth::user()->hasAnyRole(\App\Support\Scheduling\MvpOperationalAccess::LEGACY_ROLES)){
            return redirect()->route('scheduling.medical.index');
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

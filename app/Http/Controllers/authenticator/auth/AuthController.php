<?php

namespace App\Http\Controllers\authenticator\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\TryCatch;

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
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->with('mensaje', 'El correo electrónico no está registrado.');
        }

        return redirect()->route('admin.dashboard.index');
    }
    

    public function logout()
    {
         return redirect()->route('login');
    }
}

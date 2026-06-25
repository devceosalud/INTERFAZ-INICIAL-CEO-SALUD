<?php

namespace App\Http\Controllers\admin\master\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
        //$this->middleware('can:lista usuario');
    }

    public function index()
    {
        $users = User::all();

        return view('admin.user.index', [
            'users' => $users
        ]);
    }

    //PARA CREAR NUEVO USUARIO
    public function store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'nombre_usuario'     => 'required|string',
            'email'              => 'required|email:htmlv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //creamos al usuario
        $user = User::create([
            'name' => $request->nombre_usuario,
            'email' => $request->email,
            'password' => Hash::make($request->password) //encriptando
        ]);

        if ($user) {
            return response()->json([
                'code' => 1,
                'msg' => 'Usuario creado correctamente',
            ]);
        }

        return response()->json([
            'code' => 0,
            'msg' => 'No se pudo guardar al usuario, comunicar a sistemas'
        ]);
    }

    //PARA ACTUALIZAR DATOS DEL USUARIO
    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_usuario_edit'     => 'required|string',
            'email_edit'    => 'required|string',
            'password'    => 'nullable|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }
        
        $user = User::findOrFail($request->usuario_id_edit);
        $user->name = $request->nombre_usuario_edit;
        $user->email = $request->email_edit;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($user->save()) {
            return response()->json([
                'code' => 1,
                'msg' => 'Usuario actualizado correctamente',
            ]);
        }

        return response()->json([
            'code' => 0,
            'msg' => 'No se pudo guardar al usuario, comunicar a sistemas'
        ]);
    }

    //FORMULARIO PARA ASIGNAR ROLE AL USUARIO 
    public function edit(User $user)
    {
        $roles = Role::all();
        return view('admin.user.edit', [
            'user' => $user,
            'roles' => $roles
        ]);
    }

    //ASIGNAR ROLE AL USUARIO 
    public function update(User $user, Request $request)
    {
        $user->roles()->sync([$request->role]); // Usamos sync() con un array de un solo elemento
        return redirect()->route('admin.user.index')->with('exito', 'Rol asignado correctamente al usuario');
    }
}

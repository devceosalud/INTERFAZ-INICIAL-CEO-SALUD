<?php

namespace App\Http\Controllers\admin\master\role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
        //$this->middleware('can:lista roles');
    }

    //LISTA DE ROLES
    public function index()
    {
        $roles = Role::all();
        return view('admin.role.index', [
            'roles' => $roles
        ]);
    }

    //PARA CREAR PERMISOS
    public function create()
    {
        $permissions = Permission::all();
        return view('admin.role.create', [
            'permissions' => $permissions
        ]);
    }


    //PARA GUARDAR LOS PERMISOS
    public function store(Request $request)
    {
        //validaciones
        $this->validate($request, [
            'name' => 'required|min:3|max:30',
        ]);

        //CREANDO EL NUEVO PERMISOS
        $permisos = Permission::create([
            'name' => $request->name,
        ]);

        if ($permisos) {
            return redirect()->route('admin.permissions.create')->with('exito', 'Nuevo permiso guardado correctamente');
        } else {
            return redirect()->route('admin.permissions.create')->with('exito', 'Permiso no creado');
        }
    }

    //FORMULARIO PARA ACTUALIZAR LOS PERMISOS DE CADA ROLE
    public function edit(Role $role, Request $request)
    {
        $permissions = Permission::all();
        return view('admin.role.edit', [
            'permissions' => $permissions,
            'role' => $role
        ]);
    }

    //ACTUALIZAR LOS PERMISOS DE CADA ROLE 
    public function update(Role $role, Request $request)
    {
        //validaciones
        $this->validate($request, [
            'name' => 'required|min:3|max:30',
            'permissions' => 'required'
        ]);
        $role->update(['name' => $request->name]);

        //metodo sync sincroniza los roles que se mandan
        $role->permissions()->sync($request->permissions);

        return redirect()->route('admin.roles.index')->with('exito', 'Permisos asignados correctamente');;
    }

    //ELIMINAR UN ROLE
    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('admin.roles.index')->with('exito', 'Dato eliminado correctamente');
    }
}

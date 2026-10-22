<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles { hasPermissionTo as private inheritedHasPermissionTo; }
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dni',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'dni',
        'auth_version',
        'is_active',
        'doctor_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'=>'boolean', 'auth_version'=>'integer', 'doctor_id'=>'integer',
    ];


    /**
     * Obtiene todas las citas registradas por el usuario.
     */
    /** Identity assigned only by the explicit administrator linkage operation. */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Obtiene las citas de las que el usuario es responsable actual de gestión.
     */
    public function responsibleAppointments()
    {
        return $this->hasMany(Appointment::class, 'responsible_user_id');
    }

    protected $attributes = ['is_active'=>true,'auth_version'=>0];

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return $this->is_active && $this->inheritedHasPermissionTo($permission,$guardName);
    }

    //METODO PARA REDIRECCION DE RUTAS POR ROLES
    public function redirectToDashboard()
    {
        $roles = $this->getRoleNames();

        if ($roles->contains('ADMINISTRADOR')) {
            return redirect()->route('admin.dashboard.index');
        } else if ($roles->contains('ADMISION')) {
            return redirect()->route('admissionit.patient.index');
        }
    }

    //METODO PARA SACAR EL ROL DEL USUARIO
    public function roleUser()
    {
        $roles = $this->getRoleNames();

        if ($roles->contains('ADMISION')) {
            return "ADMISION";
        } elseif ($roles->contains('ADMINISTRADOR')) {
            return "ADMINISTRADOR";
        } elseif ($roles->contains('RECEPCION')) {
            return "RECEPCION";
        } elseif ($roles->contains('CAJA')) {
            return "CAJA";
        } elseif ($roles->contains('FACTURACION')) {
            return "FACTURACION";
        }
    }
}

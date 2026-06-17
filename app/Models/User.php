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
    use HasRoles;
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    /**
     * Obtiene todas las citas registradas por el usuario.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
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
}

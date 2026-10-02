<?php

namespace App\Services\Reniec;

/**
 * Identity fields shared by every RENIEC provider.
 *
 * Missing values stay null so the form can keep a manual entry.
 */
class ReniecPersonData
{
    public string $nombres;

    public string $apellido_paterno;

    public string $apellido_materno;

    public string $numero_documento;

    public ?string $fecha_nacimiento;

    public ?string $sexo;

    public ?string $estado_civil;

    public ?string $direccion;

    public function __construct(
        string $nombres,
        string $apellido_paterno,
        string $apellido_materno,
        string $numero_documento,
        ?string $fecha_nacimiento,
        ?string $sexo,
        ?string $estado_civil,
        ?string $direccion
    ) {
        $this->nombres = $nombres;
        $this->apellido_paterno = $apellido_paterno;
        $this->apellido_materno = $apellido_materno;
        $this->numero_documento = $numero_documento;
        $this->fecha_nacimiento = $fecha_nacimiento;
        $this->sexo = $sexo;
        $this->estado_civil = $estado_civil;
        $this->direccion = $direccion;
    }

    /**
     * Shape already consumed by Agenda, Pacientes and the legacy patient API.
     *
     * @return array<string, mixed>
     */
    public function toLookupArray(): array
    {
        return [
            'nombre' => $this->nombres,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'genero' => $this->sexo,
            'estado_civil' => $this->estado_civil,
            'direccion' => $this->direccion,
            'numero_identidad' => $this->numero_documento,
            'ocupacion' => null,
            'grado_instruccion' => null,
            'telefono' => null,
            'email' => null,
            'channel_id' => null,
            'interaction_medium_id' => null,
            'tipo_identificacion' => 'DNI',
        ];
    }
}

<?php

namespace App\Http\Controllers\admissionist\patient;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Responsible;
use App\Models\Specialty;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    //
    public function index()
    {
        //traer todos los pacientes del mes actual
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();
        $patients = Patient::whereBetween('fecha_registro', [$start, $end])
            ->where('estado','ACTIVO')
            ->orderBy('id', 'ASC')
            ->get();
        $channels  = Channel::where('estado', 'ACTIVO')->get();
        $interaction_media  = InteractionMedium::where('estado', 'ACTIVO')->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado', 'ACTIVO')->get();

        return view('admissionist.patient.index', [
            'patients' => $patients,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'specialties' => $specialties,
            'additional_rates' => $additional_rates
        ]);
    }

    public function store(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'nombre_paciente'     => 'required|string',
            'apellido_paterno'    => 'required|string',
            'apellido_materno'    => 'required|string',
            'genero_paciente'     => 'required|string',
            'tipo_identificacion' => 'required|string',
            'numero_identidad'    => 'required|string',

            'telefono'            => 'nullable|string',
            'channel_id'          => 'required|integer',
            'interaction_medium_id' => 'required|integer',
            'fecha_nacimiento'    => 'required|date',
            'ocupacion'           => 'nullable|string',
            'grado_instruccion'   => 'nullable|string',
            'direccion'           => 'nullable|string',
            'email'               => 'required|email:htmlv',
            'estado_civil'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        // OBTENER LA ULTIMA HOSTORIA CLINICA
        $ultimoPaciente = Patient::latest('id')->first();

        //CREAMOS EL NUMERO DE LA HISTORIA CLINICA
        $historiaNueva = $ultimoPaciente
            ? ((int) $ultimoPaciente->historia_clinica + 1)
            : 1;

        // BUSCAMOS AL PACIENTE POR IDENTIDAD
        $patient = Patient::where(
            'numero_identidad',
            $request->numero_identidad
        )->first();

        // SI NO EXISTE CREAMOS AL PACIENTE
        if (!$patient) {
            $patient = new Patient();
            $patient->historia_clinica = $historiaNueva;
            $patient->fecha_registro = date('Y-m-d');
        }

        // DATOS GENERALES
        $patient->user_id = auth()->user()->id;
        $patient->nombre = $request->nombre_paciente;
        $patient->apellido_paterno = $request->apellido_paterno;
        $patient->apellido_materno = $request->apellido_materno;
        $patient->genero = $request->genero_paciente;
        $patient->tipo_identificacion = $request->tipo_identificacion;
        $patient->numero_identidad = $request->numero_identidad;
        $patient->telefono = $request->telefono;
        $patient->channel_id = $request->channel_id;
        $patient->interaction_medium_id = $request->interaction_medium_id;
        $patient->fecha_nacimiento = $request->fecha_nacimiento;
        $patient->ocupacion = $request->ocupacion;
        $patient->grado_instruccion = $request->grado_instruccion;
        $patient->direccion = $request->direccion;
        $patient->email = $request->email;
        $patient->familiar_contacto = $request->familiar_contacto;
        $patient->estado_civil = $request->estado_civil;

        $exito = $patient->save();

        if ($exito) {
            // SI VIENE ACOMPAÑADO
            if ($request->responsable_id == 'responsable') {
                Responsible::updateOrCreate(
                    [
                        'patient_id' => $patient->id
                    ],
                    [
                        'tipo_identificacion' => $request->tipo_identificacion_responsable,
                        'numero_identidad'    => $request->numero_identidad_responsable,
                        'nombres'             => $request->nombre_responsable,
                        'telefono'            => $request->telefono_responsable,
                        'parentezco'          => $request->responsable_tipo,
                    ]
                );
            }

            return response()->json([
                'code' => 1,
                'msg' => $patient->wasRecentlyCreated
                    ? 'Paciente registrado correctamente'
                    : 'Paciente actualizado correctamente',
                'patient' => $patient
            ]);
        }

        return response()->json([
            'code' => 0,
            'msg' => 'No se pudo guardar el paciente'
        ]);
    }


    public function update(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'nombre_paciente_edit'     => 'required|string',
            'apellido_paterno_edit'    => 'required|string',
            'apellido_materno_edit'    => 'required|string',
            'genero_paciente_edit'     => 'required|string',
            'tipo_identificacion_edit' => 'required|string',
            'numero_identidad_edit'    => 'required|string',

            'telefono_edit'            => 'nullable|string',
            'channel_edit'          => 'required|integer',
            'interaction_medium_edit' => 'required|integer',
            'fecha_nacimiento_edit'    => 'nullable|date',
            'ocupacion_edit'           => 'nullable|string',
            'grado_instruccion_edit'   => 'nullable|string',
            'direccion_edit'           => 'nullable|string',
            'email_edit'               => 'nullable|email',
            'estado_civil_edit'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $patient = Patient::find($request->id);

        $patient->update([
            'nombre' => $request->nombre_paciente_edit,
            'apellido_paterno' => $request->apellido_paterno_edit,
            'apellido_materno' => $request->apellido_materno_edit,
            'genero' => $request->genero_paciente_edit,
            'tipo_identificacion' => $request->tipo_identificacion_edit,
            'numero_identidad' => $request->numero_identidad_edit,
            'fecha_nacimiento' => $request->fecha_nacimiento_edit,
            'ocupacion' => $request->ocupacion_edit,
            'grado_instruccion' => $request->grado_instruccion_edit,
            'email' => $request->email_edit,
            'estado_civil' => $request->estado_civil_edit,
            'telefono' => $request->telefono_edit,
            'channel_id' => $request->channel_edit,
            'interaction_medium_id' => $request->interaction_medium_edit,
            'direccion' => $request->direccion_edit,
            'familiar_contacto' => $request->familiar_contacto_edit,
        ]);

        return response()->json([
            'code' => 1,
            'msg' => 'Paciente actualizado correctamente'
        ]);
    }

    //PARA DESACTIVAR 
    public function delete(Request $request)
    {
        $patient = Patient::find($request->id);
        $exito = $patient->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Paciente inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Paciente no se inactivo"
            ]);
        }
    }
}

<x-mail::message>
# Datos de Tu Cita

Hola {{ $appointment->patient->nombre }}. Tu cita fue registrada correctamente

**N° de Cita:** {{ $appointment->numero_cita }}
**Fecha:** {{ $appointment->fecha_cita }}
**Hora:** {{ $appointment->hora_cita}}

# Especialidad

**Especialidad:** {{ $appointment->doctor->specialty->nombre}}
**Servicio:** {{ $appointment->service->nombre }}
<br>

# Especialista
**Doctor:** {{ $appointment->doctor->nombre}}

Gracias por elegirnos, lo esperamos pronto,<br>
{{ config('app.name') }}
</x-mail::message>

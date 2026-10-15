<?php

namespace App\Support\Authorization;

/**
 * Human-readable permission descriptions for the administrator role editor.
 * Never rename Spatie permission identifiers: they remain the persisted keys.
 */
final class PermissionDisplay
{
    private const LABELS = [
        'appointment.mvp.access' => ['Ingresar a la Agenda', 'Permite abrir el módulo de Agenda, junto con el permiso de consulta.'],
        'appointment.view' => ['Ver citas de Agenda', 'Consulta las citas autorizadas según las reglas de visibilidad.'],
        'appointment.create' => ['Registrar citas y reservas', 'Permite crear reservas privadas y solicitar confirmación de citas.'],
        'appointment.update' => ['Editar datos de citas', 'Modifica información de citas según las operaciones habilitadas.'],
        'appointment.withdraw' => ['Registrar retiro de una cita', 'Marca el retiro de una cita según las reglas del sistema.'],
        'appointment.cancel' => ['Cancelar citas', 'Permite cancelar citas autorizadas.'],
        'appointment.no_show' => ['Marcar inasistencia', 'Registra que un paciente no acudió.'],
        'appointment.reschedule' => ['Reprogramar citas', 'Permite mover una cita a otra fecha u hora disponible.'],
        'appointment.responsible.assign' => ['Asignar responsable comercial', 'Permite asignar un comercial como responsable de la cita.'],
        'appointment.hold.create' => ['Crear retención temporal de horario', 'Aparta temporalmente un horario sin confirmar una cita.'],
        'appointment.hold.extend' => ['Ampliar retención temporal de horario', 'Extiende una retención temporal; no significa cita extendida de dos intervalos.'],
        'appointment.payment.submit' => ['Registrar adelantos y pagos', 'Permite ingresar cobros cuando se cumplan los requisitos de Caja.'],
        'appointment.payment.verify' => ['Verificar pagos', 'Permite revisar y verificar pagos según las reglas financieras.'],
        'appointment.down_payment.override' => ['Autorizar excepción al adelanto mínimo', 'Permite autorizar una excepción al adelanto requerido para una cita.'],
        'appointment.zero_cost.request' => ['Solicitar cita sin costo', 'Solicita una gratuidad; no permite aprobarla por sí solo.'],
        'appointment.zero_cost.approve' => ['Aprobar cita sin costo', 'Autoriza una gratuidad conforme a las reglas del sistema.'],
        'appointment.additional.create' => ['Crear citas adicionales', 'Permite crear citas adicionales fuera de la capacidad ordinaria, según validaciones.'],
        'appointment.overbook' => ['Autorizar sobrecupo', 'Permite operaciones de sobrecupo sujetas a controles del sistema.'],
        'appointment.audit.view' => ['Consultar auditoría de citas', 'Da acceso a registros autorizados de auditoría.'],
        'appointment.reevaluation.indication.record' => ['Registrar indicación de reevaluación', 'Registra una indicación médica respaldada para una futura reevaluación.'],
        'appointment.documents.view' => ['Consultar documentos privados de citas', 'Permite ver documentos adjuntos según las restricciones clínicas.'],
        'appointment.documents.manage' => ['Gestionar documentos privados de citas', 'Permite gestionar documentos de citas según la autorización.'],
        'doctor.schedule.manage' => ['Gestionar horarios médicos', 'Permite crear, modificar o desactivar horarios médicos autorizados.'],
        'GESTION_CAJA' => ['Gestionar Caja', 'Acceso a funciones de administración de Caja existentes.'],
        'GESTION_CITAS' => ['Gestionar citas (módulo anterior)', 'Permiso heredado del módulo de citas.'],
        'GESTION_PACIENTE' => ['Gestionar pacientes', 'Permiso del módulo de pacientes.'],
        'GESTION_RECEPCION' => ['Gestionar recepción', 'Permiso del módulo de recepción.'],
        'GESTION_USUARIOS' => ['Gestionar usuarios', 'Permiso del módulo de usuarios.'],
        'GESTIONS_ROLES' => ['Gestionar roles y permisos', 'Permiso heredado del módulo de roles.'],
    ];

    public static function label(string $name): string
    {
        if (isset(self::LABELS[$name])) {
            return self::LABELS[$name][0];
        }

        // Unknown future keys still receive readable text without silently
        // implying business authorization that has not been reviewed.
        return ucfirst(strtolower(preg_replace('/[._-]+/u', ' ', $name)));
    }

    public static function description(string $name): string
    {
        return self::LABELS[$name][1]
            ?? 'Permiso técnico pendiente de descripción funcional. Consulta con TI antes de asignarlo.';
    }
}

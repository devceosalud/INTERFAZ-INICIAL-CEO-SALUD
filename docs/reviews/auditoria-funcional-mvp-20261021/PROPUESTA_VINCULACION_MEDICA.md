# Propuesta de vinculación usuario–médico — pendiente de aprobación

Esta propuesta NO está implementada. No se añade migración de identidad, vínculo, rol ni acceso médico en esta revisión. La respuesta del responsable confirma que no existe una asociación aprobada y autoriza diseñarla antes de implementarla definitivamente.

## Evidencia comprobada

- `config/auth.php`: proveedor Eloquent `App\Models\User`; Doctor no autentica.
- `app/Models/User.php`: roles Spatie y relaciones con citas creadas/responsables; sin relación con Doctor. `appointments.user_id` es creador, nunca médico autenticado.
- `app/Models/Doctor.php`: catálogo profesional, especialidad, horarios y servicios; sin User.
- Migraciones de users/doctors y sus ampliaciones: sin asociación de identidad. `agenda_professional_preferences` contiene user_id/doctor_id para favoritos y selección y es editable por el propio usuario; no puede conferir autoridad clínica.
- `ReevaluationIndicationService`: acreditación administrativa de una orden escrita de atención anterior; no acredita la identidad autenticada del profesional que ordena el retorno de la misma cita.
- `AgendaHeatmapController` incluye MEDICO en una clave de caché. Esa cadena no configura autenticación ni prueba la existencia/atribución de ese rol. No se consultaron cuentas o datos de producción.
- `UserController` exige auth + rol ADMINISTRADOR para crear/editar identidades, asignar un rol existente y cambiar vigencia. `UserAccountAccess::lockAdministrator` revalida bajo bloqueo, preserva el último administrador y registra `user_account_events`. `rotateAccess` invalida sesiones/tokens mediante auth_version.
- `RoleController::update` usa el editor Spatie existente, revisión contra cambios concurrentes y `syncPermissions`; no se necesita un segundo administrador de roles.

## Elección recomendada y alternativa

Recomiendo **`users.doctor_id` nullable, único y con FK restrictiva** hacia doctors, si se aprueba que una cuenta médica personal identifica a un solo médico y cada médico tendrá una sola cuenta clínica. Es la representación mínima de una identidad, no de delegaciones o de favoritos. Los usuarios administrativos existentes conservan NULL. No se asigna por nombre, DNI, correo, CMP, creador de cita ni coincidencia de IDs.

Una tabla de asociación sería preferible si CEO Salud necesita varias cuentas clínicas por médico, una cuenta para varios profesionales o delegaciones con vigencia/alcance. Esas posibilidades requieren reglas y auditoría adicionales y no están aprobadas. No habilitaría una relación muchos-a-muchos para resolver una necesidad que no se ha confirmado. La cardinalidad y la política sobre cuentas compartidas/combinadas deben aprobarse antes de escribir la migración.

## Administración propuesta

En el mantenimiento actual de Usuarios, añadir una sección independiente **Vinculación médica**; no mezclarla con edición de nombre, DNI o asignación de roles. Solo el administrador activo revalidado por `UserAccountAccess::lockAdministrator` podría:

1. Elegir explícitamente una cuenta personal activa y un profesional activo.
2. Revisar ambos IDs/identidades, vínculo actual, motivo y efecto sobre el acceso.
3. Confirmar con UUID y versión esperada; bajo transacción bloquear cuenta y médico y verificar unicidad.
4. Registrar vínculo anterior/nuevo, administrador, motivo, fecha y UUID en `user_account_events` existente, sin copiar credenciales.
5. Invalidar sesiones de la cuenta mediante `rotateAccess` al vincular, desvincular o sustituir. No conceder roles/capacidades por crear el vínculo. Los demás permisos se siguen administrando desde Roles.

No permitir autoservicio ni que doctor_id sea asignable desde el formulario ordinario o los endpoints actuales de identidad. La desvinculación conserva citas, doctor_id de las citas, eventos y autoría anteriores. El médico se desactiva en su catálogo; no se borra una identidad histórica referenciada.

## Autorización médica propuesta

Una acción clínica deberá exigir simultáneamente autenticación vigente, usuario y profesional activos, capacidades efectivas actuales de acceso a Agenda y actualización, vínculo aprobado y `user.doctor_id === appointment.doctor_id`, además de visibilidad y estado/transición válidos. Un permiso genérico o el rol ADMINISTRADOR no sustituye el vínculo. La cuenta administrativa no se vincula automáticamente. Cuentas con funciones mixtas requieren validación explícita de que su titular es el profesional; conviene aprobar cuentas personales separadas de los accesos administrativos.

Centralizar ese predicado en una guardia pequeña reutilizada por `AdmissionCareService`, `LegacyAppointmentMutation` y el futuro escritor de indicación, con flags efectivos en detalle/frontend. Revalidarlo dentro de la transacción y después de los bloqueos. Definir orden consistente cuenta → médico → cita y conservar UUID/revisión existentes; una revocación o cambio de médico concurrente debe ganar o provocar conflicto, nunca atribuir la acción al profesional anterior.

- Iniciar: desde paciente presente/llamado según reglas existentes, registrar inicio real una sola vez y actor verificado; no cambiar médico/paciente ni dinero.
- Finalizar: transición diferenciada a ATENDIDO con hora real/actor, sin salida física automática; conservar las reglas de cierre existentes y tratar la finalización como decisión separada de la indicación de retorno.
- Indicar/retirar reevaluación: append-only `appointment_events`, versión, doctor_id obtenido del vínculo bloqueado, actor, UUID/operation_id y referencia al evento anterior; sin nuevo estado clínico, cita, pago o salida.
- Pendiente es una proyección secundaria de esa indicación mientras siga activa y no esté listo/finalizado/inactivo. Listo sigue dependiendo del servicio manual/automático existente, nunca de una lista vacía.
- El médico no adquiere por el vínculo la potestad de seleccionar procedimientos: actualmente la selección corresponde a ADMINISTRADOR. Si debe seleccionarlos, hace falta aprobar y probar esa ampliación específica. Admisión sigue verificando realización y envío parcial con motivo.

Hasta aprobar e implementar esta guardia, las transiciones médicas se rechazan con403 incluso para administradores; no hay botón de indicación médica habilitado. Las etiquetas/filtros actuales solo pueden leer el contrato de eventos documentado, que en QA se carga explícitamente como fixture sintético. Eso NO es una prueba de acción médica autenticada ni habilita su uso operativo completo.

## Migración y pruebas propuestas

La futura migración sería aditiva: columna nullable users.doctor_id, índice único y FK restrictOnDelete. Sin backfill ni concesiones; múltiples NULL válidos en SQLite/MariaDB. La auditoría reutiliza user_account_events. Antes de migrar, verificar esquema/versiones reales y respaldo consistente; probar up y despliegue repetido en bases aisladas, NULL históricos, unicidad y rechazo de FK inválida. No está incluida ni ejecutada en esta rama.

Probar en SQLite y MariaDB: cuenta sin vínculo, otro médico, cuenta/profesional inactivo, permiso revocado, vínculo revocado/reasignado, personal administrativo con permiso genérico, accesos por rutas heredadas/directas y falta de visibilidad; solo titular habilitado inicia/finaliza/indica. Carreras reales entre vinculación/revocación y atención, mismo UUID y propuestas distintas. Navegador escritorio/móvil: los botones reflejan autorización del backend. Verificar que no cambian pagos, ocupación, procedimientos ni salida física.

Rollback: revocar acceso y restaurar código compatible conservando columna/vínculos/auditoría; no eliminar automáticamente datos de asociación. Un down destructivo debería negarse si hay vínculos o eventos dependientes. El mantenimiento de usuarios existente y la protección del último administrador deben seguir pasando sus pruebas.

## Decisiones que faltan

Aprobar cardinalidad; confirmar cuentas personales y tratamiento de funciones mixtas; definir si basta la capacidad existente de actualización junto al vínculo o se requiere una capacidad clínica separada aprobada; decidir selección de procedimientos por el médico, continuación tras listo y cómo iniciar otro ciclo de indicación cuando ya existe READY previo (sin borrar pruebas ni retirar una autorización silenciosamente). Esas decisiones no se sustituyen con permisos genéricos ni con asignaciones automáticas.

# Diagnóstico previo y alcance autorizado

Base verificada: a8bd76d8e10090decfde56c7402be585da723fcf (Draft PR57); main remoto 1f840e90912d2ce8c55ce7502a22a1d582492bc3. Rama local aislada, sin publicación.

Antes: User autentica; Doctor es catálogo. Sin relación entre ambos. No se consultan usuarios ni pacientes productivos. MEDICO aparece en una clave de caché, pero no existe una definición controlada de ese rol en migraciones/seeders. No permite inferir sus asignaciones productivas. Las cuentas existentes se reutilizarán únicamente por selección y verificación administrativa explícita, sin coincidencias automáticas.

Usuarios se crean y editan desde UserController, con listas explícitas de atributos. UserAccountAccess serializa cambios administrativos, conserva el último administrador, audita en user_account_events e invalida sesiones mediante auth_version. Se reutilizan esas estructuras; no se necesita otra tabla de identidades ni usuarios duplicados.

AdmissionCareService permite llegada/llamado/salida administrativa y bloquea todas las acciones médicas. LegacyAppointmentMutation también bloquea cambios médicos. Se conservarán estas restricciones administrativas; el médico tendrá un acceso específico y limitado, sin appointment.update ni permisos económicos.

ManualReevaluationService mantiene una marca administrativa de retorno en ATENDIDO, incluidos permisos heredados de COMERCIAL. OperationalProcedureService permite selección ADMINISTRADOR, realización ADMINISTRADOR/ADMISION y envío parcial autorizado con motivo. READY requiere procedimientos no vacíos realizados o decisión manual válida. AppointmentReevaluationService ya proyecta indicación médica pendiente como distintivo secundario sin cambiar estado/color.

Ampliación mínima: users.doctor_id nullable/UNIQUE/FK; administración auditada del vínculo; seis capacidades clínicas Spatie; vista médica apoyada en el CSS/presentación existentes y sin proyección económica; escritor médico sobre appointment_operations/events; selección médica de sus propios procedimientos reutilizando OperationalProcedureService. La marca manual de Admisión se amplía solo para una indicación médica activa en EN_ATENCION. El retorno administrativo ATENDIDO conserva sus reglas.

No se modifican doctors.id, appointments.doctor_id, contratos de reserva, precios, cobros, capacidad ni historial de reprogramaciones. Los puntos4/5 siguen pendientes. Se exige regresión antes/después de cada módulo y no basta con pruebas nuevas.

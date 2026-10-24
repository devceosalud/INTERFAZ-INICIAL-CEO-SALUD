# Última corrección: Enviar a seguimiento

Base verificada: PR72 Draft `162de591b81ffe4c838f32de0d16bbb4386b421b`; main `a4e2a9b0f9e517c55be10232321391b1dc970e73`. Mismo branch, sin cambios de diseño, CSS, permisos, automatización ni esquema.

## Causa y regla nueva

El contexto anterior ofrecía el botón con cero solicitudes, permitía un seguimiento general y mostraba también procedimientos que ya tenían seguimiento. El handler abría un segundo popup con selección/motivo/fecha. Ahora el contexto es null salvo que Quiroz verificado tenga acceso a una cita con solicitudes persistidas, activas y todavía sin seguimiento.

La consulta parte de `appointment_requested_procedures`, `appointment_id` actual y `selected=true`, igual que la lista de procedimientos del detalle. Un NOT EXISTS por la FK real de solicitud excluye cualquier seguimiento ya registrado, incluidos los automáticos o finalizados. No se infiere de textos, contador visual ni otra cita. Los borradores del popup no están persistidos y no habilitan el envío.

| Caso | Botón / endpoint |
|---|---|
| Sin solicitudes activas | Oculto; POST válido de una operación nueva devuelve422 sin escrituras |
| Radiografía y Curación confirmadas, sin seguimiento | Visible; un clic envía ambas y abre el módulo |
| Solo Cirugía/RM con seguimiento automático | Oculto; una petición nueva devuelve409, sin tocar los automáticos |
| Ya enviados | Oculto; no se repiten registros ni eventos |
| Otro procedimiento agregado posteriormente | Reaparece; el servidor envía solo el nuevo |
| Actor ajeno o no autorizado |403 y transacción sin escrituras |

## Contrato e integridad

Se reutiliza el POST existente. El cliente solo envía `request_key`, `expected_patient_id` y `expected_procedure_version`. Ya no admite selección, general, motivo, fecha o confirmación del popup anterior: esos campos se rechazan422. La respuesta añade `redirect_url` fijo al módulo autorizado. El cliente valida origen/ruta, actualiza el detalle y navega, sin construir un diálogo.

La transacción conserva orden de locks: operación UUID → identidad clínica/cita → revisión → solicitudes pendientes. Revalida actor activo, capacidad clínica, cuenta2, vínculo, cita/paciente, estado permitido y versión. Calcula los pendientes en servidor, crea únicamente filas MANUAL con actor/fecha y evento cifrado, sin motivo inventado ni fecha prevista. Los registros automáticos no cambian a MIXED.

Dos clics simultáneos en cliente producen una sola petición. El mismo UUID reenviado reconoce la operación previa200 sin nuevos registros, aun si el botón ya desapareció; otra operación con todos los procedimientos enviados recibe409. Cambiar paciente/versión o reutilizar UUID para otro payload se rechaza. Un error revierte también el ledger de la operación fallida.

`manual_reason_ciphertext` y `planned_review_on` ya son nullable: **no hay sexta migración**. El conjunto PR72 conserva exactamente las cinco migraciones documentadas. Los motivos, observaciones, fechas, estados y eventos de seguimientos anteriores se conservan. Los generales históricos siguen consultables/editables en el módulo; este botón y endpoint ya no crean generales nuevos. La edición posterior del seguimiento conserva su motivo/evidencia y validaciones propias.

## Alcance de producto

Solo cinco archivos:

- `app/Services/Scheduling/MedicalProcedureFollowupService.php`: contexto y envío directo; sincronización automática intacta.
- `app/Services/Scheduling/AdmissionBoardService.php`: comparte con el botón la misma lista/versión de procedimientos mostrada en el detalle. Si una selección concurrente cambia, el envío rechaza la versión obsoleta sin consultar otra lista silenciosamente.
- `app/Http/Controllers/Scheduling/MedicalProcedureFollowupController.php`: respuesta con destino del módulo.
- `public/js/scheduling/medical-followup-referral.js`: envío directo, exclusión de doble clic y reintentos con UUID estable.
- `public/js/scheduling/admission-board.js`: visibilidad defensiva, resultado/error y navegación.

Se conservan popup de solicitud, catálogo privado/oficial, Enter, atributos clínicos, chips, flujos de atención/reevaluación/salida, alertas, documentos, economía y ambas agendas. Los tests previos de creación general/envío con selección se adaptan a la nueva regla explícita; permanecen sus comprobaciones de conservación histórica, permisos, conflictos, idempotencia y ausencia de efectos económicos.

## Reproducción y demo

Antes de restaurar, se capturaron los IDs reales: A1 tenía tres procedimientos ya seguidos por las pruebas manuales del usuario; Q14 tenía cero y M15 una RM automática. Los tres mostraban el botón erróneamente. No se presentó A como vacío cuando no lo estaba. Se respaldó la BD descartable antes del reinicio; las evidencias y los cambios manuales previos permanecen en el respaldo privado.

En la demo restaurada: A1 sin solicitudes y sin botón; Q14 Radiografía/Curación confirmadas y pendientes de envío; U21 solicitudes ya enviadas; M15/S16 automáticos; V22 general histórico conservado. La guía local explica cómo agregar otro procedimiento y verificar la reaparición. Reset privado con frase y respaldo, solo ceosalud_demo_enterprise33345.

[Resultados y capturas de esta corrección](qa/direct/README.md). [Runbook actualizado](PREPARACION-PRODUCTIVA-PR72.md). No se accedió a Hostinger ni producción.

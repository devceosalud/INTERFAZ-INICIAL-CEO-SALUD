# Agenda operativa 2.0 — análisis funcional

## Baseline y preparación

Baseline remoto comprobado con `git fetch origin main` y `git ls-remote origin refs/heads/main`: **9c3b93a5086172d30fbf59fc9c4c2c40df84a645**, merge de PR #45. Coincide con el SHA solicitado; no hubo diferencias adicionales al iniciar. Esto no acredita el commit instalado en Hostinger.

El checkout principal estaba en `pilot/agenda-horarios-clientes`, HEAD `c76498b`, con cinco archivos modificados y varios worktrees preexistentes. Se conservaron sin reset, stash, checkout ni limpieza. Trabajo exclusivo en `.worktrees/agenda-v2`, rama **feat/agenda-operativa-flujo-reevaluaciones-v2**, creada desde `origin/main`. No se modificó `main`.

Se inspeccionaron registro operativo, creación, pagos, disponibilidad, comprobantes/OCR, visibilidad, tareas de admisión, recorrido de atención, presentación de ambas agendas, componente heredado de reevaluaciones, indicaciones clínicas, resolutores de precios, menús, rutas y workflows. Los siete workflows existentes responden a PR o ejecución manual y no contienen despliegue, FTP, SSH ni conexión a Hostinger. Se añade únicamente CI aislado.

## Diagnóstico heredado

- Registro y pagos ya tenían transacciones, bloqueo por médico, UUID por actor, comprobantes y protección de operaciones bancarias. Un adelanto podía proteger el horario sin confirmar `estado_agenda`.
- La vista de citas heredada comparte consulta, calendario, edición y seguimiento con Nicol. No era correcto bloquear toda su URL GET.
- Admisión tenía polling con revisión, cancelación y protección del campo enfocado. Agenda operativa solo refrescaba al cambiar filtros o ejecutar acciones.
- Las tareas libres tienen sus propias tablas y permisos, incluidos operadores comerciales. No equivalen a una solicitud estructurada de procedimientos.
- `estado_cita=REEVALUACION` tiene consumidores clínicos/heredados. La nueva cola no utiliza ni modifica ese valor.
- Los colores ya distinguen dinero real, presencia, salida, cancelación/retiro y reembolso. Los snapshots no conciliados no se convierten en dinero real.
- El layout cargaba Clarity en páginas sensibles; se excluye de las dos agendas y las páginas heredadas de citas que pueden mostrar la cola.

## Ocho cambios entregados

1. **Pago y confirmación coherentes.** Todo pago nuevo real positivo aceptado por Registro rápido o por una reserva confirma el horario en la misma transacción, aunque llegue `mode=RESERVE` o `confirm=false`. Conflictos revierten cita/pago/ticket/operación/adjuntos del intento. Sin pago sigue pendiente. OCR y cero no confirman. Continúa disponible la confirmación explícita de reservas históricas con dinero ya acreditado o exoneración autorizada; no se realiza un backfill.
2. **Creación heredada restringida.** ADMISION/COMERCIAL, salvo usuario que también sea ADMINISTRADOR, reciben 403 al intentar el registro heredado. Menús dicen «Seguimiento de citas» y no incluyen el formulario de creación para esos roles. La consulta GET y las ediciones autorizadas compartidas se conservan. ADMINISTRADOR y otros módulos previamente autorizados siguen sujetos a sus capacidades existentes.
3. **Refresco operativo.** Polling de cinco segundos después de completar cada consulta, con secuencia, abort y comprobación de filtros. No recarga el documento. Conserva el espacio de trabajo y pausa durante edición, creación de una selección nueva, modales, operaciones en curso o pestaña oculta. Una cita seleccionada sin cambios permite actualizar la proyección del calendario. Las respuestas antiguas no reemplazan resultados nuevos y una sesión vencida conserva el trabajo.
4. **Procedimientos estructurados.** Siete claves estables: radiography, ultrasound, laboratory, wound_care, mri, prp, hyaluronic_acid. Las etiquetas visibles son Radiografía, Ecografía, Laboratorio, Curación, Resonancia magnética, PRP y Ácido hialurónico. ADMINISTRADOR confirma expresamente el conjunto; ADMISION ve solo los solicitados y registra realizados/reaperturas. Un retirado conserva su fila y auditoría; solicitarlo otra vez exige nueva verificación.
5. **Reevaluación en la misma cita.** Al menos uno solicitado y todos realizados producen READY automático durante la jornada activa de Lima. Con algunos realizados y pendientes, ADMISION puede enviar con confirmación y motivo. ADMINISTRADOR necesita autorización de supervisión específica. Sin realizados no se permite. No se crea otra cita, hora, precio, atención o pago.
6. **Naranja consistente.** `AgendaAppointmentPresentation` y `AgendaLegend` comparten naranja y texto «Listo para reevaluación». Cancelación/retiro, salida, reembolso y advertencia financiera conservan prioridad. Los procedimientos muestran además su avance numérico. La clasificación de atención original permanece intacta.
7. **Ventana de Nicol.** Componente común «Reevaluaciones» en Admisión y en la vista heredada existente, ordenado por ready_at e ID. Incluye paciente, profesional, servicio, hora de referencia, origen, realizados, pendientes y estado. Filtra el día con America/Lima y la visibilidad real. Registros retirados/suspendidos/inactivos se distinguen de quienes esperan. El componente clínico anterior se conserva bajo un desplegable separado.
8. **Retroceder estado.** Corrige la última transición registrada por Admisión a EN_ESPERA, LLAMANDO o EN_ATENCION si todavía es la última y existe el snapshot previo. Exige motivo, UUID, estado e ID del evento esperado. Bloquea ATENDIDO, salida y estados finales. Conserva el evento original y el timestamp corregido en auditoría; restaura únicamente el timestamp efectivo de la transición y el estado previo. No modifica procedimientos ni cola.

## Compatibilidad y límites de negocio

Las nuevas citas de reevaluación clínica mantienen indicación escrita, origen atendido válido, evidencia privada, compatibilidad profesional/servicio, uso único y límites de días 0/1/15/16. La cola operativa no llama al resolutor económico ni consume indicaciones clínicas.

Una decisión manual con realizados y pendientes se conserva al editar solicitudes. Si se desmarca el último realizado, queda **SUSPENDED**, visible y auditada; con una nueva realización recupera READY y una nueva hora efectiva. **WITHDRAWN** solo vuelve por un envío explícito autorizado, aunque se completen más procedimientos. Las lecturas y el polling no cambian estas decisiones. No se añadió «Reevaluación completada» porque introducir un segundo cierre de atención no es parte de la autorización actual.

Deuda detectada: tareas libres y procedimientos son conceptos diferentes y mantienen permisos distintos; scripts heredados asumían la presencia del formulario de creación y se corrigió su inicializador; las pruebas con DDL necesitan aislamiento adicional en MariaDB por los commits implícitos; Clarity continúa fuera de las rutas excluidas, por lo que una política global de analítica requiere un proyecto separado.

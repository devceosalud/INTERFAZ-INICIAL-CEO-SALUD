# Hotfix de Agenda de admisión

Base: main 9c3b93a5086172d30fbf59fc9c4c2c40df84a645. Rama: hotfix/admision-reevaluacion-manual.

## Comportamiento

- ATENDIDO se muestra en verde oscuro #166534 y texto blanco. La marca manual usa naranja #fed7aa y texto #7c2d12.
- Un checkbox permite marcar/desmarcar la misma cita ATENDIDO con agenda LEGADO o CONFIRMADA. No exige procedimientos ni exámenes. No altera ningún atributo de appointments, ni genera cita, precio, pago o indicación clínica/comercial.
- Cancelación/retiro, reembolso y salida conservan su prioridad. Salida mantiene el tachado. Las alertas de conciliación, pagos e indicadores médicos permanecen. Solo se reemplazan bases visuales no críticas conocidas; un estado visual futuro conserva su presentación.
- La pestaña Reevaluaciones muestra las marcas activas del conjunto autorizado y filtrado; conserva fechas, sede, médico, especialidad, paciente, estado y grupo. Totales, indicadores y paginación usan el mismo conjunto. El límite existente de 2000 citas por consulta se mantiene.
- ADMINISTRADOR y ADMISION activos requieren acceso MVP, lectura y edición efectivos. COMERCIAL y los demás lectores consultan sin checkbox; el endpoint rechaza sus escrituras incluso con appointment.update. No se conceden permisos nuevos ni excepciones a reservas privadas.
- Todos los chips de Atención / Agenda usan fuente 12–12,5 px, negrita 700, padding 4×8 px y altura mínima 24 px. El resto del ERP conserva sus estilos.
- Ajuste visual final: el chip Agenda heredada se oculta solo en la tabla principal con visibility:hidden y aria-hidden=true. Conserva su espacio, colores y dimensiones; no modifica LEGADO ni bookingLabel ni el detalle. Reserva pendiente, horario confirmado y etiquetas desconocidas siguen visibles. En navegador la fila de referencia midió 91,86 px antes y después del ajuste.

## Persistencia sin migración

Se reutilizan appointment_events y appointment_operations, disponibles en main. No hay migración, nueva tabla ni columna. Los tipos REEVALUACION_MANUAL_MARCADA / REEVALUACION_MANUAL_DESMARCADA son exclusivos del hotfix. El último ID de esos dos tipos determina la marca; eventos ajenos no la cambian, incluso con timestamps iguales.

Cada cambio guarda actor, hora, origen ADMISSION_MANUAL_V1, operación, valor anterior/nuevo y evento anterior. La transacción usa una clave UUID única por actor y hash del contrato normalizado; el mismo intento no duplica auditoría. Reutilizar la clave para otro contenido devuelve 409. Reintentar una operación antigua tras un desmarcado devuelve el estado actual sin resucitar la marca. expected_marked y expected_version detectan conflictos, incluido marcar/desmarcar entre dos lecturas. Se bloquean operación, profesional y cita en el orden usado por los servicios existentes; se relee la visibilidad y el último evento bajo bloqueo. Una falla de auditoría revierte también la operación.

La lectura obtiene los últimos eventos con una consulta agrupada para el conjunto filtrado, sin una consulta adicional por fila. Los feeds y las respuestas mantienen private, no-store y la protección CSRF existente.

## Compatibilidad posterior con Agenda v2 / PR #46

No se utilizó ni modificó su worktree ni su implementación. La marca tiene servicio, endpoint, tipos de evento y origen propios. No escribe tablas de procedimientos ni indicaciones de reevaluación económica/clínica. No autoriza gratuidad, otra cita ni cumplimiento de procedimientos.

Al integrar posteriormente #46, conservar ambos contratos y resolver los puntos compartidos de AdmissionBoardService, rutas, vista, CSS y cliente. Desmarcar manualmente debe afectar solo esta fuente; no debe borrar ni desactivar una reevaluación automática. La presentación específica de admisión no cambia AgendaAppointmentPresentation compartida. Reejecutar ambas suites y validar prioridad de colores y permisos; no se afirma que una integración futura pueda hacerse sin resolución manual de conflictos.

## Pruebas y evidencia

Consultar [QA.md](QA.md) y [evidence/RESUMEN.json](evidence/RESUMEN.json). Todos los pacientes, importes, usuarios y documentos de QA son ficticios. El navegador se conectó exclusivamente a localhost:8097 con SQLite desechable; integraciones del servidor usan fakes y una CSP limita tráfico externo del navegador.

## Rollback para una liberación futura autorizada

1. Identificar y guardar el SHA desplegado y el artefacto anterior. Respaldar la BD antes de cualquier liberación autorizada.
2. Revertir el commit del hotfix mediante un commit git revert revisado, o restaurar el artefacto anterior por el proceso autorizado. Si hubiera commits posteriores, revertir únicamente los cambios del hotfix y resolver los conflictos; no resetear una rama compartida.
3. Renovar los assets y cachés de rutas/vistas mediante el procedimiento habitual de la liberación, y verificar que la acción manual y la pestaña ya no se publican.
4. Conservar appointment_events y appointment_operations completos. No ejecutar migrate:rollback, eliminar eventos ni reescribir citas/pagos. El código anterior simplemente ignora los nuevos tipos. Una reinstalación reconstruye la última marca a partir del historial.
5. Verificar lectura de agenda, permisos, fechas, pagos y precios. Si se requiere deshacer una marca individual, usar el checkbox autorizado con la versión vigente mientras la cita sea elegible; no borrar auditoría con SQL.

Este trabajo entrega un PR Draft para revisión. No incluye merge, despliegue, conexión a Hostinger ni escrituras en producción.

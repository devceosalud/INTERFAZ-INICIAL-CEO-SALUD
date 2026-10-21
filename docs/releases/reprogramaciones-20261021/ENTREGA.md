# Candidato local: historial visual de reprogramaciones — 21/10/2026

Rama `codex/release-reprogramaciones-20261021`, creada exactamente desde `1f840e90912d2ce8c55ce7502a22a1d582492bc3`. El responsable confirmó ese SHA como fuente del despliegue de Hostinger en `srv-prd01.ceosalud.com`, completado el 20/10/2026 a las 17:08. `git ls-remote origin refs/heads/main` coincidió con el mismo SHA durante esta preparación. No se accedió al servidor ni se verificaron sus archivos conservados; el preflight es una condición pendiente para cualquier despliegue autorizado.

El trabajo de correcciones económicas se conserva en su rama/worktree original. Esta rama parte del commit anterior a esa implementación: no incorpora sus commits, permisos, migraciones, modelos, servicios, rutas ni formularios económicos.

## Causa y comportamiento

La reprogramación actualiza la fecha/hora de la misma fila de `appointments` y guarda un evento `REPROGRAMADA`. El feed ya entregaba referencias separadas, utilizadas por un panel superior. La grilla diaria construía sus filas desde la disponibilidad y las citas vigentes, sin representar esas referencias en su intervalo de origen. Admisión filtraba exclusivamente por la fecha vigente de la cita.

Ahora la grilla diaria agrega una marca azul grisácea `REPROGRAMADA → fecha, hora` dentro de cada tramo visual del intervalo anterior. El texto de disponibilidad se obtiene del contexto vigente del motor: si otra cita ocupa el intervalo, se muestran la cita y la marca secundaria, sin anunciar disponibilidad falsa. Si el horario fue retirado, se informa que actualmente está fuera del horario configurado. Las citas extendidas conservan el rango de origen registrado en el evento.

Semana y mes muestran insignias accesibles en el DOM de las cabeceras/días de FullCalendar. Nunca se registran como eventos ni se añaden al arreglo de ocupaciones. La tabla comparativa conserva sus cifras y el panel histórico común.

Admisión incluye una sección plegable **Reprogramaciones del día**, con origen, destino del evento y ubicación actual. Las filas vigentes llevan un distintivo de su reprogramación más reciente. La referencia histórica solo ofrece navegar a la cita vigente; no contiene controles de llegada, atención, cobro o edición. Se corrigió además la inicialización de ambos extremos del filtro de fecha al navegar a esa cita, evitando un rango inválido cuando el destino es posterior al día consultado.

## Invariantes

- Se consultan los eventos existentes; no se crean citas, eventos ni pacientes desde la visualización.
- No se modifican `RescheduleAppointmentService`, el motor de disponibilidad, las reglas de confirmación, el estado clínico, los semáforos, los contadores ni la proyección financiera.
- Los historiales viajan en `reprogramaciones` / `reschedules`, separados de `eventos` / `rows`. Los KPI y los importes se calculan exclusivamente a partir de las filas vigentes.
- Se preservan todas las reprogramaciones sucesivas. El enlace apunta a la fecha/médico actuales y el texto distingue el destino del evento de la ubicación vigente.
- Autenticación, permisos efectivos MVP/VIEW y visibilidad operacional existentes. La nueva ruta de lectura tiene límite de peticiones y rechaza cuentas inactivas. No se crean ni conceden permisos.
- El historial no expone nombres/documentos de pacientes, motivos clínicos, motivos de reprogramación ni importes. La identidad del autor mantiene la política existente de visibilidad del creador.
- El filtro de fecha/médico/sede se aplica al origen registrado. Los filtros de paciente, estado, grupo, revisión y categoría visual usan la cita vigente. La búsqueda de paciente sigue en el cuerpo POST de Admisión.
- Paginación independiente de la cola: 30 referencias por página en Admisión; 200 por carga inicial en Agenda. El cursor usa IDs de evento. Se permite consultar más sin duplicar referencias; los cambios de filtro descartan respuestas antiguas.
- Las lecturas con filtros calculados procesan como máximo 10 lotes de 201 eventos por petición y ofrecen continuación por cursor, incluso si no encontraron coincidencias. No se añade un índice ni otra migración.

## Esquema y dependencias

**No necesita cambios de esquema sobre la base confirmada.** El diff de `database/`, configuración, Composer y permisos está vacío. La prueba específica verifica la lectura con las migraciones originales y sin la tabla de correcciones económicas. También comprueba que no cambia la tabla de migraciones ni las filas de citas, pagos, tickets, líneas o turnos al consultar el historial.

Reutiliza `appointment_events.metadata` (`from`/`to`), `appointments`, sus relaciones y los servicios existentes de Agenda/Admisión. Requiere las dependencias ya presentes en la base: Laravel/Spatie, soporte JSON de SQLite/MariaDB, FullCalendar y los assets compartidos. No necesita `composer update`, instalación NPM ni compilación: los archivos públicos se sirven directamente y `AgendaAssets` versiona su contenido. Se agregó un único CSS local.

El verificador independiente y los dos manifiestos cubren 535 archivos de aplicación/dependencias. Se comparan textos normalizando exclusivamente CRLF a LF; se conservan fingerprints exactos de los archivos entregados en el manifiesto del ZIP. No se comparan `.env`, secretos, uploads, almacenamiento privado, dependencias instaladas ni cachés generadas contra Git.

## Límites y riesgos

- No se inventa un historial anterior que no exista. Eventos sin fecha/hora de origen o destino se omiten. Si un evento heredado no guardó duración, se muestra su hora de origen sin inventar un intervalo extendido. Si no guardó médico, mantiene la compatibilidad anterior usando el médico actual; no acredita quién atendía ese horario histórico. Las sedes nulas siguen el tratamiento heredado de sede no registrada.
- La consulta JSON puede requerir más tiempo con historiales grandes. Hay rango temporal, páginas, lectura acotada y continuación; no se ha medido carga productiva ni una población de millones de eventos. No se propone una migración de índices en este release.
- El historial es una proyección de lectura que se actualiza al consultar/refrescar. Una reprogramación concurrente puede volver antiguo el contexto ya mostrado; las acciones posteriores siguen las validaciones existentes sobre la cita vigente.
- Se verificó Chrome de escritorio y viewport móvil de 390 px; no se afirma cobertura de Safari/iOS ni de todos los dispositivos.
- SHA de despliegue y archivos instalados son comprobaciones distintas. Archivos retenidos, vendor, cachés u OPcache de Hostinger siguen pendientes del procedimiento autorizado. Ante divergencias, se detiene la activación.

No hay decisiones comerciales pendientes para este cambio visual. Los ajustes de tarifas/pagos y su auditoría quedan fuera de esta candidata.

Ver [RESULTADOS.md](RESULTADOS.md), [ARCHIVOS.md](ARCHIVOS.md) y [DESPLIEGUE_Y_ROLLBACK.md](DESPLIEGUE_Y_ROLLBACK.md). Las capturas de `evidence/` usan exclusivamente pacientes, profesionales, usuarios, citas y dinero ficticios.

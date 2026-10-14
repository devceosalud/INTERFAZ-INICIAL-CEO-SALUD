# Admisión operativa de Nicole — desarrollo

Fecha: 14/10/2026. Rama: codex/mvp-admision-nicole. Base: PR #10 (que depende de #9 → #8 → #7 → #6 → #5). Alta de Magdalena: PR #11 independiente sobre #5; no se fusiona su código dentro del panel.

## Comportamiento

- Pantalla /scheduling-mvp/admission, estructura contrastada con el Figma de Rodrigo: seis indicadores, filtros, tabla, detalle lateral, recorrido y disponibilidad. Hoy America/Lima y Magdalena resuelta por nombre único activo del catálogo, nunca por ID fijo. Si falta/ambigua, aviso explícito; no crea sedes ni reasigna filas NULL.
- Filtros POST con fecha/rango máximo 31 días, sede, especialidad, médico, paciente/documento, estado original y grupo. Identidad no se envía en URL. Máximo 2000 resultados antes de agrupar; obliga a acotar en vez de mostrar totales truncados. Páginas de 30; indicadores y totales abarcan el mismo conjunto filtrado completo.
- Por llegar = PROGRAMADO/CONFIRMADO vigente sin hora_llegada; espera = PACIENTE_LLEGO/EN_ESPERA/LLAMANDO; consultorio = EN_ATENCION; atendido = ATENDIDO; desactivadas = CANCELADO/NO_ASISTIO/RETIRO, distinguidos. Reservas pendientes y registros inconsistentes se identifican por separado; no se cambia ningún estado para visualizarlo.
- Reutiliza la consulta visible de Agenda y AppointmentEconomicPosition. Pagos acreditados, snapshot histórico y atribución pendiente se diferencian; no crea pagos ni copia importes para pintar el panel. Histórico administrativo limitado a 20 citas visibles, sin motivos/observaciones/diagnósticos ni contenido de documentos médicos.
- Pago/OCR, confirmación, agendamiento y reprogramación abren Agenda con fecha, médico y cita concreta; conserva un único motor financiero. Ficha administrativa reutiliza pacientes. Disponibilidad proviene del motor real y siempre se revalida al reservar.
- Llegada, llamado, inicio y fin requieren UPDATE efectivo, cita visible y Agenda LEGADO/CONFIRMADA. Delegan LegacyAppointmentMutation; conservan permisos/controles financieros. Estado esperado y UUID con bloqueo transaccional evitan actualizaciones obsoletas y eventos duplicados. Se estampan únicamente actuaciones realizadas, sin inventar las anteriores.
- Cancelación, inasistencia y retiro continúan en el flujo autorizado de Agenda; actualizar/volver al panel recupera su estado. No son devoluciones.

## Evidencia actual

PHP 8.3.35: focal SQLite 11/143 antes de ampliar el caso de seis roles; focal MariaDB 11.8.9 final 12/167. JavaScript focal 4/4. Suites completas y QA visual en curso; actualizar este apartado con resultados ejecutados antes del cierre.

## Límites y publicación

Exportación Excel y recetas/informes versionados aún pendientes (R12/R14); el panel no expone su contenido ni los presenta como disponibles. La acción clínica/administrativa registra seguimiento; no sustituye una historia clínica. No se modifican ni recalculan citas históricas al consultar.

Sin nuevas migraciones, dependencias, seeders o grants en este PR. Requiere las migraciones previas de la cadena, permisos ordinarios efectivos y configuración de pagos revisada. Magdalena se da de alta por el mecanismo supervisado de #11; horarios/citas heredados NULL se consultan como tales, sin atribución automática.

Publicación solo autorizada: respaldar código/BD/adjuntos y APP_KEY, verificar SHA/autodespliegue, restaurar copia en staging equivalente y ejecutar circuito ficticio antes de activar. Rollback de este panel: volver al código previo manteniendo BD, pagos y documentos posteriores; no revertir migraciones históricas ni restaurar una BD antigua sobre nuevos cobros. Conservar la rama/commit exactos y conciliar movimientos si se propone otra recuperación.

Clarity permanece íntegro. Su admisibilidad sobre salud/finanzas sigue pendiente de responsable de privacidad/negocio antes de datos reales; el panel no elimina ese bloqueo. No se ha accedido ni modificado producción.

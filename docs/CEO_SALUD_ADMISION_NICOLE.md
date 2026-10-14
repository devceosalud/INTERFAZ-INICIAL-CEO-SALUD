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

- PHP 8.3.35, suite completa SQLite: **821 pruebas / 6396 aserciones**.
- PHP 8.3.35 + MariaDB 11.8.9 aislada: **311 / 3612**. El primer pase falló por fixtures persistidos después de la reconstrucción de esquema en AgendaConcurrencyTest; se invalidó el caché de RefreshDatabase y el pase completo posterior fue satisfactorio, sin cambiar reglas financieras.
- Proyección administrativa mínima posterior: focal **12 / 174** en SQLite y **12 / 174** en MariaDB. No selecciona motivos/observaciones ni campos personales innecesarios para el panel.
- JavaScript completo final: **247/247**; incluye respuestas tardías, sesión HTML, errores privados de servidor y selección de módulos para destruir selectpicker. El tema no duplica controles en Nicole.
- Navegador ADMIN ficticio: desde Nicole se abrió Agenda, se creó paciente nuevo con DNI ficticio y teléfono, se aplicó descuento fijo S/20 sobre catálogo S/100, se reservó sin dinero, se volvió al detalle de la misma cita, se abrió su pago/OCR, se leyó Yape sintético S/75 y se aplicó/revisó expresamente antes de cobrar. El pago dejó la reserva pendiente; la confirmación se hizo por acción separada. Saldo S/5. Llegada → llamado → inicio → finalización actualizaron KPIs y marcas reales sin alterar dinero.
- SELECT de QA posterior: 2 citas (1 histórica intacta y 1 nueva), 1 pago S/75, 1 comprobante privado vinculado a ese pago, revisión humana true y bank_validated false. El fixture no usa pacientes ni comprobantes reales.
- Navegador Agenda: alta modal completada; cambio con datos sin guardar cancelado conserva el motivo; descarte explícito limpia motivo, descuento, importe y archivo. Día/Semana/Mes muestran la cita actualizada.
- Seis perfiles comprobados automáticamente: ADMINISTRADOR, ADMISION, COMERCIAL, RECEPCION, CAJA y FACTURACION; VIEW permite consultar y UPDATE efectivo es necesario para atención. No se afirma haber recorrido los seis perfiles en navegador.
- Captura local: storage/app/qa-rules/nicole-desktop.png. Escritorio observado con viewport real **1164×654**; al solicitar 390×844 la herramienta conservó el tamaño de escritorio incluso en pestaña nueva. **QA móvil pendiente**, pese a los breakpoints implementados; no se declara aprobada.

## Límites y publicación

Exportación Excel y recetas/informes versionados aún pendientes (R12/R14); el panel no expone su contenido ni los presenta como disponibles. La acción clínica/administrativa registra seguimiento; no sustituye una historia clínica. No se modifican ni recalculan citas históricas al consultar.

Sin nuevas migraciones, dependencias, seeders o grants en este PR. Requiere las migraciones previas de la cadena, permisos ordinarios efectivos y configuración de pagos revisada. Magdalena se da de alta por el mecanismo supervisado de #11; horarios/citas heredados NULL se consultan como tales, sin atribución automática. Para citas nuevas con sede explícita, configurar sus horarios futuros con el ID real mediante el editor existente; este PR no vincula horarios históricos automáticamente.

Publicación solo autorizada: respaldar código/BD/adjuntos y APP_KEY, verificar SHA/autodespliegue, restaurar copia en staging equivalente y ejecutar circuito ficticio antes de activar. Rollback de este panel: volver al código previo manteniendo BD, pagos y documentos posteriores; no revertir migraciones históricas ni restaurar una BD antigua sobre nuevos cobros. Conservar la rama/commit exactos y conciliar movimientos si se propone otra recuperación.

Clarity permanece íntegro. Su admisibilidad sobre salud/finanzas sigue pendiente de responsable de privacidad/negocio antes de datos reales; el panel no elimina ese bloqueo. No se ha accedido ni modificado producción.

# RC — jornada ficticia multiusuario

Código probado `b388bd3f4816e30506e913aec33234518949db5b`; Chrome 155.0.8059.79, America/Lima, dos servidores PHP loopback, BD aislada 33322; cookies independientes. 79 casos aprobados en 17 etapas. No datos ni servicios productivos.

## bootstrap

- PASS — Administrador principal inicia sesión real (1839 ms)
- PASS — ADMIN crea admin2 desde Usuarios (5358 ms)
- PASS — ADMIN crea commercialA desde Usuarios (5300 ms)
- PASS — ADMIN crea commercialB desde Usuarios (5186 ms)
- PASS — ADMIN crea nicole desde Usuarios (4976 ms)
- PASS — ADMIN crea reception desde Usuarios (5303 ms)
- PASS — ADMIN crea cashier desde Usuarios (5090 ms)
- PASS — admin2 mantiene cookie y autenticación independientes (1377 ms)
- PASS — commercialA mantiene cookie y autenticación independientes (1332 ms)
- PASS — commercialB mantiene cookie y autenticación independientes (1180 ms)
- PASS — nicole mantiene cookie y autenticación independientes (1221 ms)
- PASS — reception mantiene cookie y autenticación independientes (1229 ms)
- PASS — cashier mantiene cookie y autenticación independientes (1296 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-bootstrap.json).

## permissions-schedules

- PASS — Comercial sin CREATE: interfaz bloqueada y petición directa 403, cero dinero (623 ms)
- PASS — Horarios: comercial sin capacidad recibe 403 en crear, editar e inactivar (575 ms)
- PASS — ADMIN concede CREATE, UPDATE y gestión de horarios mediante editor existente (1327 ms)
- PASS — Sesión comercial existente recibe capacidades nuevas al refrescar sin evadir validaciones (1871 ms)
- PASS — Comercial crea horario desde formulario real con sede, médico y cadencia (4233 ms)
- PASS — Calendarios de horarios Día, Semana y Mes representan el bloque persistido (121 ms)
- PASS — ADMIN revoca horarios y CREATE; misma sesión rechazada en backend (2079 ms)
- PASS — ADMIN restituye únicamente CREATE para continuar la jornada (1194 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-permissions-schedules.json).

## jornada-reserves

- PASS — Comercial A crea paciente ficticio y reserva sin dinero desde modal (2003 ms)
- PASS — Comercial B no ve identidad de A y reserva el mismo intervalo todavía libre (4261 ms)
- PASS — Sin permiso financiero: campo y cobro HTTP rechazados; CSRF real rechaza 419 (2174 ms)
- PASS — Administrador habilita cobros mediante editor, comercial refresca y puede ingresar adelanto (3776 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-jornada-reserves.json).

## jornada-pay

- PASS — Cobro sin permiso bloqueado por UI y backend; petición sin CSRF rechazada (1991 ms)
- PASS — Permiso de cobro concedido desde editor y efectivo tras refrescar sesión comercial (3597 ms)
- PASS — OCR Yape local sugiere datos de comprobante ficticio y no registra dinero (1867 ms)
- PASS — Revisión humana explícita y adelanto S20: reserva protegida y todavía pendiente (1368 ms)
- PASS — Comercial B conserva reserva y no puede cobrar el intervalo protegido ni conocer la reserva de A (3851 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-jornada-pay.json).

## jornada-conversion

- PASS — Reserva en conflicto ofrece resolución sin convertir; ADD ausente bloquea UI y HTTP (924 ms)
- PASS — ADMIN concede ADD expresamente y la conversión exige confirmación visible de precio (6052 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-jornada-conversion.json).

## jornada-confirm-care

- PASS — Adelanto S20 no permite confirmación ni altera el pago existente (720 ms)
- PASS — OCR BCP S30: segundo pago separado alcanza 50% sin confirmar automáticamente (1828 ms)
- PASS — OCR BBVA S50 en ADICIONAL pendiente admite adelanto y conserva reserva (2267 ms)
- PASS — Confirmación expresa de A; Nicole ve la cita mediante polling sin recarga (4866 ms)
- PASS — Confirmación expresa de ADICIONAL mantiene tres pagos y dos citas distintas (758 ms)
- PASS — Nicole recorre llegada, llamado, inicio y fin con confirmación y persistencia (9303 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-jornada-confirm-care.json).

## jornada-security

- PASS — Edición operativa permitida guarda motivo y actor sin cambiar precio ni pagos (487 ms)
- PASS — ADMIN retira UPDATE; interfaz abierta y petición directa ya no pueden editar (2576 ms)
- PASS — ADMIN retira PAY; formulario previamente habilitado no puede registrar otro pago (4427 ms)
- PASS — Administrador desactiva Comercial B desde Usuarios con motivo; sesión activa queda inválida (2928 ms)
- PASS — Reactivación conserva roles e historial y no revive la cookie anterior (2949 ms)
- PASS — Comercial B requiere inicio de sesión nuevo y recupera acceso ordinario (1172 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-jornada-security.json).

## prices

- PASS — Descuento fijo S20 cotiza neto S80 y guarda reserva sin cobro (3474 ms)
- PASS — Descuento porcentual 10% y postoperatorio conserva precio S90 sin gratuidad (4564 ms)
- PASS — Descuento 100% rechazado en cotización sin crear cita ni dinero (4119 ms)
- PASS — Exoneración administrativa requiere motivo y permiso; ADMIN confirma sin dinero solo con motivo (3407 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-prices.json).

## schedule-edit

- PASS — Crear otro día desde selección real del calendario conserva la fecha elegida (3572 ms)
- PASS — Editar horas y duración desde UI conserva citas y pagos vinculados (4100 ms)
- PASS — UI rechaza dejar sin horario una cita confirmada, sin escrituras parciales (2224 ms)
- PASS — Inactivar bloque futuro vacío desde UI conserva el registro y añade auditoría (2290 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-schedule-edit.json).

## overlap

- PASS — Alta solapada rechazada en formulario y servidor sin cambiar bloques heredados (1752 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-overlap.json).

## reevaluation

- PASS — Nicole acredita indicación escrita del médico en atención realizada y conserva evidencia privada (994 ms)
- PASS — Cotización HTTP autoritativa: días 0,1,15,16 y ausencia de indicación (864 ms)
- PASS — Agenda aplica tarifa médica S0 sin exoneración discrecional ni pago y vincula uso único (5247 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-reevaluation.json).

## documents

- PASS — Editor concede explícitamente VIEW y MANAGE de documentos a Admisión, sin añadir facultades financieras (1462 ms)
- PASS — Nicole adjunta receta ficticia privada a la cita correcta desde Agenda (827 ms)
- PASS — Comercial sin permiso documental recibe 404; usar la cita equivocada tampoco revela el archivo (360 ms)
- PASS — Reemplazo explícito con motivo crea nueva versión y conserva el archivo anterior fuera de descargas ordinarias (885 ms)
- PASS — Nicole muestra únicamente la versión vigente de la cita; revocar VIEW retira enlaces y acceso desde sesión existente (6856 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-documents.json).

## mobile

- PASS — Nicole móvil: viewport CSS 390 y navegación real sin desbordar el documento (2084 ms)
- PASS — Agenda móvil: viewport CSS 390 y navegación real sin desbordar el documento (3991 ms)
- PASS — Horarios móvil: viewport CSS 390 y navegación real sin desbordar el documento (1753 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-mobile.json).

## legacy

- PASS — ADMIN configura un horario futuro ficticio por interfaz para el flujo heredado (3126 ms)
- PASS — Calendario heredado utiliza recursos locales y carga sin dependencias externas (39 ms)
- PASS — Formulario heredado reserva doble duración con precio único calculado y sin dinero (3478 ms)
- PASS — Reprogramación heredada conserva indicación de duración y exige destino válido sin alterar pagos (7335 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-legacy.json).

## ocr-negative

- PASS — OCR ambiguo no elige importe ni registra dinero (1245 ms)
- PASS — Imagen ilegible no inventa monto u operación; permanece disponible el registro manual (685 ms)
- PASS — Duplicado bancario y sobrepago rechazados por servidor sin escrituras (815 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-ocr-negative.json).

## polling

- PASS — Sondeo real mantiene filtros y selección con intervalos de cinco segundos (11551 ms)
- PASS — Respuesta lenta no provoca sondeos simultáneos (10650 ms)
- PASS — Error transitorio conserva filas y totales y se recupera (9961 ms)
- PASS — Confirmación abierta pausa sondeos; cancelar preserva atención y dinero (8318 ms)
- PASS — Filtros sin aplicar y salida del módulo no generan consultas innecesarias (13739 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-polling.json).

## documents-poll

- PASS — Documento subido por Comercial aparece en el detalle de Nicole sin recargar ni cambiar filas (1365 ms)
- PASS — Versión reemplazada actualiza el enlace vigente mediante sondeo (5703 ms)
- PASS — Revocar VIEW retira documentos del panel abierto con la siguiente revalidación autorizada (6801 ms)

Evidencia: [peticiones y resultados](evidence/rc/final/browser-documents-poll.json).

## Persistencia y límites

ADMIN creó seis trabajadores y asignó/revocó capacidades desde el editor existente. Comercial A creó paciente/reserva; B conservó una segunda reserva privada válida. A registró 20+30; B convirtió explícitamente su misma reserva a ADD y pagó 50. Tres pagos separados suman S/100; el OCR no creó dinero. Confirmaciones expresas, llegada/llamado/inicio/fin y revocación de permisos/sesiones quedaron comprobados. Nuevos documentos/versiones no alteraron pagos. Ver final-db.json para totales finales, huérfanos, duplicados e histórico intacto.

La simultaneidad de escrituras se verifica además con procesos independientes y barreras en AgendaConcurrencyTest, RcConcurrencyTest y WorkerConcurrencyTest; no se presenta la interacción secuencial humana como una carrera de BD. No se ejecutaron operaciones externas de banco, SUNAT, RENIEC o Clarity.

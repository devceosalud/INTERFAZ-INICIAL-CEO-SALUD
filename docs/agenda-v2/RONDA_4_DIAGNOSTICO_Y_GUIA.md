# Cuarta ronda — procedimientos, gestión de citas y teleconsulta

Estado vigente de la continuación: [entrega exclusivamente local del20/10/2026](CONTINUACION_20261020.md). PR46 cerrado y sin fusionar; no publicar ni reabrir sin autorización. Los resultados anteriores son antecedentes.

Inicio verificado: c8b91fa8c4096345c9d5a7e59a523e0ab81a1236, worktree agenda-v2 limpio, PR46 abierto Draft. Checkpoint codex/agenda-v2-pre-round4-20261019. Los siete workflows del baseline finalizaron success; las suites anteriores no acreditan los cambios de esta ronda.

## Decisiones confirmadas por el usuario

- ADMINISTRADOR con acceso a Agenda puede transcribir procedimientos solicitados por el médico; se conserva el usuario que transcribe. Doctor y User no tienen una asociación automática en el esquema actual. No se identifica al transcriptor como médico tratante.
- TELECONSULTA: registro, consulta y reprogramación. Flujo remoto de atención pendiente; no generar llegada, presencia ni salida física.
- ADMINISTRADOR y ADMISION con autorización efectiva pueden cambiar modalidad antes de iniciar atención, incluso con pagos, mediante confirmación y auditoría. No modifica importes.
- El descuento ordinario sigue sin permitir 100 %. La exoneración autorizada es la operación existente de costo cero. Una clasificación de descuento total histórico requiere respaldo persistido de autorización, actor, motivo y monto; precio cero por sí solo no acredita autorización.

## Migración propuesta y alcance

2026_10_19_040000_add_agenda_v2_attendance_and_preferences añade attendance_mode varchar16 default PRESENCIAL (compatibilidad histórica), custom_label varchar500 nullable (sin tocar procedure_key varchar40) y agenda_professional_preferences (usuario/profesional únicos, favorito, contador y última selección). El procedimiento personalizado usa c:UUID, independiente del nombre. No hay backfill de procedimientos, pagos, clínicas o auditorías. Preferencias en BD permiten persistencia entre dispositivos; no guardan pacientes ni contenido clínico.

Se probó exclusivamente bajo IsolatedAgendaV2Qa, que comprueba host127.0.0.1, puerto33324, esquema y datadir ficticios exactos, versiónMariaDB11.8.9, APP_ENVtesting y ausencia de .env. Ninguna migración productiva autorizada ni ejecutada.

Rollback: conservar columnas/tablas y archivos; no ejecutar down cuando contienen datos nuevos. Código anterior no interpreta teleconsultas ni procedimientos personalizados: bloquear escrituras de atención/solicitudes durante reversión y mantener lector/política compatible. No restaurar una BD antigua perdiendo eventos posteriores. Matriz, QA y capturas finales se incluyen en el acta de continuación local.

## Diagnósticos y solución integrada

| Observación | Causa comprobada | Cambio |
|---|---|---|
| Procedimientos limitados | CATALOG tenía siete claves; la etiqueta se resolvía exclusivamente por esa clave. | Se mantienen los siete y se añaden solicitudes personalizadas con clave c:UUID y custom_label de hasta500 caracteres. Nombre significativo desde1 carácter, sin truncamiento; texto renderizado con textContent. |
| Consulta sin cobro aparecía pendiente | estado_pagado es un enum histórico PENDIENTE/PARCIAL/PAGADO y el frontend lo mostraba directamente. | Proyección financial_status común, independiente del enum, de los vouchers y de la confirmación. |
| Falta de gestión integral | Había editores parciales, reprogramación y documentos separados, sin un popup integrado. | Gestionar cita reutiliza economy, notes, detalle de Admisión, documentos, disponibilidad y reschedule. No crea un segundo motor de reservas ni editor económico. |
| Sin teleconsulta | No existía una modalidad de atención; price_modality distingue consulta/reevaluación económica y tipo_agendamiento regular/adicional/fuera de horario. | Campo attendance_mode independiente. Presencial predeterminada; teleconsulta persiste al registrar y reprogramar. |
| Etiquetas acumuladas | Cada agenda creaba sus propios elementos, con tamaños y reglas diferentes. | Componente compartido con deduplicación por clave, prioridades y desplegable +N accesible. Alertas críticas no se colapsan. |
| Programado amarillo con franja blanca | La franja previa reflejaba dinero/presencia; el badge reflejaba atención. | Franja descrita expresamente. Programado/confirmado sin categoría prioritaria usa amarillo suave; finanzas se muestran por separado, sin asumir pago. |
| Documentos extensos | Formulario de carga siempre visible e historial administrativo completo desplegado. | Lista primero, + Adjuntar archivo, formulario progresivo, cancelación, versiones secundarias y grupos de documentos/historial desplegables. |
| Búsqueda repetida del profesional | No había persistencia individual de última selección, favoritos o frecuencia. | Tabla por usuario y profesional; hasta5 favoritos y5 frecuentes, última selección válida y activa. Escrituras serializadas en cliente y por usuario en servidor. |

Se conserva la identidad del transcriptor y del verificador, así como fechas y snapshots de las solicitudes. Retirar desactiva selected; no borra la fila ni su ledger. El nombre confirmado es inmutable: para corregirlo se retira y se solicita otro con identificador nuevo. Una selección enviada por un cliente anterior, que omite custom_procedures, conserva las solicitudes personalizadas activas. Realización, contador, cola automática/manual y suspensión/retirada siguen usando el mismo servicio y versión; nunca se inventa una realización desde una marca manual antigua.

## Consultas sin cobro

| Condición acreditada | Estado presentado |
|---|---|
| es_exonerado y autorizado_por persistidos válidos | Exonerada · sin cobro autorizado |
| Precio0, SAME_DAY_REEVALUATION y origen/evidencia/fecha/paciente/servicio acreditados según reglas existentes | Sin costo por regla clínica |
| Descuento total histórico, monto igual al catálogo positivo, actor y motivo, más autorización persistida de costo cero | Sin saldo por descuento autorizado |
| Precio0 sin ese respaldo, o asignación económica ambigua | Pendiente de validación |
| Saldo real positivo | Pendiente de pago |
| Pago real completo atribuido y saldo0 | Pagado |
| Saldo cubierto mediante crédito | Sin saldo · crédito aplicado |

El caso histórico no autoriza nuevos descuentos del100 %. El descuento ordinario mantiene su rechazo previo; se utiliza exoneración conforme a la decisión del usuario. No hay una operación para crear descuentos totales nueva ni un backfill que los suponga. No se comprueba una autorización antigua según el rol actual del autorizante: se conserva el respaldo histórico persistido. Los snapshots ambiguos y montos heredados no conciliados no se convierten en pagos reales. Esta clasificación es una lectura: no crea pagos0, no cambia estado_pagado, importes, vouchers o confirmaciones. La acreditación clínica continúa invalidándose si se corrige su origen; los casos de días0,1,15,16 permanecen bajo la suite completa.

## Gestionar cita

Un clic conserva selección. Doble clic en una cita existente abre el dialog; Alt+Enter y el botón Gestionar cita proporcionan acceso equivalente. Un segundo clic sobre la misma selección no reconstruye la fila. Se espera la carga pendiente y se impide abrir dos diálogos. Los borradores de Registro rápido deben guardarse o descartarse antes de abrir el gestor; no se pierden silenciosamente.

Datos principales y motivo están arriba. Datos operativos, reprogramación, economía, documentos e historial se abren progresivamente. Solo se editan motivo, observación e indicadores explícitos con UPDATE y visibilidad de escritura. El cambio de modalidad exige además ADMINISTRADOR/ADMISION, estado anterior al inicio de atención y confirmación expresa. Tener lectura ADMIN de una reserva ajena no concede escritura sobre ella. Los campos económicos, identidad, profesional y servicio son de consulta; no hay edición ordinaria de precios pagados.

La operación notes admite UUID y expected_revision. La revisión incluye atributos originales y último evento, para detectar también cambios que vuelven al mismo valor dentro del mismo segundo. Se comprueba con la cita bloqueada, se audita y se concluye la operación de forma idempotente. El contrato anterior sin revisión conserva su filtrado de campos; el contrato del gestor rechaza campos ajenos a la operación. Los IDs privados y ausentes mantienen respuesta indistinguible. Una prueba adicional demostró que el envío de modalidad sin cambio por COMERCIAL con UPDATE recibía200 sin modificar datos; ahora se rechaza403 antes de crear operación o evento, incluso si el valor coincide. Se conservan la evidencia anterior y posterior.

Reprogramación consulta el endpoint existente de disponibilidad, que solo entrega intervalos, y llama al mismo reschedule con fecha/hora esperadas y revisión opcional del gestor. Clasificaciones regular/fuera de horario siguen requiriendo confirmación cuando cambian. El motor mantiene ocupación, duración y reglas económicas, incluida la revisión obligatoria si una indicación cambia de precio y existen pagos/tickets. No se permite cambiar profesional/servicio: la regla existente exige nueva cita y revisión del crédito, conservando el original. El panel de cancelación/retiro se mueve temporalmente al dialog, sin duplicar formularios ni servicios; al cerrarlo se restaura. No se añade eliminación física.

Cerrar o cambiar de sección no elimina documentos guardados. Seleccionar archivo genera borrador protegido; no se guardan otros campos del gestor a costa de perder ese archivo. El visor y sus workers se destruyen al retirar su contenido. Actualizar pide confirmación antes de descartar datos. El polling queda suspendido con el dialog abierto; al guardar se actualiza el calendario y al cerrar se sincroniza el espacio de trabajo, manteniendo profesional y filtros.

## Teleconsulta: alcance aprobado y pendiente

TELECONSULTA es modalidad de atención; REGULAR/ADICIONAL/FUERA_HORARIO son condiciones de agendamiento. Las dos dimensiones e indicadores postoperatorio/herida pueden coexistir. La etiqueta violeta procede únicamente del atributo guardado. No se deduce de servicios, no crea un adicional, no cambia precio y no genera enlaces externos. La sede sigue siendo la sede administrativa de la reserva; no se interpreta como presencia física ni se inventa una nueva sede. Reprogramar conserva la modalidad y las validaciones de horario existentes.

El usuario decidió dejar pendiente el flujo remoto de atención. Por ello no se ofrecen ni aceptan llegada, espera física, llamado, atención/finalización por el recorrido físico, salida, retiro físico o inasistencia remota como si fueran actuaciones presenciales. La cancelación administrativa conserva sus reglas. No se borran timestamps anteriores al convertir una modalidad; son historia real. No hay videollamadas, enlaces ficticios, prescripciones o historia clínica electrónica. Definir espera/atención/finalización remotas requiere una decisión posterior.

## Permisos de cuarta ronda

Precondición común: usuario activo, sesión/CSRF, MVP_ACCESS/VIEW y cita visible según cada operación. Ningún permiso general nuevo se concede automáticamente.

| Operación | ADMINISTRADOR | ADMISION | COMERCIAL / otros |
|---|---|---|---|
| Transcribir solicitud predeterminada/personalizada | Sí, confirmación explícita | No | No |
| Registrar realizado/reapertura | Sí | Sí | No |
| Envío parcial manual | Con supervisión específica | Sí | No |
| Marca directa ATENDIDO | Con UPDATE | Con UPDATE | COMERCIAL autorizado, sin UPDATE; otros no |
| Consultar gestor | Lectura operativa autorizada | Según visibilidad | Según visibilidad |
| Editar datos operativos | UPDATE + visibilidad de escritura | Igual | Solo si ya tiene UPDATE y visibilidad; no se concede |
| Cambiar modalidad | UPDATE, antes de atención, confirmar | Igual | No, incluso con UPDATE |
| Reprogramar | RESCHEDULE y reglas vigentes | Igual | Solo con capacidad ya concedida y visibilidad |
| Cambiar precio/profesional/servicio pagados desde gestor | No se implementa escritura ordinaria | Igual | Igual |
| Favoritos/frecuentes | Solo preferencias propias | Solo propias | Solo propias con acceso a Agenda |

Documentos aportados mantienen la matriz de tercera ronda: ADMIN consulta/adjunta/reemplaza; COMERCIAL consulta/adjunta en citas visibles sin adquirir UPDATE o pagos; ADMISION solo con sus permisos documentales existentes. Evidencias clínicas acreditadas exigen su permiso específico y no se confunden con documentos aportados. La nueva interfaz mantiene MIME real, firma,8MB, almacenamiento privado, descargas autorizadas, PDF.js local y auditoría/versiones. Los archivos y rutas internas nunca forman parte de preferencias o telemetría.

## Favoritos y frecuentes

Cada usuario conserva como máximo5 favoritos y ve hasta5 frecuentes activos. Se restaura el último profesional válido al entrar sin un doctor explícito en la URL. El contexto de retorno explícito tiene prioridad. Las preferencias se guardan en servidor y están disponibles en otra sesión/dispositivo; el navegador no es el almacén exclusivo. Inactivos desaparecen de la lectura y no aceptan nuevas preferencias. Sede, especialidad y búsqueda continúan filtrando la Agenda; un favorito no elude disponibilidad ni permisos.

Se agrupan bajo Favoritos y frecuentes para mantener utilizable la búsqueda manual en laptops. Las selecciones esperan la carga de la cita y las escrituras de preferencias se encolan, evitando perder la última elección al marcar un favorito rápidamente. La hora guarda microsegundos para desempatar selecciones cercanas. No se guarda información del paciente, clínica ni financiera.

## QA y revisión visual

SQLite:1130 pruebas /10028 aserciones. MariaDB:1142 /10141, incluidas12 carreras. JavaScript:324. Chrome:43 recorridos funcionales y10 superficies de consola revisadas. Todas estas ejecuciones finalizaron con exit0 y sin fallos, errores u omisiones. Los recorridos completos Chrome y sus capturas se registran en [QA y evidencias](QA_Y_EVIDENCIAS.md); no se consideran aprobados por el solo hecho de iniciarse.

CI anterior c8b91fa: siete workflows completados success, incluidos SQLite1083/9468, MariaDB1092/9556 y JS318 verificados en sus logs. Estos resultados anteriores no sustituyen los checks del nuevo commit. PR46 está cerrado, Draft y sin fusionar. La CI del SHA local queda pendiente de autorización para publicar; esta continuación no hace push ni modifica el PR. No hay workflows de despliegue añadidos.

Las primeras suites de esta ronda detectaron dos incompatibilidades de contrato y una prueba que reconstruía la tabla antigua sin la extensión nueva. Se conserva el contrato sin revisión y se actualiza el montaje del esquema de esa prueba sin retirar ninguna aserción de integridad. Chrome detectó una carrera de selección/doble clic y una sincronización innecesaria al refrescar el gestor; se corrigieron y se volvieron a recorrer. No se retiraron pruebas ni se flexibilizaron permisos para obtener verde.

## Guía local

Servidor persistente: http://127.0.0.1:8092, router seguro de QA, exclusivamente local. MariaDB ficticia en127.0.0.1:33324. No existe .env en agenda-v2. Cuentas nuevas: agenda-r4-admin@example.invalid, agenda-r4-admission@example.invalid y agenda-r4-commercial@example.invalid. La contraseña ficticia está exclusivamente en storage/app/qa-v2/ACCESO_MANUAL_QA.md o browser-private.json, ignorados por Git; no se publica en documentación del repositorio.

1. Entrar con ADMIN, fecha19/10/2026, MÉDICO RONDA CUATRO FICTICIO. TELE_ADDITIONAL combina teleconsulta, adicional, postoperatorio y herida explícitos. Abrir +N para restantes indicadores.
2. Doble clic, Alt+Enter o Gestionar cita: editar motivo/observación permitidos. Cambiar modalidad antes de atención y confirmar; revisar auditoría y dinero intacto. Usar Reprogramación para consultar horas y confirmar un nuevo destino válido.
3. Con otra sesión ADMISION, editar la misma cita antes de guardar el primer modal: debe rechazar la versión vieja y conservar el borrador. Actualizar permite consultar la nueva versión tras confirmar descarte.
4. Admisión → CUSTOM → Editar solicitud → Otro procedimiento → X/EMG → Confirmar selección. ADMISION marca realizado y lo desmarca; comprobar progreso/origen y ambas agendas. Un parcial sin autorización no se vuelve naranja.
5. EXEMPT muestra exoneración y ZERO validación pendiente. El selector Teleconsulta no acredita pago; el registro nuevo sin pago queda reserva y un adelanto real confirma una sola vez.
6. Documentos e historial → Documentos de la cita → + Adjuntar archivo. COMERCIAL puede cargar un archivo ficticio, ADMIN revisarlo; las versiones están en el desplegable. ADMISION sin permisos no descarga por URL directa. El PDF se visualiza localmente sin URL pública.
7. Marcar estrella junto a un profesional; abrir Favoritos y frecuentes. Seleccionarlo, recargar y volver desde otra sesión del mismo usuario: debe restaurarse. Otra cuenta conserva sus propias preferencias.

PHP se reinicia mediante tools/qa/start-agenda-v2-manual.ps1 -RestartOwnedServer, con MariaDB QA disponible. Detener ERP: storage/app/qa-v2/stop-manual-qa.ps1; añadir -StopDatabase para apagar únicamente la instancia ficticia de forma ordenada. Se verificó y actualizó su registro local de procesos al reiniciar esta ronda. No usar el runner que reconstruye la BD de navegador para una sesión manual. No detener XAMPP ni otros worktrees.

## Despliegue, rollback y riesgos

No se ha autorizado ni realizado despliegue, merge o acceso a Hostinger/producción. Antes de una futura liberación: confirmar SHA/esquema reales, respaldo coordinado BD+archivos probado, ensayar la migración aditiva, publicar backend/Blade/JS/CSS coherentes e invalidar cachés de vistas/assets conforme al plan. Mantener los límites PHP/proxy para8MB y el disco privado. Las migraciones se ensayan solo en QA; un éxito local no acredita tiempos de ALTER ni capacidad del hosting.

Rollback conserva columnas, tablas, preferencias, solicitudes, eventos y archivos/versiones posteriores. El código anterior no comprende c:UUID ni teleconsulta: revertirlo ciegamente puede fallar en etiquetas o registrar atención física incorrecta. Mantener lector/política compatible o bloquear temporalmente escrituras de solicitudes y atención durante la reversión. No ejecutar down con datos nuevos, ni borrar eventos, ni restaurar una BD anterior perdiendo escrituras posteriores. Mantener también la compatibilidad documental PATIENT_PROVIDED de tercera ronda. Pagos y comprobantes no se reescriben.

Pendiente de negocio por decisión expresa: recorrido de atención remota. Fuera de alcance confirmado: videollamadas externas y un nuevo sistema para ajustes financieros/profesional/servicio sobre citas pagadas. Riesgos restantes: aceptación visual humana, recursos/locking/backup del hosting no inspeccionados, rendimiento con carga productiva no medido, antivirus documental no incorporado. La interfaz es administrativa y no sustituye una historia clínica electrónica.

### Hallazgo adicional en regresión con más de30 citas

Chrome reprodujo que Actualizar detalle perdía la cita seleccionada si no pertenecía a la página actual del listado. El frontend solo volvía a consultar el detalle si payload.rows contenía su ID; con las nuevas fixtures acumuladas la cita quedaba fuera de las primeras30. Tras descartar un borrador no se mostraba su versión actual. Se conserva la evidencia fallida. La actualización explícita ahora recupera siempre el ID seleccionado mediante el endpoint autorizado, independientemente de paginación/filtros; el polling conserva su condición de selección vigente. No se amplía visibilidad ni se omite autorización backend. El recorrido de borradores/conflicto409 existente verifica la corrección sin retirar aserciones.

### Telemetría técnica por evento

El recorrido documental detectó422 en lotes de geometría de interfaz: Laravel aplicaba distinct a expanded.* entre todos los eventos del lote, aunque repetir el mismo panel en dos clics distintos es válido. La regresión falló antes y pasó después. La unicidad se valida ahora dentro de cada evento; permanecen el allowlist de cinco nombres técnicos, máximos, UUID y rechazo de texto/IDs/HTML/valores/datos de pacientes. No se captura contenido documental ni se añade un sistema analítico. Las evidencias anterior/posterior se conservan junto al QA de cuarta ronda.

## Integración del main que avanzó durante esta ronda

Al inicio, el PR usaba dcb9b2b como base. La actualización posterior de referencias confirmó origin/main en0f21c62aa09cc66abb787b67c5954d3a01b9459f, merge dePR51. Su diff neto respecto de dcb son siete archivos de reportes CSV; los cambios de catálogo dePR50 aparecen expresamente revertidos en su historia. No se reintroducen esos cambios por resolver la integración.

Se protegió el trabajo en5299381 y se ensayó merge-tree antes de modificar archivos. El merge real se detuvo sin commit para revisar dos coincidencias: agenda/index conserva todos los assets/preferencias/modal y añade el enlace gerencial condicionado por ADMIN+VIEW_AUDIT; routes/scheduling conserva las rutas operativas y añade el grupo CSV con rol, permiso y throttle propios. No hubo marcadores de conflicto textual ni se eligió indiscriminadamente una versión. Se verificó el diff neto de siete archivos y se creó el merge local0ba1c23bcf8e52da0e641bda155f39c860a1c843. Main, su checkout original y los otros worktrees permanecieron sin modificación.

La QA previa al merge quedó guardada separadamente: SQLite1116/9645; MariaDB1128/9758; JS323 y Chrome40. No acredita la integración posterior. Se repiten las suites completas y se añade una lectura real del CSV ficticio con ADMIN y rechazos para ADMISION/COMERCIAL. La exportación mantiene su contrato y columnas históricas; la clasificación financial_status de Agenda no reescribe el enum exportado ni los importes.

Durante una reversión sin paquete compatible, bloquear toda la superficie de procedimientos/atención de Agenda MVP, incluidas lecturas de claves personalizadas, no solo escrituras. Mantener consultas históricas autorizadas que no dependan de esos lectores. Esta contención es temporal: una reversión funcional completa requiere el paquete compatible probado, sin borrar tablas o archivos.

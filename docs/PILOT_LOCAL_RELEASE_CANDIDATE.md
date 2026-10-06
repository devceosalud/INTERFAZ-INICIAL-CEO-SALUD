# Piloto local: Horarios, Agenda y Pacientes

Fecha de revisión: 2026-10-05. Rama `pilot/agenda-horarios-clientes`, basada en
`1bf9785a5d11560bd8b39190f014ee7bb61dda00`. Este informe se incluye en el commit único del release candidate; resolver su SHA mediante Git.
Este documento y `PILOT_RELEASE_MANIFEST.json` son preparación de revisión, no autorización de despliegue.

## P0: diagnóstico inicial del 05/10 (resuelto el 06/10) y alta normal

La BD local se verificó mediante Laravel y MySQL: ambiente `local`, host `127.0.0.1`,
puerto `3308`, base equivalente a `ERPCEOSALUD` ignorando case, y columnas A1 presentes.
La bandera Scheduling ya estaba habilitada al comenzar este pedido. No se modificó `.env`.

Usuario 10, Demo Admision Local: posee `appointment.mvp.access`, `appointment.view`,
`appointment.create`, `appointment.responsible.assign`. Usuario 11, Demo Comercial Local:
no posee MVP/access/view/create. Ningún rol recibe permisos automáticamente por este código.

Se reprodujo POST `/scheduling-mvp/agenda/appointments`, con paciente ficticio,
médico 1, fecha 2026-10-09, hora 10:20, duración 20, sede null y responsable null:

| Servicio | Resultado | Causa comprobada |
|---|---|---|
| 1 | HTTP 422 | Dos doctor_services activos: 5 (100.00) y 7 (150.00), para el mismo médico/servicio |
| 3 | HTTP 201 | Una asignación activa: doctor_service 6; snapshot 150.00 |

Mensaje 422: «Este médico tiene más de una asignación activa para el servicio seleccionado.
Deje solo una antes de agendar.» No se escogió una tarifa arbitraria ni se alteró el catálogo.
El selector muestra una sola opción deshabilitada para la asignación duplicada y explica el motivo.
Cuando queda un único servicio elegible lo selecciona automáticamente.

La reproducción HTTP interna mantuvo auth, permisos, FormRequest, servicio y transacción;
solo excluyó CSRF al no usar navegador. Se verificó persistencia y presencia en feed HTTP 200
y luego se revirtió la transacción de prueba. Esto identifica un bloqueo reproducible,
no demuestra cuál era cada selección previa realizada por Rodrigo.

También se completó el alta por navegador con CSRF normal: paciente ficticio 4816,
documento `PILOT-20261005-UI-639086`, PRUEBA PILOTO / SMOKE / LOCAL; cita 23,
médico 1, servicio 3, 2026-10-09 10:20, 20 minutos. Tras refrescar, el formulario mostró
el mismo paciente/HCE, servicio, precio 150.00, pago PENDIENTE y estado PROGRAMADO.
Fixture reservado para comprobación visual; no se usaron pacientes reales como fixtures.

## P1: reprogramación

PATCH `/scheduling-mvp/agenda/appointments/{id}/reschedule` acepta fecha/hora destino y
expected_fecha_cita/expected_hora_cita del origen mostrado. Compara ese origen bajo lock:
una confirmación sobre una posición antigua devuelve 409 y no pisa otra reprogramación.
Requiere auth, MVP_ACCESS, VIEW y RESCHEDULE. IDs privados ajenos e inexistentes devuelven
el mismo 404 sin identidad del paciente. La consulta visible se repite dentro de la transacción.

El servicio bloquea primero la fila Doctor y después Appointment, vuelve a validar médico activo,
horario real, sede, cadencia de inicio, duración íntegra y ocupación global entre sedes.
La propia cita queda excluida de ocupación. Ese mismo bloqueo Doctor serializa creación normal,
adicional y reprogramación realizadas mediante estos servicios.

Solo actualiza fecha_cita, hora_cita, updated_by_user_id y el timestamp automático de Eloquent.
No elimina/recrea, no recalcula precio, no toca paciente/servicio/propietario/responsable/pagos/lifecycle.
Solo permite PROGRAMADO/CONFIRMADO con duración definida. Una cita legacy sin duración requiere
revisión humana; el piloto no inventa ni persiste una duración para ella.

Semana usa drag & drop de citas, con confirmación origen/destino antes de enviar PATCH.
Los hits transparentes de disponibilidad no bloquean el drag; backend decide el conflicto.
Día usa su tabla custom y un formulario de fecha/hora al seleccionar la cita.
Mes conserva su arquitectura de resumen y abre Día para operar la cita, sin reescribir Agenda.
Cancelar no envía request; error 409/422/red revierte el drag al origen.

## P2: adicional manual

POST `/scheduling-mvp/agenda/additional-appointments` es un endpoint separado, protegido por
MVP_ACCESS, VIEW y CREATE_ADDITIONAL. Reutiliza CreateAppointmentService y el snapshot
del doctor_service, responsable autorizado, número único y tarifa estándar existente.

El backend impone PROGRAMADO / CONFIRMADA / ADICIONAL; pago PENDIENTE,
total_pagado 0 y saldo_pendiente = precio_programado. CONFIRMADA de agenda no implica pago.
El endpoint normal continúa creando LEGADO / tipo null y no acepta spoofing de ADICIONAL.

Solo permite un inicio/cadencia dentro del horario real; puede ignorar ocupación regular.
No consume slot regular, permanece visible a otros lectores autorizados y usa etiqueta ADICIONAL
y color violeta. Día mantiene la subfila adicional y el slot regular libre cuando corresponde.
El botón prepara un nuevo paciente/servicio, sin copiar silenciosamente al paciente de una cita existente.

## P3: telemetría y mapa de clics

Migration nueva: `2026_10_05_120000_create_agenda_click_events_table`.
No modifica tablas clínicas ni financieras. No hay FK a usuario/paciente.

Campos: event_uuid anónimo por clic (unique para retries), screen fijo agenda, view_mode,
element de lista cerrada, x/y normalizados, dimensiones viewport, rol controlado y recorded_at.
El tiempo se registra en servidor UTC; filtros del visor corresponden a America/Lima
(o scheduling.operational_timezone). Índices fecha/vista y elemento/fecha.

POST click-events requiere auth, MVP_ACCESS, VIEW y límite de 60 batches/minuto.
Batch máximo 20; validación rechaza campos adicionales en raíz y en cada evento.
No acepta DNI, documentos, HCE, nombres, teléfonos, precios, inputs, texto libre, URL libre,
user_id, patient_id ni rol/timestamp proporcionados por cliente.
El frontend solo lee clases/IDs controlados para clasificarlos: nunca lee value ni textContent.
Cola acotada a 100, flush cada 5 segundos/al ocultar página, fetch asíncrono best effort;
errores y ausencia de tabla no controlan ni bloquean Agenda.

GET `/scheduling-mvp/agenda/heatmap` y `/heatmap/data` requieren MVP_ACCESS + VIEW_AUDIT.
Visor con fecha (máximo 93 días), vista y zona, agregado SQL a grilla 40x40, canvas local.
No devuelve eventos individuales ni identidad de usuario/paciente. El mapa es una densidad
de posiciones de viewport, no una captura/reproducción de una pantalla clínica.
Distintos tamaños/layouts pueden necesitar filtros adicionales en una evolución posterior.
Retención operativa y acceso de auditoría deben acordarse para producción.

## P4 y P5: estabilización comprobada

Pacientes: flujo operativo existente reutilizado, no nuevo CRUD. Regla DNI nuevo = 8 dígitos;
conserva DNI histórico inválido cuando no se cambia. Unique numero_identidad existe en migration
y en MySQL local; no se encontraron grupos duplicados en esa base. HCE se persiste en backend.
Se verificó alta ficticia, selección desde Agenda y relación Patient → Appointment.
Tests cubren búsqueda, duplicados, alta, edición, HCE y privacidad de pendientes.

Horarios: workspace abre en navegador, filtros médico/sede/mes, modal con Aplicar horario
y referencia interna oculta. Los tests JS cubren agrupación LUN…DOM, quitar fecha y quitar weekday,
selección/patrón, confirmación y conservación de filtros. No se modificó el workspace existente.
Smoke local transaccional creó, editó e inactivó un horario ficticio 2050-01-10 mediante rutas normales:
todos HTTP 200/code 1; Appointment asociado conservó íntegramente sus atributos. Transacción revertida.
Se añadió regresión explícita: una reserva PENDIENTE_CONFIRMACION sobrevive a edición e inactivación
sin pérdida ni cancelación automática. La bandeja/contingencia persistente y notificación siguen en A3.

## P6: DNI/RUC pendiente de proveedor

Reutilizar ReniecProviderInterface::consultar(string): ?ReniecPersonData y ReniecService.
Punto UI: AgendaReniecLookupController; solo DNI nuevo, consulta opcional, sin persistencia automática.
Agenda limita connectTimeout/timeout a 5 segundos; fallos/no configuración devuelven unavailable
y conservan registro manual. Callers legacy que no proporcionan timeout mantienen el comportamiento
heredado; no se hicieron requests reales en esta tarea.

Configuración actual (nombres, nunca valores de credenciales):

- RENIEC_PROVIDER = aqpfact: AQPFACT_URL_DNI, AQPFACT_TOKEN; GET base/{dni}, Bearer.
  Respuesta success/data: nombres, apellido_paterno, apellido_materno, numero,
  fecha_nacimiento d/m/Y, sexo VARON/MUJER, estado_civil, direccion.
- RENIEC_PROVIDER = apisperu: APISPERU_DNI_URL, APISPERU_DNI_TOKEN; GET endpoint?numero=dni, Bearer.
  Respuesta plana: nombres, apellidoPaterno, apellidoMaterno, numeroDocumento.
  Nacimiento/género/civil/dirección quedan null y se completan manualmente.
- RUC: SunatService heredado usa AQPFACT_URL_RUC + AQPFACT_TOKEN, GET base/{ruc}.
  No tiene interface de proveedor ni timeout explícito equivalente; no se conectó ni se reescribió.

Rodrigo debe proporcionar: proveedor y documentación oficial, endpoints DNI/RUC de sandbox y producción,
autenticación/headers y entrega segura del token, ejemplos sanitizados de éxito/no encontrado/error,
rate limits/coste/timeout, restricciones de uso y autorización para las consultas reales.
El adapter nuevo solo será necesario si el contrato no coincide; no inventar APIs ni colocar tokens en código.

## P8: manifest y despliegue futuro (NO EJECUTADO)

main local y base productiva declarada: `6552e52521ac59d9c1bf8bc3efd880533a87d11d`.
No se verificó el estado productivo actual. Se consultó únicamente evidencia sanitizada ya guardada
en docs/SCHEMA_PRODUCTIVO_VERIFICADO.md y docs/MVP_4A_SCHEMA_READINESS_APPOINTMENT.md.

PILOT_RELEASE_MANIFEST.json enumera candidatos runtime y migrations, indicando origen HEAD o working tree.
No es un paquete liberado: revisar cada archivo/dependencia contra el commit realmente desplegado,
construir una rama/artefacto limpio y excluir cambios heredados locales. No hacer merge directo de la rama.
Si aparece DoctorController en la base del manifest, es exclusivamente su versión comprometida HEAD;
ninguno de los tres archivos dirty heredados se debe copiar desde working tree.

Dependencias por commits (documentación pura no necesita llegar al runtime):

- b6b6246 + 05c4ba7: auth/roles/capabilities, middleware y rutas.
- 85b4814: schema foundation; b8281c5 + cc30f4e: disponibilidad e integración.
- ba67206 + 437e50c + 0996a6b + f9d9b68: shell, Agenda y workspace Horarios.
- fc34a95 + 7e1e39a + b3020d8 + 5f4cab2: identificación/alta/edición de paciente.
- edb19f0 + 4f40c9b + b645385 + 0b364d1 + 191ae31: contratos DNI, HCE, validación y cola.
- 52e3f50 + 671b953 + f3786f3 + 82307c7: cadencia, readiness schema y alta normal.
- 4b0c3f2 + b82292d: selección de horas/fechas y agrupación weekdays.
- 1fbf7ba ANTES de ed5efc4 y 1bf9785: schema A1 antes de consumidores A1/A2.
- Cambios actuales piloto: P0 UX, P1/P2, P3 + nueva migration + tests.

Migrations incrementales, SOLO si realmente pendientes después de inspeccionar migrations/DDL:

1. 2026_09_29_120000_create_sites_table
2. 2026_09_29_120100_add_scheduling_foundation_to_appointments_table
3. 2026_09_29_120200_add_site_to_doctor_schedules_table
4. 2026_10_02_000000_reconcile_appointment_status_enum
5. 2026_10_05_000000_add_agenda_lifecycle_to_appointments_table
6. 2026_10_05_120000_create_agenda_click_events_table

La evidencia productiva previa no demuestra que estos seis cambios estén aplicados.
La migration de sites debe preceder a ambas FK. A1 debe preceder a todo lector A2, incluso
con Scheduling apagado, porque A2 también protege lectores legacy. No desplegar primero
código consumidor contra schema viejo. No rerun de migrations históricas appointments/payments:
sus correcciones de bootstrap no reparan por sí solas drift del schema vivo.
Payments heredada sin entidad_origen/destino sigue fuera del alcance.

Configuración: ninguna env nueva del piloto. SCHEDULING_MVP_ENABLED=true solo después de schema,
catálogo, permisos y UAT; default false. SCHEDULING_OPERATIONAL_TIMEZONE=America/Lima opcional existente.
No hace falta proveedor DNI para alta manual. APP_DEBUG debe permanecer false en producción.

Permisos de cada operador piloto (asignación explícita por operador autorizado):

| Función | Permisos |
|---|---|
| Abrir/ver/agendar normal | appointment.mvp.access, appointment.view, appointment.create |
| Reprogramar | anteriores de lectura + appointment.reschedule |
| Adicional | anteriores de lectura + appointment.additional.create |
| Asignar comercial | appointment.responsible.assign |
| Visor mapa | appointment.mvp.access + appointment.audit.view (no para comerciales normales) |
| Pacientes/Horarios | middleware de roles existente; auditar matriz efectiva para cada cuenta |

No hay seeder ni grant automático. Crear los nombres de Permission que falten y asignarlos
exclusivamente a cuentas/roles aprobados, guard web; después `php artisan permission:cache-reset`.
COMERCIAL requiere una auditoría específica antes de activar A3, tanto local como productiva.

Assets piloto se sirven directamente desde public/js y public/css; no requieren npm build.
No actualizar ni reconstruir FullCalendar ni dependencies como parte de este cambio.
Si vendor ya coincide con composer.lock, no es necesario reinstalar. Si no:
`composer install --no-dev --prefer-dist --optimize-autoloader` en el artefacto limpio, nunca composer update.

Orden futuro mínimo, tras aprobación y backup/restore comprobado:

1. Confirmar commit/DDL/permisos del destino, schema drift, motor InnoDB y catálogo único activo.
2. Backup consistente BD completa y artefacto vigente; restauración verificada en copia aislada.
3. Ventana controlada sin escrituras para ALTER TABLE; feature flag off.
4. Aplicar SOLO las migrations pendientes de la lista en orden, con archivos --path explícitos.
   En producción el operador autorizado agregaría --force; no usar migrate indiscriminado.
5. Desplegar artefacto revisado, sin .env local, .env.testing, fixtures, archivos de revisión ni dirty heredados.
6. `php artisan config:clear`, `php artisan view:clear`; `route:clear` solo si estaba cacheado;
   `permission:cache-reset` después de la asignación aprobada. Reconstruir config cache según hosting vigente.
7. Abrir workspace/crear horario ficticio y cita ficticia, reprogramar, cancelar drag, probar conflicto,
   additional + regular, privacidad hidden=missing, paciente/HCE, clics y visor con roles separados.
8. Activar grupo piloto y bandera únicamente tras validar ese smoke, antes de pacientes reales.

Rollback funcional: retirar permisos específicos/revertir artefacto/flag off. NO borrar pacientes,
citas adicionales ni reservas para volver atrás. Conservar columnas A1 y tabla de telemetría.
Flag off no desactiva los lectores legacy A2: para volver a código anterior hay que restaurar
el artefacto compatible. Retirar código específico piloto hacia HEAD 1bf conserva lectura/privacidad A2.
No ejecutar down de A1 ni reducir enum operativo si ya hay datos nuevos.

## Riesgos y límites pendientes

- Catálogo local 5/7 corregido el 06/10: 5 INACTIVO, 7 ACTIVO. Producción requiere inspección y corrección de datos autorizada propia; el commit no modifica esa BD.
- No hay sedes cargadas localmente: flujo legacy null probado; sedes reales cubiertas por tests, catálogo operativo pendiente.
- La futura contingencia A3 no se implementó. Pendientes afectadas se conservan y no se cancelan; necesitan seguimiento humano.
- Escrituras legacy de citas/horarios no comparten necesariamente el bloqueo Doctor de los nuevos servicios.
  La garantía de serialización descrita se limita a creación/reprogramación/adicional por estos servicios.
  No se afirma un bloqueo universal ni se inventa una regla sobre reservas A3.
- Se observó error de theme legacy selectpicker en consola; no impidió el alta validada. No se refactorizó el theme.
- Cambios/actualizaciones sin duración definida reciben 422; regular ocupado/fuera de horario recibe 409.
- No hay límite nuevo de adicionales ni idempotencia de intención de booking. Número de cita sí es único.
- No hay réplica mensual, OCR, adelantos nuevos, reconsultas nuevas ni historial longitudinal completo.
- Datos de telemetría best effort: fallos de red pueden perder clics, nunca la operación clínica.
- No desplegar sin reconstruir manifiesto contra el schema y commit realmente vivos.

## Evidencia de tests y activación local

| Comando ejecutado | Resultado final |
|---|---|
| php artisan test --env=testing tests/Feature/Scheduling | 277 passed, 0 failed, 1 skipped |
| php artisan test --env=testing tests/Feature/Patients | 65 passed, 0 failed |
| php artisan test --env=testing tests/Feature/Security | 45 passed, 0 failed |
| php artisan test --env=testing --filter="AppointmentReaderPrivacyTest\|PendingPatientChartPrivacyTest\|AppointmentSalesPrivacyTest" | 20 passed, 0 failed |
| php artisan test --env=testing tests/Unit/Support/Scheduling/AppointmentAgendaLifecycleTest.php | 6 passed, 0 failed |
| php artisan test --env=testing tests/Feature/Baseline/CashierSalesAndPaymentsSmokeTest.php | 1 passed, 0 failed |
| node --test tests/JavaScript/*.test.js | 144 passed, 0 failed |

Scheduling incluye PilotAppointmentOperationsTest (11 passed), AgendaHeatmapTest (8 passed),
regresión de selector ambiguo y reserva pendiente conservada tras inactivar horario.
JS incluye cuatro tests de operaciones de cita, tres de telemetría, dos de canvas heatmap
y la regresión de adicional + slot regular en Día, además de suites existentes de Agenda/Horarios/Pacientes.
No sumar los filtros/específicos a la suite completa como tests independientes: hay solapamiento.

La omisión única es rollback nativo A1 con SQLite local <3.35 (3.33); DDL MySQL se compila
separadamente sin abrir conexión. Los tests Laravel usan SQLite :memory:, no pacientes reales.
No se implementaron pruebas de estrés multi-process contra MySQL: el bloqueo Doctor se revisó
en código y se probaron conflictos/revalidación en suite. Esa limitación debe mantenerse explícita.

git diff --check global: solo DoctorController.php:139 trailing whitespace y :138 blank line EOF,
ambos heredados/protegidos. El diff del piloto y los archivos nuevos pasan whitespace/sintaxis.
Ningún archivo está staged. Los SHA256 de los tres controllers protegidos coinciden con el inicio.

Activación local autorizada y completada el 2026-10-05: se verificaron local / 127.0.0.1:3308 /
ERPCEOSALUD case-insensitive, y se ejecutó únicamente Artisan migrate con --path de heatmap.
La única fila nueva de migrations es ID 35, batch 8, 2026_10_05_120000_create_agenda_click_events_table,
estado Ran. El comando CLI exacto repetido confirmó Nothing to migrate; ninguna otra migration aplicada.
A1 se conserva; no rollback. La tabla agenda_click_events existe.

Usuario 10 verificado como Demo Admision Local, rol ADMISION. Antes tenía seis capabilities efectivas:
GESTION_CITAS, GESTION_PACIENTE, appointment.mvp.access, appointment.view,
appointment.create, appointment.responsible.assign. Después conserva esas seis y agrega solamente
appointment.reschedule, appointment.additional.create, appointment.audit.view.
Sus grants directos son las siete appointment.*; las dos GESTION_* provienen del rol existente.
Se compararon todos los model_has_roles y los grants de otros usuarios antes/después: idénticos.
No se cambiaron contraseñas, roles ni permisos globales. .env no fue modificado.

## Cierre del smoke visual LOCAL: P1–P6

| Paso | Evidencia real |
|---|---|
| P1 normal | UI autenticada creó Appointment 25, paciente 4816, doctor 1, service 3, 2026-10-09 10:40; persistido y visible. POST normal adicional de comprobación transaccional dio HTTP 201 y fue revertido. |
| P2 reprogramar | Confirmación nativa origen/destino; mismo 25 pasó a 11:00 desde Día y a 2026-10-11 11:00 por drag Semana. Patient/service/doctor/owner, snapshot 150, pagado 0, saldo 150, PENDIENTE y lifecycle conservados. Regresó por drag confirmado al viernes 09 a 11:00 para revisión. |
| P2 rechazo | Destino 09/10 10:20 ocupado por fixture 23 dio HTTP 409 y todos los atributos permanecieron iguales. Destino fuera de horario 17:00 también 409. Drag real hacia jueves 08/10 sin horario devolvió mensaje de intervalo inválido y el bloque volvió visualmente al viernes. |
| P3 adicional | UI creó 26 junto a 25 a las 11:00: PROGRAMADO / CONFIRMADA / ADICIONAL / PENDIENTE, precio 150, pagado 0, saldo 150; violeta y etiqueta ADICIONAL. No modificó 25. Al mover 25 al domingo, viernes 11:00 fue libre con solo 26; consumingRegularSlot(26) = false. |
| P4 mapa | Tabla poblada desde clicks reales; visor protegido abre y dibuja densidad. Corte registrado: 34 eventos, Día 30, Semana 4; filtro patient_search 6. Filtros visuales de vista/zona y rango local probados. |
| P5 pacientes | Nuevo 4817 mediante modal, documento PASAPORTE sintético; búsqueda existente, edición del mismo ID/nombre, HCE estable; guardar documento duplicado mostró error unique y count = 1. Guardar y continuar creó cita 27; selección desde Agenda verificada. |
| P6 horarios | Selección real de nueve fechas, siete grupos LUN/MAR/MIE/JUE/VIE/SAB/DOM; quitar 27/10 individual y grupo de lunes mantuvo otras fechas. Se conservaron solo 20/10 y 21/10 para crear 19/20, 09:00–10:00, 20 min. +Horario single mostró Aplicar horario y sus tres alcances. |
| P6 conservación | UI agendó 28 en horario 19; edición a 09:20–10:20 mostró alerta de 1 cita afectada. Confirmar editó solo horario; inactivar dejó 19 INACTIVO. SHA256 de TODOS los atributos de 28 fue idéntico antes y después: 7f2cbe53679378806c5a1e8c18b1284366560c727ced9af84f44c4ffc4f7076c. |

La confirmación nativa apareció con el mensaje exacto de origen/destino; el adaptador IAB no la devuelve
con getJsDialog, pero la operación se aceptó con Return. Se verificó DB y posición después de cada cambio.
No se reemplazó confirmación por escrituras directas ni se desactivó auth/CSRF del navegador.
Las comprobaciones HTTP internas mantienen auth/permissions/FormRequests y omiten únicamente CSRF.

Se corrigieron dos defectos visibles del piloto durante este cierre:
- heatmap.blade usaba js_data, pero layouts.app renderiza script_data: el visor no cargaba su JS.
  Corrección mínima de sección, con assertSee del asset en AgendaHeatmapTest y revalidación visual.
- Iniciar una adicional dejaba el HCE del paciente anterior en el indicador de búsqueda.
  Se pinta inmediatamente la identidad vacía; validado visualmente como — / Sin paciente seleccionado.
No se cambiaron precios, pagos, reglas A3 ni los tres controllers heredados.

El fallo del endpoint de telemetría se comprobó con un batch local inválido (DNI extra): HTTP 422,
sin persistencia, y Agenda continúa operable. Rechazo/pérdida de endpoint en frontend también pasa
el test de cola JS; la ausencia de schema devuelve 503 en test aislado y no rompe GET Agenda.
No se retiró la tabla ni se interrumpió el servidor local para simular una caída durante este smoke.

Privacidad persistida: columnas exactamente id, event_uuid, screen, view_mode, element, x, y,
viewport_width, viewport_height, actor_role, recorded_at. Ejemplo anónimo real: screen=agenda,
view_mode=dia, element=agenda.patient_search, x=0.392188, y=0.593056, viewport=1280x720,
actor_role=ADMISION, recorded_at=2026-10-05 22:00:44 UTC. No contiene DNI/HCE/nombres/teléfono,
valores de inputs, datos médicos, texto de paciente, patient_id/appointment_id/user_id.
El visor solo devuelve celdas agregadas. No transmite datos a un proveedor externo.

Los nueve appointments previos ajenos al fixture (4,5,6,9,10,12,13,14,21) siguen presentes.
Durante las operaciones de Horarios se verificaron hashes idénticos de las citas ajenas y schedules 1–17.
Solo se operaron pacientes ficticios 4816/4817 y schedules nuevos 19/20; no se borraron fixtures.

Evidencias PNG fuera del repositorio, carpeta de visualizaciones de esta conversación:
pilot-regular-additional.png, pilot-rescheduled-sunday.png, pilot-drag-reverted.png,
pilot-heatmap.png, pilot-weekday-groups.png. Agenda/heatmap/Horarios quedan abiertos para Rodrigo.

## Auditoría inicial READ-ONLY del 05/10: doctor_services 5 y 7

Ambos corresponden a doctor_id=1, JULIO QUIROZ; service_id=1, CONSULTA TRAUMATOLOGIA ACTIVO.

| ID | Primera consulta | Reconsulta | Días | Estado | created_at = updated_at |
|---|---|---|---|---|---|
| 5 | 100.00 | 80.00 | 15 | ACTIVO | 2026-08-12 14:48:44 |
| 7 | 150.00 | 120.00 | 15 | ACTIVO | 2026-08-12 14:49:26 |

SHOW INDEX local solo devuelve PRIMARY(id), sin unicidad doctor/service. La migration original
2026_08_11_140132_create_doctor_services_table.php tampoco añade unicidad ni FK.
DoctorServiceController::store (también en HEAD, líneas 36–62) valida importes/días y crea otro
DoctorService explícito; no impide duplicar el par. El código permite esta situación, pero timestamps
separados 42 segundos no demuestran quién los creó ni la intención. No hay evidencia de causa exacta.

No existe columna doctor_service_id en ninguna tabla local, ni FK entrante a doctor_services.
appointments.service_id referencia el servicio, no esa asignación: legacy AppointmentController::store
resuelve DoctorService -> Service y persiste service.id. No puede atribuirse de forma inequívoca una cita
al ID 5 o 7. Historial del par: appointments 4/5/9 snapshot 100; appointment 10 snapshot 150.
Es evidencia de uso histórico de ambos importes, no prueba de qué fila doctor_services se eligió.

Voucher_items de tipo cita: item 2 -> appointment 5/voucher 2/100; item 7 -> appointment 9/voucher 7/100;
item 8 -> appointment 10/voucher 8/150. Vouchers 2/7/8 son TICKET con totales 100/100/150.
No hay voucher_item directo DoctorService para 5/7; todos los 8 items locales son tipo cita.
No existe tabla sales: el módulo Sales usa vouchers/voucher_items/payments/cash movements.
La inspección de columnas/FK y código no encontró persistencia directa de doctor_service_id en esas tablas.

Riesgo financiero detectado en el diagnóstico del 05/10 (corregido en este RC): Sales::buscarCitas (Sales.php:429–450) une por
appointments.doctor_id/service_id sin filtrar estado de doctor_services. Con datos locales, cuatro citas
producen ocho matches: cada una aparece con precios actuales 100 y 150. Inactivar una fila resolvería
el unique-active de Agenda, pero ese join seguiría viendo ambas. No modificar aquí cálculos financieros.
calculatedPrice mantiene el bug de IDs documentado y queda fuera del alcance.

Impacto inicial del 05/10: JULIO QUIROZ / CONSULTA TRAUMATOLOGIA quedaba deshabilitado y backend 422. El 06/10 ambos servicios quedaron habilitados a 150 tras la corrección autorizada.
Un piloto que incluya consulta traumatológica de ese médico está bloqueado. No hay una definición
aprobada del mix operativo: no afirmar que PIE DIABETICO sustituye a la consulta de traumatología.
PIE DIABETICO se usó exclusivamente en fixtures, porque doctor_service 6 es único y positivo.

Opciones seguras, sujetas a decisión/autorización separada:
1. Si son la misma prestación: negocio aprueba tarifa vigente y fecha efectiva; conservar ambas filas/historia,
   inactivar solo la no vigente y prevenir futuras altas duplicadas. Revisar aparte Sales/legacy para resolver
   el join y respetar snapshots, sin recalcular citas/comprobantes anteriores. Inactivación sola no basta.
2. Si representan prestaciones/planes diferentes: definir catálogo/prestaciones distintas con nombres y
   servicios inequívocos o versionado explícito de tarifas para nuevas altas. No reasignar historia por precio
   inferido; mantener snapshots/vouchers y documentar trazabilidad.
3. Limitar expresamente el primer piloto a un catálogo validado y posponer ese par hasta resolverlo.
   Solo es aceptable si negocio confirma que las operadoras no necesitan dicha consulta durante el piloto.
No elegir 100 o 150 como correcto a partir de estos datos. No se borró/actualizó/fusionó ningún registro.

## Cierre de catálogo y release candidate del 06/10/2026

Autorización de negocio: Quiroz Trauma/Pie consulta150 y reevaluación120; generales CEO Trauma100/80.
No se incorporó una nueva matriz ni se infirió que todos los médicos distintos de Quiroz sean generales.
Se verificó LOCAL, host127.0.0.1, puerto3308, ERPCEOSALUD case-insensitive y MySQL DATABASE().
Motor local reportado: 10.4.32-MariaDB. Producción no fue contactada.

Corrección de datos LOCAL, dentro de transacción y con validación de filas exactas:
- Doctor1 JULIO QUIROZ / service1 CONSULTA TRAUMATOLOGIA, únicos activos iniciales[5,7].
- ID5: estado ACTIVO -> INACTIVO; updated_at 2026-08-12 14:48:44 -> 2026-10-06 12:56:37 (valor almacenado).
  Primera100, reconsulta80, días15, doctor/service/created_at sin cambios.
- ID7 queda ACTIVO; primera150, reconsulta120, días15, timestamps idénticos.
- ID6 Pie Diabético ya era150/120 ACTIVO; no modificado.
No se borraron asignaciones ni se recalcularon citas/comprobantes. La corrección de datos no viaja en Git.

Abstracción mínima ActiveDoctorServiceResolver centraliza query vigente, par único y selección por ID.
Requiere asignación/doctor/servicio activos; cero filas y varios activos generan error explícito.
Agenda utiliza la misma resolución para opciones y escritura. El listado conserva un motivo explícito
cuando hay duplicados, sin escoger precio. API serviceBydoctor/calculatedPrice y writers legacy
admissionist Appointment/Schedule validan mediante la resolución; IDs inactivos/doctor distinto fallan422.
calculatedPrice mantiene su lógica económica/reconsulta/tarifas y el bug heredado de IDs fuera del alcance;
no depende de visibilidad ni se reescribió la matriz económica.

Auditoría de lectores con rg sobre app/resources/routes:
- AgendaBoardController / CreateAppointmentService: resolución común.
- API appointment serviceBydoctor / calculatedPrice: resolución común.
- Sales búsquedas de servicios: exige médico, activos únicos; dup muestra error de catálogo antes de paginar.
  Selección de servicio vuelve a resolver el par y precio, rechazando resultado inactivo/ambiguo.
  Cambiar médico refresca resultados; productos siguen sin requerir médico.
- Sales búsqueda de cita existente: elimina join doctor_services y usa precio_programado persistido,
  sin sumar nuevamente tarifa adicional. Carrito de cita toma ese snapshot desde backend.
  No oculta citas por inactivación del catálogo ni transforma historia de100 a150; no reprocesa ventas.
- Admissionist alta/edición legacy: valida asignación activa única y médico consistente, sin recalcular snapshots.
- DoctorServiceController protegido: index es lista administrativa de filas ACTIVO; no escoge precio para operación.
  Store queda intacto y la validación común del modelo cubre su create. Delete solo inactiva.
- Relaciones Doctor::doctorServices/services y Service::doctorServices/doctors mantienen catálogo/historia;
  no se encontraron consumidores runtime de esas relaciones como selector de precio. No convertirlas en
  scopes globales que oculten historia.
No quedan joins runtime de doctor_services para elegir arbitrariamente un precio.

Prevención: DoctorService::save valida nuevas ACTIVAS, reactivaciones y cambios de par dentro de
transacción, bloqueando Doctor y leyendo conflictos con lockForUpdate antes de insertar/actualizar.
La lectura bloqueante evita un snapshot obsoleto de REPEATABLE READ. INACTIVOS históricos se conservan.
Endpoint maestro devuelve422/service_id ante duplicado; JS muestra ese mensaje y rehabilita el botón.
No se cambió ninguno de los tres controllers protegidos. No se añadió UNIQUE ni migration de catálogo.
SQL directo / Query Builder / pivot attach no pasan por save; no existe garantía universal de constraint.
Si se autorizan futuros writers, deberán respetar el modelo o la misma validación. No se ejecutó estrés
multiproceso MySQL. Una constraint generada para activos requiere auditar duplicados/motor del destino;
no se fuerza aquí una constraint parcial.

Regresión real local mediante endpoint normal, auth/permissions/FormRequest/service intactos (CSRF
omitido solo por ser HTTP interno): Appointment30, Patient4816 ficticio, doctor1/service1, 09/10 10:40,
20min, HTTP201, snapshot150.00, pagoPENDIENTE. Tabla de opciones: Trauma disponible=true/precio150,
Pie disponible=true/precio150. Se verificaron identidad y ausencia de voucher_item; se borró exclusivamente
Appointment30 dentro de la misma transacción de smoke y quedó ausente. Fixtures anteriores intactos.
Hashes completos de tablas antes/después de corrección+smoke coinciden:
- appointments: b1f0e0492a2a4ac68aeaad29dd7df77672f7d74147304817dc5bcffd2fa975a0
- patients: 7851aa828fa92233d9c98727af657a31a08f76c68b4bd32f182e34dfc90bb176
- vouchers: 6304a93f5e3928fc0d39264feadc157369783879f4810c844ec7d7c5bb9c091b
- voucher_items: 151f1ae7e9154fed48e1094b1e2ad281d5b48f1efccff3c494bc3d4e3c51d88a

Tests RC: Scheduling277passed/1skipped; Patients65; Security45; CashierSmoke1; PatientAppointmentSmoke2;
Catalog8; Sales12 (Pricing7+Privacy5); filtroA2=20; Lifecycle6; JavaScript144. Cero fallos.
La única omisión sigue siendo rollback nativoSQLite3.33. Las pruebas usan SQLite :memory: aislado.
Heatmap sin cambio funcional en este cierre: migration2026_10_05_120000 incluida;
campos cerrados sin DNI/HCE/nombre/teléfono/patient_id/appointment_id/user_id/texto libre/datos médicos.

Reproducción futura de datos en deployment, SIN EJECUTAR AHORA:
1. Operador autorizado inspecciona catálogo/identidad del doctor y servicios del destino; no asumir IDs5/7
   locales ni clasificar generales por exclusión de Quiroz. Guardar export de filas/citas snapshots antes.
2. Confirmar explícitamente vigente Quiroz Trauma150/120 y Pie150/120 según regla aprobada; si hay drift
   no esperado o más asignaciones, detenerse. Backup/restore y ventana controlada de catálogo.
3. Transacción: bloquear Doctor y asignaciones, validar precios/estado esperados; inactivar únicamente
   la asignación antigua100/80 identificada de Quiroz Trauma, conservar precios/created_at; mantener
  150/120 activa. Guardar evidencia before/after y responsable/fecha del cambio fuera de datos clínicos.
4. Comparar snapshots históricos/comprobantes sin diferencias, exactamente un activo por par,
   opciones/API/Sales/nuevo booking ficticio. Activar piloto solo después de esas verificaciones.
5. Reversión de esa corrección, si se autoriza, conserva registros y precios: no delete ni recálculo masivo.
   Reactivar100/80 coexistiendo con150/120 quedaría rechazado; cualquier reversión debe mantener unicidad.

El manifest identifica el commit que lo contiene mediante git log, evitando un SHA autorreferente.
El manifest guarda git_blob para verificar el contenido versionado ante conversión LF/CRLF; sha256 registra los bytes del working tree al armar el RC. Incluye las dependencias ya comprometidas del padre.
No contiene .env ni datos de pacientes reales. El único commit aprobado no equivale a permiso de despliegue.

## Fixtures retenidos y limpieza selectiva posterior

No borrar hasta finalizar la revisión visual. Médico 1 / service 3 / precio snapshot 150 / pago PENDIENTE,
pagado 0 y saldo 150 en todas las citas. No se generaron vouchers de los pacientes ficticios.

| Paciente | Documento ficticio | Nombre actual | Appointments |
|---|---|---|---|
| 4816 | PILOT-20261005-UI-639086 | PRUEBA PILOTO / SMOKE / LOCAL | 23,25,26 |
| 4817 | PILOT-20261005-UI-NEW-74219 | PRUEBA PILOTO NUEVO EDITADO / SMOKE / LOCAL | 27,28 |

23: 09/10 10:20 LEGADO; 25: 09/10 11:00 LEGADO; 26: 09/10 11:00 CONFIRMADA/ADICIONAL;
27: 09/10 11:40 LEGADO; 28: 20/10 09:00 LEGADO (retenida fuera del horario inactivado).
Horarios nuevos: 19 (20/10 09:20–10:20 INACTIVO), 20 (21/10 09:00–10:00 ACTIVO), doctor1,
sede null, cadencia20. Los saltos de IDs anteriores provienen de pruebas transaccionales revertidas.

Para revisión: http://127.0.0.1:8000/scheduling-mvp/agenda → JULIO QUIROZ, Día 2026-10-09,
11:00. Heatmap: http://127.0.0.1:8000/scheduling-mvp/agenda/heatmap, fechas 2026-10-05,
Todas vistas/zonas. Horarios: http://127.0.0.1:8000/admissionist/doctor-schedule, octubre, médico1;
21/10 conserva bloque activo; 20/10 no tiene bloque activo pero cita28 sigue en Agenda.

Limpieza autorizable posteriormente en Tinker, TODO dentro de transacción y con guardas locales:

```php
$c = DB::connection()->getConfig();
if (app()->environment() !== 'local' || $c['host'] !== '127.0.0.1'
    || (int)$c['port'] !== 3308 || strtolower($c['database']) !== 'erpceosalud'
    || strtolower(DB::selectOne('SELECT DATABASE() AS db')->db) !== 'erpceosalud') {
    throw new RuntimeException('STOP: entorno distinto.');
}
DB::transaction(function () {
    $fixtures = [
        4816 => ['doc'=>'PILOT-20261005-UI-639086','name'=>'PRUEBA PILOTO','ids'=>[23,25,26]],
        4817 => ['doc'=>'PILOT-20261005-UI-NEW-74219','name'=>'PRUEBA PILOTO NUEVO EDITADO','ids'=>[27,28]],
    ];
    $refs = DB::table('information_schema.KEY_COLUMN_USAGE')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->whereIn('REFERENCED_TABLE_NAME', ['patients','appointments'])
        ->get(['TABLE_NAME','COLUMN_NAME','REFERENCED_TABLE_NAME']);
    foreach ($fixtures as $id=>$f) {
        $p=App\Models\Patient::lockForUpdate()->findOrFail($id);
        if ($p->numero_identidad!==$f['doc'] || $p->nombre!==$f['name']
            || $p->apellido_paterno!=='SMOKE' || $p->apellido_materno!=='LOCAL' || (int)$p->user_id!==10) {
            throw new RuntimeException('STOP: identidad distinta.');
        }
        $ids=$p->appointments()->lockForUpdate()->orderBy('id')->pluck('id')->map(fn($v)=>(int)$v)->all();
        if ($ids!==$f['ids']) throw new RuntimeException('STOP: citas adicionales/distintas.');
        if (DB::table('responsibles')->where('patient_id',$id)->exists()
            || DB::table('vouchers')->where(fn($q)=>$q->where('patient_id',$id)->orWhere('paga_patient_id',$id))->exists()
            || DB::table('voucher_items')->whereIn('item_type',['cita',App\Models\Appointment::class])->whereIn('item_id',$ids)->exists()) {
            throw new RuntimeException('STOP: datos adicionales.');
        }
        foreach ($refs as $r) {
            if ($r->TABLE_NAME==='appointments' && $r->COLUMN_NAME==='patient_id') continue;
            if (DB::table($r->TABLE_NAME)->whereIn($r->COLUMN_NAME,$r->REFERENCED_TABLE_NAME==='patients'?[$id]:$ids)->exists()) {
                throw new RuntimeException('STOP: relación adicional.');
            }
        }
        if (DB::table('appointments')->where('patient_id',$id)->whereIn('id',$ids)->delete()!==count($ids)
            || DB::table('patients')->where('id',$id)->delete()!==1) throw new RuntimeException('STOP: conteo.');
    }
    foreach ([19=>['2026-10-20','09:20:00','10:20:00','INACTIVO'],20=>['2026-10-21','09:00:00','10:00:00','ACTIVO']] as $id=>$f) {
        $s=App\Models\DoctorSchedule::lockForUpdate()->findOrFail($id);
        if ((int)$s->doctor_id!==1 || $s->site_id!==null || (int)$s->duracion_cita!==20
            || [$s->fecha_cita,$s->hora_inicio,$s->hora_fin,$s->estado]!==$f
            || DB::table('appointments')->where('doctor_id',1)->where('fecha_cita',$f[0])->exists()) {
            throw new RuntimeException('STOP: horario distinto o nuevas citas.');
        }
        if (DB::table('doctor_schedules')->where('id',$id)->delete()!==1) throw new RuntimeException('STOP: conteo horario.');
    }
});
```

No ejecutar este script ahora. No limpia usuarios, doctores, services/doctor_services, sedes ni tarifas.
No hace rollback de A1/heatmap. La telemetría anónima no está vinculada a pacientes y se conserva;
su retención/limpieza se decide aparte. No revocar permisos todavía durante revisión; si luego se
revierte el smoke, retirar únicamente los tres grants directos nuevos de user10, preservando sus grants previos/roles.

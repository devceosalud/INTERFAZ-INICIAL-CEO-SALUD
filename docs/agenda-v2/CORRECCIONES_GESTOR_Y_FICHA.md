# Correcciones de Gestionar cita y ficha del paciente — 20/10/2026

## Alcance y estado inicial

Continuación exclusiva de `.worktrees/agenda-v2`, rama `feat/agenda-operativa-flujo-reevaluaciones-v2`, base `5984318fd43ba1eba02b806177c67c732f7a958c`. Los dos textos adjuntos eran idénticos. El checkout estaba limpio salvo el archivo ajeno no seguido `dad}sad}sa`, que se conserva. No se modificó el checkout original ni otros worktrees. Se comprobaron los hashes de sus cinco cambios anteriores.

Esta revisión sustituye los bloqueos generales de la entrega anterior solo para los casos seguros ahora autorizados. No activa penalidad, recotización, corrección de montos/referencias ni reasignación de información clínica/financiera. No hay push, cambios de PR, merge, despliegue o acceso productivo.

## Diagnóstico y solución

1. **Ficha fuera de Agenda.** El gestor anterior solo enlazaba a `patient_url`. Ahora carga el mismo Blade de campos mediante `/patients/{id}/editor?appointment_id=...`; guarda por el mismo `PUT /patients/{id}` y `OperationalPatientMutationController`. No hay iframe, segundo guardado ni editor financiero. `PatientRecordForm` comparte la serialización entre ficha completa y popup.
2. **Selectores duplicados.** `custom.js`/`custom.min.js` ejecutaban `$('select').selectpicker()` al cargar todas las páginas. La ficha había diseñado sus controles como nativos; sus reglas `.patients-field select` se aplicaban al `<select>` dentro del wrapper del plugin, volviéndolo visible y dejando también el botón generado. Se añade un contrato explícito `data-native-select` y el inicializador excluye únicamente esos controles. No se ocultan duplicados con CSS ni se cambia Bootstrap Select de los demás módulos. Se corrigen el grid del documento, teléfono, datos personales y el retorno a Agenda, que antes ocupaba una columna estrecha independiente. Los assets modificados de la ficha se versionan para evitar una mezcla con caché antigua.
3. **Accesos secundarios.** Pasan a `Más opciones`, con botones separados en grid, ajuste móvil y foco visible. Se conservan atención/procedimientos, documentos/historial, datos operativos, cancelación/retiro y modalidad.
4. **Cambio de paciente.** Ya es una escritura real únicamente sin vínculos incompatibles: búsqueda local por documento, comparación, motivo administrativo, motivo de consulta nuevo y confirmación. No se cambia la ficha maestra del paciente anterior. Se archivan sus identificadores/contexto en el JSON del evento existente `PACIENTE_REASIGNADO`, se vacían observaciones y banderas postoperatorio/herida, y se conserva la misma cita/horario/contrato. El motivo anterior no se devuelve en el detalle o feed del nuevo paciente.
5. **Cambio de servicio.** Permite consultar todas las asignaciones únicas, activas y compatibles del profesional, incluso si la cita está bloqueada para escritura. El preview muestra importe/duración actuales y propuestos, disponibilidad y restricciones. El cambio real exige igualdad con el contrato guardado y, si existe, su precio de catálogo; vuelve a consultar catálogo/compatibilidad y disponibilidad dentro de la transacción. Solo cambia `service_id` y autor de actualización; no recotiza, cobra, mueve el horario ni altera snapshots. Se guarda `SERVICIO_CORREGIDO`.
6. **Bloqueos.** Se distinguen permisos, atención/presencia, pagos reales o snapshots positivos, referencias bancarias/historial financiero aun sin atribución actual, comprobantes incluso anulados, documentos incluso versiones borradas, procedimientos/historial, indicaciones, tareas de atención, créditos/devoluciones, contratos especiales y saldos que requieren revisión. Una diferencia de precio o de clase de servicio impide confirmar, pero no impide consultar la propuesta.

## Ficha inline, validación y concurrencia

Cuatro pestañas: Identificación, Contacto, Datos personales e Información adicional. Un solo formulario compartido con documento, nombres/apellidos, teléfonos, correo, nacimiento, género, estado civil, dirección, ocupación, instrucción, canales/medios y responsable. En escritorio se amplía solo esta acción y se compacta su navegación; en móvil hay una columna. El panel de campos tiene scroll interno y Guardar/Cancelar permanecen visibles. `Ver ficha completa` sigue disponible.

Se reutilizan las reglas existentes: unicidad de documento, DNI nuevo de ocho dígitos, conservación de DNI histórico sin cambiar, obligatorios, correo, teléfono, catálogos y menor/responsable. Los errores conservan el borrador, abren la pestaña del campo inválido y enfocan el control. Desmarcar responsable no elimina un registro anterior: se conserva la semántica del mantenimiento y se explica en el formulario.

El guardado anterior no tenía comprobación de versión. `PatientRecordRevision` incorpora los datos maestros editables y el responsable gestionado por el mantenimiento. Ambas interfaces envían su hash; las escrituras inline lo exigen y verifican el contexto paciente/cita bajo bloqueo. Se rechaza con409 una ficha cambiada desde otra sesión. No se cambia `appointment.patient_id` al corregir la ficha. El nombre se recarga en el gestor, Agenda y la ficha completa.

La corrección inline deja `FICHA_PACIENTE_CORREGIDA` con usuario, fecha/hora, campos y hashes anterior/posterior en los eventos de cita; no guarda contenidos clínicos en logs. La ficha completa abierta con contexto de Agenda utiliza también esa auditoría. El mantenimiento independiente conserva logging de metadatos, no se ha creado una tabla global de auditoría de pacientes. Los clientes heredados ajenos a estas dos interfaces pueden omitir versión para conservar compatibilidad: no se afirma que todos los escritores históricos tengan control optimista.

Las ediciones de cita usan `appointment_operations` existente, UUID estable, hash de payload, revisión, confirmación y motivo. Repetir la misma operación no duplica cambios/eventos; otra operación con revisión antigua recibe409. Se serializan profesional/cita antes de revalidar vínculos. Cobros y cargas documentales que hubiesen leído una identidad distinta la vuelven a comprobar antes de escribir; el formulario de pago de Agenda envía además la revisión económica/operativa consultada.

## Permisos efectivos

| Operación | ADMINISTRADOR | ADMISION | COMERCIAL |
|---|---|---|---|
| Consultar/editar ficha inline de cita visible | PatientWriteAccess y acceso efectivo a Agenda | Misma política existente | Misma política existente; no concede UPDATE de citas |
| Cambiar paciente/servicio | canEdit + UPDATE + visibilidad de escritura + ausencia de bloqueos | Igual, si tiene UPDATE | Solo si ya posee UPDATE y visibilidad; no se le concede por este cambio |
| Consultar servicios y restricciones | Cita visible autorizada | Igual | Igual |
| Cambiar modalidad/atención | Reglas y capacidades anteriores | Reglas y capacidades anteriores | No se amplía su permiso |
| Corregir monto/referencia, penalidad o recotizar | No habilitado | No habilitado | No habilitado |
| Documentos | Conserva política documental específica | Conserva restricciones existentes | Conserva carga autorizada; sin privilegios financieros nuevos |

Los usuarios inactivos no pueden operar. Un identificador oculto no revela sus restricciones por el endpoint. CSRF, sesión, capacidades y comprobación backend siguen vigentes; no se depende de ocultar botones.

## Migraciones, conservación y rollback

**Sin migraciones nuevas ni backfill.** Se reutilizan `patients`, `responsibles`, `appointments`, `appointment_events.metadata` y `appointment_operations`. No se borran motivos históricos, archivos, comprobantes, pagos ni procedimientos. La conservación del contexto anterior en una reasignación usa el historial ya existente; no se añade otro mecanismo de archivo. El JSON interno no se publica en el feed/detalle. El contexto de atención sin una operación soportada sigue siendo de solo lectura.

Rollback eventual: revertir código manteniendo las tablas, archivos y eventos. No reasignar automáticamente paciente/servicio al anterior ni ejecutar down para revertir una operación administrativa. El código de la base puede leer el paciente/servicio actuales; los eventos nuevos permanecen aunque su UI anterior no los explique. La liberación y referencia histórica de reprogramaciones continúan como en la entrega previa. Una publicación futura debe desplegar de manera coherente controladores/servicios/rutas, Blade parcial, scripts compartidos, estilos y el inicializador compilado. No ejecutar ese procedimiento sin autorización.

## QA y capturas

Resultados verificables y logs en `evidence/quick-edit/RESULTADOS.json`; inventario en `ARCHIVOS.json`. Galería en [CAPTURAS.md](evidence/quick-edit/CAPTURAS.md). Todos los datos son ficticios; `.env` ausente; MariaDB11.8.9 y PHP se limitan a loopback y esquemas QA separados de navegador/suite. No hay llamadas a producción.

Las capturas originales iniciales de la ficha coincidieron con el fade del preloader y no se usan como evidencia final. Las comparativas `before` se recapturaron en Chrome con los assets cliente originales obtenidos de `git show 5984318` y el HTML compartido equivalente sin opt-out nativo, sobre la BD ficticia actual. Es una reconstrucción de presentación del baseline, no una ejecución íntegra del backend antiguo ni una medición de rendimiento. No se intercambiaron archivos del worktree. `baseline-capture.json` documenta método/archivos; el script se conserva en `tools/qa/browser-agenda-safe-quick-edit-baseline.cjs`. Las capturas `after` corresponden al código actual y esperan que termine el preloader.

Los errores intermedios se conservaron separados de los resultados finales: un selector del runner buscaba el texto del label cuando el select tenía un nombre accesible específico; el fixture de inactividad intentaba mass-assignment de `is_active` y luego esperaba403 en vez del401 de sesión revocada; al ampliar el caso a menores, el payload de la segunda sesión omitía responsable y recibió correctamente422. También se detectó y corrigió una guarda demasiado amplia añadida en esta revisión: economic_source=VOUCHER es normal en una reserva nueva sin ticket ni pago, y por sí sola no acredita un vínculo financiero. La prueba de edición al mismo precio se ajustó al dato real de registro, falló antes de corregir la guarda y pasó después (guard-red/guard-green). Se conservan los bloqueos por vínculos y referencias reales; no se debilitaron reglas contables para pasar las pruebas.

## Prueba manual local

1. Entrar a http://127.0.0.1:8092 con las credenciales ficticias de `storage/app/qa-v2/ACCESO_MANUAL_QA.md`.
2. La cita sin vínculos de `storage/app/qa-v2/quick-edit/fixtures.json` permite probar servicio/paciente. Su fecha es `date`, profesional `doctor_id` y cita `appointment_id`. Ya contiene eventos de la demostración: usar otro servicio al mismo precio y un paciente diferente registrado. Es obligatorio introducir un motivo nuevo al reasignar.
3. La cita de `storage/app/qa-v2/management/fixtures-final.json` conserva dos adelantos. Abre Gestionar cita y comprueba bloqueo por pago/comprobante, consulta de servicios y ficha inline. No modificar importes.
4. Editar ficha: probar DNI duplicado, correo inválido, un menor sin responsable, Cancelar y guardado correcto. Desde otra sesión abrir la misma ficha, guardar y comprobar409 en el borrador anterior.
5. Abrir Más opciones con clic o Enter. Verificar documentos/historial, modalidad y retorno a sus módulos. Reprogramar sigue liberando origen y manteniendo anotación no bloqueante.
6. Comprobar escritorio1366/1440/1920 y móvil390. Para repetir QA se usan fixtures APPEND_FICTIONAL_ONLY y los scripts conservados; nunca reiniciar la BD manual ni borrar sus registros.

El servidor manual permanece disponible. Para detenerlo se utiliza `storage/app/qa-v2/stop-manual-qa.ps1`; `-StopDatabase` detiene también la MariaDB QA verificada conservando sus datos.

## Pendientes y riesgos

Penalidad20%, tarifa exacta/base comercial/contradicción del correo, recotización y corrección de importes/referencias continúan pendientes de aprobación. Las relaciones documentales, clínicas o financieras no se transfieren; se requiere un diseño específico para esos casos. No se ha añadido un audit trail global de pacientes ni actualizado cada API heredada para exigir revisión; los dos formularios actualizados sí la envían. El catálogo no define duración por servicio: se conserva y valida la del horario actual, sin inventar duraciones. El flujo clínico remoto sigue pendiente de negocio.

CI para este trabajo local no ejecutada: no se autoriza publicar. La validación QA no afirma comportamiento en producción ni sustituye revisión visual humana.

## Cierre verificable

Código funcional congelado: `4743278d4b362f03df536106316340f2de7aac37` (30 archivos de aplicación/QA). SQLite:1151 pruebas,10182 aserciones; MariaDB ficticia:1166 pruebas,10319 aserciones, incluidos15 escenarios de concurrencia; JavaScript:333 pruebas. Sin fallos, errores ni omitidas en las suites finales. Sintaxis de25 archivos PHP/JS/CJS aprobada.

Chrome155:61 recorridos aprobados en esta continuación (16 nuevos de edición inline/segura,13 de gestión/reprogramación,3 enfocados,19 de reevaluación/MFR/permisos y10 de documentos/privacidad). Los16 nuevos verifican la última implementación funcional. Las45 regresiones se ejecutaron antes de la última guarda acotada de historial bancario de AppointmentQuickEdit; no cambiaron los módulos que ejercitan. Las suites PHP completas finales y la prueba red/green verifican esa guarda. RESULTADOS.json conserva esta procedencia; no se atribuyen a este trabajo los totales de entregas anteriores. No hubo errores JavaScript inesperados; los bloqueos CSP exactos de recursos externos heredados permanecen separados y no se relajó la política.

El reloj de replay está desactivado y los servicios continúan en127.0.0.1:8092/33324. Fixture final sin vínculos: cita171, profesional8, fecha17/11/2026, paciente original130 y destino131. La demostración ya cambió el servicio al14 y el paciente al131; seleccionar otro servicio de igual precio y un paciente diferente para repetir. Fixture con adelantos: cita166, fecha11/11/2026, mismo profesional. Las credenciales permanecen fuera de Git.

CI y revisión humana final quedan pendientes; no hay autorización de publicación/despliegue. Los montos/referencias bancarias, penalidad, recotización y transferencias con vínculos sensibles no están habilitados. Los dos commits de esta entrega son locales: código y documentación/evidencia.

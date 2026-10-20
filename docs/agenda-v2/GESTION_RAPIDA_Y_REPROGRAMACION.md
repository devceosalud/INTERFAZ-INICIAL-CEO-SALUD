# Gestión rápida, reprogramación e investigación del recargo

Fecha: 20/10/2026. Base: `13efa1489dfdb98e7f309a716225a539fd15e3cd`.
Worktree único: `.worktrees/agenda-v2`. Rama: `feat/agenda-operativa-flujo-reevaluaciones-v2`.

## Alcance ejecutado y preservación

Se respalda la propuesta visual en el commit local `dc3afdf`. Implementación `28c779a` y control final de horario sin cambio `4d43620` (`4d436207a31d47eff001cb860c4b55ff57b52714`). La aplicación se modifica únicamente dentro de este worktree. Se conserva el archivo ajeno no versionado `dad}sad}sa`. Los SHA256 de los cinco cambios anteriores del checkout principal coinciden con el acta de cuarta ronda. No se hace push, no se modifica main, no se abre/reabre/fusiona PR ni se accede a Hostinger o producción.

El entorno de prueba verifica ausencia de `.env`, APP_ENV testing, MariaDB11.8.9, 127.0.0.1:33324, datadir exclusivo dentro de `storage/app/qa-v2/mariadb`, usuarios/esquemas ficticios separados para suite y navegador. El servidor manual persiste en 127.0.0.1:8092 con router seguro. No se resetea la base de navegador ni se borran citas para liberar pruebas.

## Modal implementado

Cinco acciones: **Reprogramar**, **Cambiar servicio**, **Cambiar paciente**, **Corregir datos del paciente** y **Corregir adelanto**. Las dos primeras tienen prioridad visual. Cabecera compacta con paciente, servicio/profesional, fecha/hora, modalidad, estado y total/acreditado/saldo. Solo un panel activo. Se reutilizan botones, campos, tipografía y paleta de Agenda.

Reprogramar conserva el motor y endpoint existentes, consulta disponibilidad, exige motivo en el flujo UI, confirma antes de guardar y conserva borradores ante error. Bloquea controles durante la operación y restaura sus permisos originales después de cancelar o fallar. La revisión del servidor detecta cambios concurrentes. Tras cambiar de día, el calendario navega al destino confirmado por el servidor.

Corregir datos abre la ficha de pacientes existente usando `patient_url` autorizado y el retorno a Agenda. No copia validaciones ni cambia el paciente asignado. El mantenimiento general sigue teniendo las limitaciones de auditoría/concurrencia identificadas en la propuesta: no se afirma que ya se hayan resuelto aquí.

Corregir adelanto lista exclusivamente Payments reales atribuidos inequívocamente, permite elegir cada pago y distingue errores de referencia, monto o ambos. Se muestran importe, operación textual, comprobante y turno. **No se habilita ninguna corrección**, ni existe un nuevo endpoint de escritura. Faltan permiso específico y comprobación de conciliación; montos además requieren aprobación económica. Lectura ampliada de ADMINISTRADOR no se convierte en permiso de modificación o revisión individual de pagos: se conserva la comprobación de edición/visibilidad original.

Cambiar servicio y reasignar paciente muestran un bloqueo comprensible. Esta entrega no interpreta la aprobación visual como autorización de nuevas mutaciones económicas o clínicas. Quedan pendientes sus reglas de precio, vínculos y archivo del contexto del paciente original.

Atención/procedimientos abren Admisión; Documentos e historial abre su pestaña de la misma cita conservando fecha, profesional y sede. Datos operativos, cancelación y retiro vuelven a sus paneles existentes. No se montan formularios ni documentos duplicados dentro del gestor. La operación previamente autorizada de modalidad permanece como acceso secundario, con sus permisos, confirmación y auditoría existentes. Se conservan doble clic, Alt+Enter y botón para móvil.

### Hallazgo de interacción corregido

`public/assets/css/style.css:28968` posiciona de forma absoluta los indicadores nativos de fecha/hora. El campo de fecha tiene contención relativa, pero el de hora no la tenía; Chrome devolvía ese input en `elementsFromPoint` sobre el botón situado fuera de su rectángulo. El clic de Reprogramar no llegaba al botón. Se agrega contención relativa y overflow al input de hora **solo en el modal**. No se altera el CSS global ni se fuerza el clic en las pruebas. El recorrido real posteriormente pulsa, confirma y verifica la escritura y la restauración de controles.

## Registro rápido

Orden real del DOM: resumen total/acreditado/saldo; tarifas/descuentos frecuentes; importe/método/operación/banco; referidos y exoneración en desplegable complementario; comprobantes/OCR en desplegable inferior. Se conservan IDs, un solo formulario, eventos, revisión de comprobantes, permisos, cotización y procesamiento. Al seleccionar archivo se abre su sección para mostrar validación/revisión.

Una primera revisión visual mostró que abrir todas las autorizaciones hacía demasiado alto el formulario. Se separaron las frecuentes de referidos/exoneración, manteniendo estas últimas accesibles. Se comprueba el orden por DOM y en capturas de1366/1440/1920/390. No se afirma que todo el formulario largo quepa en una sola pantalla móvil.

Las pruebas anteriores que exigían OCR antes de tarifas se actualizan a la nueva prioridad aprobada. Se mantienen sus aserciones de unicidad de campos, controles económicos, validación y OCR. El test de anidación ahora identifica el cierre exterior de Registrar adelanto, no el primer cierre de un details interno.

## Slots e historial de reprogramaciones

El motor ya calcula ocupación con fecha/hora actuales de la misma Appointment. Reprogramar actualiza esa fila y conserva dinero, propietario y relaciones. No se crea otra cita ni una reserva histórica. Un intento idéntico a la posición actual no agrega otro evento; el modal pide un horario distinto, conserva el motivo y no envía la solicitud; una revisión o posición esperada obsoleta rechaza el cambio.

Se agrega `RescheduleHistory`, proyección de solo lectura de eventos `REPROGRAMADA`, limitada al período/profesionales/sede y a la visibilidad operativa autorizada. El feed devuelve `reprogramaciones` **fuera de eventos activos**. No se toca disponibilidad pública, ocupación ni capacidad mensual. El evento conserva origen/destino, usuario, fecha/hora y motivo; el nuevo origen incluye doctor/sede para auditoría.

La UI presenta un panel discreto junto al calendario: **CITA REPROGRAMADA → fecha · hora**, identificado con fecha/hora de origen. En movimientos repetidos conserva cada destino histórico e indica también el destino actual. Funciona en diaria, semanal y mensual; no se dibuja encima de la nueva cita del intervalo. Ver cita actual navega al destino, con protección de borradores. Se muestran hasta200 referencias y se informa si hay más; el historial de la cita conserva todos los eventos.

No se incluyen paciente, motivo clínico ni narración de auditoría en las anotaciones del feed. Las identidades de usuarios respetan el permiso de presentación de responsables. Eventos anteriores sin motivo siguen sin motivo inventado. El campo motivo se admite en el endpoint para compatibilidad con clientes anteriores; los flujos UI actuales de modal y arrastre lo solicitan. El servicio mantiene compatibilidad con invocaciones heredadas.

Se prueba liberar origen, reservarlo nuevamente con pago real, destino ocupado, cambio de día, cambios repetidos/no-op, adelantos intactos, conflictos entre sesiones, privacidad y carrera entre procesos. El indicador mensual sigue contando dinero positivo atribuible, incluso inferior al50%; las referencias no incrementan citas ni ocupación.

## Diagnóstico de tarifas y amarillo

Catálogo existente: `additional_rates`, modelo `AdditionalRate`, mantenimiento `AdditionalRateController`/`CatalogManagement`, versionado y auditoría del catálogo. `AppointmentPriceResolver` valida registro activo, vigencia, tipo MONTO_FIJO/PORCENTAJE y valor no negativo. El recargo porcentual se calcula sobre el precio base resuelto antes del descuento; luego se aplica el descuento ordinario. Referidos y reevaluación clínica tienen ramas especiales que no deben combinarse por suposición.

QA contiene únicamente tarifas neutrales/ficticias de0. No se localiza allí la tarifa productiva del20%. El usuario confirma que existe en producción y aún debe comunicar su nombre/ID. **No se consulta producción ni se crea otra tarifa.** Una prueba transaccional ficticia reutiliza la fila de catálogo de su fixture como20%: base100→120; descuento10%→108. Esto demuestra el motor existente, no la política definitiva del nuevo recargo ni una aplicación a una cita persistida.

El amarillo puede corresponder a PAGO_REAL, cuya paleta es amarilla, o a la franja PROGRAMADO de una reserva/cita sin pago; no representa por sí solo estado de atención, confirmación, permiso o posibilidad de cambiar precio. La causa del bloqueo está en `agenda-operational-workspace.js`: rate y descuentos se deshabilitan para **cualquier appointment_id existente**. El resolver cotiza nuevas citas; no ofrece un ajuste seguro de contratos existentes. Se explica ese límite en pantalla; no se elimina la protección por CSS ni se usa una edición de notas para modificar precios.

### Decisión de negocio recibida

Sobrecargo del20% conservando íntegros los adelantos, con implementación económica pendiente. No retener automáticamente20% del adelanto. `resources/views/emails/appointment/record.blade.php` anuncia otra política de reprogramación tardía: cargo20% del valor de consulta y crédito80%. Se documenta la contradicción y **no se modifica ni elimina el correo**.

### Flujo económico propuesto, no activado

1. Confirmar nombre/configuración de la tarifa existente y base comercial. Selección desde catálogo de cada entorno; sin ID productivo fijo, coincidencia ambigua o registro duplicado. Validar valor20%, tipo, vigencia, actividad y versión al confirmar; nunca elegir la primera coincidencia por nombre o porcentaje.
2. Sobre la base aprobada B, calcular R=redondear(B×20/100) a céntimos; mostrar importe contratado previo P, B, R, nuevo total P+R, adelantos reales intactos y nuevo saldo. El orden con descuentos/exoneración debe aprobarse; no asumir que la fórmula de creación autoriza cambiar un contrato existente.
3. Ejemplo únicamente si B=P=100: total120, pagos50, saldo70. Si ya existe descuento, la base y el efecto no se deducen del ejemplo. Exoneraciones, créditos y devoluciones requieren tratamiento explícito.
4. Operación voluntaria con permiso específico, motivo y confirmación, asociada al evento de reprogramación. Clave idempotente y unicidad de ajuste por operación para impedir doble clic. Definir si una segunda reprogramación permite otra penalidad; no inventar esa regla.
5. Antes de confirmar: bloquear/revalidar cita, catálogo, pago, voucher/familia, turno, fiscalidad, conciliación y aplicaciones posteriores. Con pagos/comprobantes no modificar sus importes ni sus líneas directamente.
6. El mecanismo de ajuste contable, compatibilidad de voucher, Agenda, Caja y reportes y rollback necesita aprobación antes de escribirse. Mientras falte, reprogramar conserva contrato y no aplica penalidad. Tampoco se activa recotización de una reserva existente sin pagos hasta aprobar base/política.

## Migraciones, compatibilidad y rollback

No hay migraciones ni transformaciones de datos en esta entrega. Se reutilizan appointment_events y sus metadatos; los eventos originales se conservan. Ningún Payment/Voucher histórico se corrige, anula o reemplaza. No se cambia el esquema ni se crean tarifas o descuentos productivos.

Rollback eventual de código a la base anterior conserva nuevas reprogramaciones y motivos en tablas existentes; desaparece la nueva presentación, no su auditoría. La base anterior ya lee la fecha actual de la misma cita para ocupación. Esta compatibilidad no debe confundirse con el rollback del futuro ajuste financiero, todavía no aprobado. Publicación/despliegue requieren autorización; en esta tarea solo hay commits locales.

## Evidencias y ejecución

Los archivos originales completos permanecen en `storage/app/qa-v2/management`. Copias revisables bajo `docs/agenda-v2/evidence/management` incluyen resultados, JUnit y capturas. Inventario en `evidence/management/ARCHIVOS.json`.

- PHP SQLite final:1136 pruebas,10079 aserciones, sin fallos/errores/omitidas.
- PHP MariaDB final:1149 pruebas,10201 aserciones, sin fallos/errores/omitidas;13 escenarios de concurrencia entre procesos, incluido reprogramar.
- JavaScript:328 pruebas aprobadas, sin fallos/omitidas.
- Chrome155:45 casos aprobados:13 recorridos nuevos,3 enfocados y29 regresiones conservadas (19 Agenda/reevaluaciones/MFR y10 documentos/privacidad). Roles ADMINISTRADOR/ADMISION/COMERCIAL y resoluciones1366/1440/1920/390. Sin errores JavaScript reportados; en el arnés principal no hay errores de consola inesperados y se registran159 bloqueos CSP esperados identificados por URLs exactas.
- Sintaxis:13 archivos PHP y13 JavaScript/CJS modificados, todos aprobados. Ver SYNTAX.json y RESULTADOS.json. CI del nuevo SHA no ejecutada porque no se autoriza publicar.

Las ejecuciones intermedias no aprobadas se distinguen del resultado final: se corrigió un enlace de ficha que intentaba construir patient_id en lugar de reutilizar patient_url; se diagnosticó el indicador nativo de hora; se ajustó el test de paciente a su ID único porque varios ficticios comparten nombre; y se clasifica CSP por URLs externas exactas, sin permitir conexiones ni silenciar errores nuevos. Los bloqueos de fuentes/Clarity/CDN heredados son esperados en QA y se reportan separados de errores de aplicación.

Una regresión antigua agotó todos sus horarios ficticios después de varias rondas. Se añade un bloque de horario exclusivamente ficticio, conservando todas las citas y evidencias. El runner consulta disponibilidad del motor en lugar de limitarse a una lista fija de horas. No se quitan aserciones ni se relajan permisos para pasar.

## Guía local

Servidor persistente: http://127.0.0.1:8092. Credenciales ficticias: `storage/app/qa-v2/ACCESO_MANUAL_QA.md` (no versionado). El fixture final identifica profesional, cita, fecha de origen y destino en `storage/app/qa-v2/management/fixtures-final.json`.

1. Abrir Agenda operativa, seleccionar profesional/cita ficticios del fixture final y su fecha current_date. Doble clic, Alt+Enter o Gestionar cita en móvil.
2. Elegir Reprogramar, completar fecha/hora disponibles y motivo. Confirmar; comprobar nueva fecha en calendario y adelantos intactos.
3. Consultar fecha original: revisar referencias sin ocupación. El botón Ver cita actual lleva al destino; una nueva cita puede ocupar el origen si no existe otro bloqueo real.
4. Corregir adelanto permite elegir dos pagos y consultar cada uno. Correcciones siguen bloqueadas. Datos del paciente abre el editor existente con retorno a Agenda.
5. En Registro rápido abrir Registrar adelanto: tarifas/descuentos y pagos preceden a Comprobantes y OCR. Seleccionar un archivo revela su revisión; OCR no acredita dinero.

Para repetir: guardas de `IsolatedAgendaV2Qa`; phpunit normal y perfil `phpunit.agenda-v2-mariadb.xml`; node --test tests/JavaScript/*.test.js. Para nuevo recorrido se añaden ficticios con `AGENDA_MANAGEMENT_QA=APPEND_FICTIONAL_ONLY` y `tests/Support/agenda-management-seed.php`; luego `tools/qa/browser-agenda-quick-management.cjs`. No usar el runner que resetea navegador o detiene servidor. Las regresiones antiguas usan reloj ficticio de su cohorte; se desactiva al terminar mediante `agenda-v2-browser-clock.php --real-time`.

Detener ERP al terminar: ejecutar el script local `storage/app/qa-v2/stop-manual-qa.ps1`; su opción `-StopDatabase` detiene también la MariaDB verificada, conservando datos. No hay comandos de producción en esta guía.

## Pendientes y límites

Nombre de tarifa y base/política definitivos; contradicción con correo; autorización y mecanismo contable de penalidad; política de repetición; conciliación/permiso de correcciones bancarias; importes; cambio de servicio y reasignación sensibles. No se realizan estas operaciones por aprobar el diseño.

La consulta histórica filtra metadatos JSON por período y limita200 filas visibles; falta medir rendimiento sobre volúmenes productivos representativos antes de decidir índices adicionales. QA no demuestra carga productiva. La referencia solo puede reconstruirse si el evento histórico tiene origen/destino almacenados: no se inventan movimientos anteriores al historial existente. El flujo remoto clínico sigue pendiente de negocio, como en cuarta ronda.


## Cierre local y capturas

Código final verificado: `4d436207a31d47eff001cb860c4b55ff57b52714`. Pruebas aprobadas y procesos terminados con salida0; no hay suites pendientes ni omitidas de las ejecutadas. Las operaciones económicas no aprobadas siguen sin implementarse y no se contabilizan como pruebas aprobadas.

[Galería comparativa](evidence/management/CAPTURAS.md), [resultados](evidence/management/RESULTADOS.json), [inventario](evidence/management/ARCHIVOS.json) y [fuentes verificadas](evidence/management/FUENTES_SHA256.json). Antes del modal se capturó la UI anterior con la cohorte R4; después usa una nueva cohorte exclusivamente ficticia. No se comparan importes de pacientes distintos como medición de rendimiento.

Cita de demostración final:158, profesional8, origen08/11/2026 y destino09/11/2026 a09:00. El origen ya tiene otra reserva ficticia y conserva la anotación. La guía usa esos fixtures; no son pacientes reales. El servidor manual permanece disponible y el replay clock está desactivado.


## Actualización posterior aprobada — gestor y ficha

La entrega de este documento corresponde al baseline5984318. La revisión siguiente incorpora ficha inline compartida y cambios seguros de paciente/servicio, sustituyendo los bloqueos generales descritos arriba únicamente donde el usuario lo autorizó. Mantiene bloqueadas las operaciones económicas o con vínculos incompatibles. [Estado actual, pruebas y guía](CORRECCIONES_GESTOR_Y_FICHA.md).

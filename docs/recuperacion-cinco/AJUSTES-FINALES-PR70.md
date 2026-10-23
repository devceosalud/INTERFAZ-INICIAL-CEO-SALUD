# Ajustes finales funcionales y visuales del PR #70

Continuación de `codex/integracion-cinco-main-20261022`, verificada remotamente en `d255da5b4403e854a1e7275120821a39480b1605` antes de editar. Base de main: `a57b2d04e8302279be1c775c7e8bc913a170f209` (#68). Se conservan #67, #68, las recuperaciones de #69 y la primera ronda documentada en [OBSERVACIONES-PR70.md](OBSERVACIONES-PR70.md). No se fusionó, desplegó ni accedió a Hostinger o datos productivos.

## Cambios y causas comprobadas

La relación concreta de archivos y su propósito está en [ARCHIVOS-AJUSTES-FINALES-PR70.md](ARCHIVOS-AJUSTES-FINALES-PR70.md).

| Observación | Causa | Resultado |
|---|---|---|
| Enlace de reprogramación | Seleccionaba el detalle, sin localizar la página ni desplazar/enfocar la fila. En Chromium la cita quedaba debajo del área visible | El feed localiza la página de la **cita vigente autorizada** dentro de la cronología. El enlace lleva intención de foco, fecha/profesional/sede vigentes; se limpian categorías incompatibles. Se desplaza el panel y, cuando corresponde, la página; foco de teclado y borde temporal de ocho segundos. Un resultado no visible ofrece mensaje sin revelar reservas privadas. Una cita, sin ocupación ni KPI adicionales |
| 3/3 todavía en pre-reevaluación | `ready()` exigía confirmación operativa incluso con todos los exámenes verificados; la actualización del detalle no renovaba inmediatamente el feed | Exámenes completos permiten clasificación naranja/Listos; Pendientes y Listos son excluyentes. `awaiting_confirmation` conserva la etapa real y el botón de confirmación. No se crea ningún evento de confirmación al verificar exámenes. El feed/KPI se actualiza tras cada check, sin recarga. Una corrección que desmarca un examen devuelve el caso a pendiente si no hay otra autorización válida |
| Seguimiento redundante | Cinco variantes de menú y tres controladores conservaban el listado anterior | Se retira el acceso de los menús. Las tres URL antiguas redirigen a Agenda operativa con configuración/acceso efectivos; de lo contrario responden 403. Las rutas conservan sus nombres para compatibilidad |
| Edición entre trabajadores | Se comprobaba el permiso general, sin autoría. La ruta heredada de modificación también permitía actualizar una ficha ajena: reproducción HTTP200 con Comercial sobre DEMO70-A, conservando sus mismos valores | Propiedad comprobada en backend y formularios de solo lectura para el resto. Incluye maestro, popup, contacto, captura durante alta, notas, edición rápida y ruta heredada. Los controles de concurrencia/revisión se conservan. No se transfieren propietarios al asignar responsable comercial |
| Apariencia y espacio de ambas agendas | Cabecera/filtros altos, detalle vacío ocupando ancho y profesionales comprimidos por contadores/favoritos. En la demo, un aviso de29px quedaba oculto bajo el menú fijo y desplazaba el contenido | Cabecera y filtros compactos al100%, lista de Admisión a ancho completo hasta seleccionar paciente, retorno a la lista sin perder selección. Panel derecho adaptable360–420px, separación interna y resumen de pagos en2×2; no recorta los nombres/estado. Profesionales con columna adaptable260–310px, nombre que admite varias líneas y métricas debajo. Admisión hereda la misma familia tipográfica de Agenda operativa. Etiquetas importantes con relleno sólido, texto destacado y alineación derecha, conservando el catálogo y sus reglas. Fondos completos de estado intactos. El aviso privado de QA es ahora un enlace fijo visible, sin ocupar altura de trabajo |

Se encontró además una carrera en Agenda operativa: `load()` invocaba `clearSelection()` sin esperar su limpieza asíncrona, mientras el renderer seleccionaba el enlace de una cita. La limpieza podía pintar «Sin selección» sobre los datos de esa cita. Ahora termina antes del render, con comprobación de vigencia de la petición después de esperar. El recorrido de navegador comprueba paciente, datos y permisos reales antes de editar.

## Propiedad y permisos

Se utilizan **exclusivamente** `patients.user_id` y `appointments.user_id`, como procedencia de creación. `responsible_user_id` sigue siendo asignación comercial y no concede propiedad de escritura. Una ficha sin creador identificable queda en consulta para Admisión/Comercial; no se inventa un propietario ni se modifica el historial.

| Acción | ADMISION / COMERCIAL creador | Otro operativo | ADMINISTRADOR existente | MEDICO |
|---|---|---|---|---|
| Ver cita confirmada | Acceso vigente | Acceso vigente | Acceso vigente | Solo alcance médico vigente |
| Corregir documento/contacto/ficha maestra | Creador de **paciente**; en contexto de cita, también creador de esa cita | Solo lectura; escritura403 | Se conserva acceso administrativo ya existente | Sin nuevas facultades |
| Motivo/observación de la cita | Creador de **cita**, con CREATE o UPDATE efectivo y acceso a Agenda | Solo lectura; escritura403 aun con UPDATE | Conserva UPDATE efectivo | Sin ampliación |
| Cambiar paciente/servicio/modalidad mediante editores existentes | También exige las autorizaciones y guardas anteriores | Bloqueado por propiedad | Guardas anteriores | Sin ampliación |
| Indicadores herida/postoperatorio de cita existente | Además requiere UPDATE, como antes | Bloqueado | Guardas anteriores | Sin ampliación |
| Llegada, confirmar retorno, verificar exámenes, rechazo y caja | Sus permisos específicos anteriores | Sus permisos específicos anteriores | Según reglas anteriores | No se convierten en firma médica |
| Corrección económica | Sin autorización administrativa: no | No | Permiso económico y revisión/auditoría anteriores | Solo si además es administrador autorizado |
| Reprogramación / salida | Flujos operativos específicos, con sus autorizaciones e historial anteriores | No se sustituyen por la edición de ficha | Reglas anteriores | Alcance anterior |

El permiso estrecho de notas para el creador Comercial usa su CREATE existente; **no concede `appointment.update`**, edición de flags ni administración económica. El maestro continúa siendo único: ser creador de una cita sobre un paciente ajeno no permite corregir el documento de ese paciente. La consulta de la ficha permanece disponible.

El override administrativo **ya existía** y se conserva expresamente. También existía la lectura administrativa global de Agenda operativa mediante `AppointmentVisibility::operationalAdministrator`; la redirección heredada utiliza esa misma agenda, sin conceder roles ni permisos nuevos. Las reservas ajenas siguen ocultas para los otros trabajadores operativos. El dashboard conserva su alcance previo. Ningún override permite firmar actuaciones de otro médico.

## Reevaluación: listo no equivale a confirmado ni realizado

| Situación | Categoría | Confirmación operativa | Acción clínica |
|---|---|---|---|
| Indicación médica, exámenes incompletos | Pendiente · Por confirmar | Botón disponible | No finalizar ni iniciar segunda atención |
| Indicación médica, todos completos, aún sin confirmar | **Listo, naranja** + badge «falta confirmación operativa» | Botón disponible; evento aún ausente | Esperar confirmación para iniciar segunda atención |
| Confirmada, exámenes incompletos | Pendiente confirmada | Evento conservado | Requiere exámenes o autorización manual válida |
| Confirmada y completa / manual autorizada | Listo, naranja | Evento conservado | Médico propio inicia segunda atención |
| Segunda atención iniciada | En consultorio | Historial íntegro | Médico propio finaliza; salida posterior separada |

Se conservan motivo/auditoría del retorno manual, cierre por rechazo antes de retomar, procedimientos realmente pendientes, firma médica verificada y una sola cita. Se mantiene `users.doctor_id`, las tres migraciones históricas y la redirección exclusiva del usuario2. Pilar sin cuenta médica verificada continúa **pendiente de validación clínica completa**.

## Validación y evidencias

Los resultados finales, capturas antes/después y logs se encuentran en [qa/ajustes-finales70/README.md](qa/ajustes-finales70/README.md). Los fallos intermedios se conservan y describen; no se eliminan pruebas para obtener verde.

| Validación | Resultado |
|---|---|
| PHPUnit, SQLite completa | **1.241 pruebas / 11.485 aserciones**, sin fallos |
| PHPUnit, MariaDB11.8.9 completa | **1.268 / 11.726**, sin fallos; incluye concurrencia |
| Regresión afectada después de ajustes de UI | **264 / 2.469**, sin fallos |
| JavaScript | **356**, sin fallos |
| Chromium, paleta completa | **208 verificaciones**, cuatro perfiles, escritorio/móvil y selección |
| Chromium, recorrido integrado previo | **22 controles**, siete cuentas; atención normal, manual, rechazo, salida, economía y reprogramación |
| Chromium, observaciones finales | **10 controles de recorrido**, foco escritorio/móvil,3/3 sin confirmar, confirmación→segunda atención→salida, autoría y permisos |
| Chromium, crear sin adelanto | Dos entradas reales de UI: paciente existente y nuevo; motivo vacío, doble clic y creación atómica comprobados |
| Chromium, panel derecho | Cuatro tamaños, paciente y estado sin elipsis, campos contenidos y vista previa120/60/nuevo10/saldo50 sin guardar un pago |
| Chromium, densidad | Ocho combinaciones de ambas agendas a1920/1536/1366/1280px, zoom100%, sin desborde, hueco superior6px y nombres sin elipsis |

Las suites completas corresponden al cambio funcional integrado; después de los últimos ajustes exclusivamente visuales se repiten las regresiones afectadas, JavaScript y navegador. No se comparan sus cifras con ejecuciones históricas de otras candidatas como si fueran la misma versión.

La revisión de densidad cuenta únicamente filas de citas completas dentro del viewport **y** del panel: excluye intervalos libres y referencias rojas. Su número varía con el alto de pantalla, estados y selección. El resultado medido de cada tamaño se conserva en `layout-final-densidad.json`, junto con capturas antes/después; no se cambia el zoom ni se ocultan filas para mejorar el resultado. El detalle del intervalo de Agenda operativa y el bloque superior complementario de reprogramaciones se despliegan a demanda; las referencias rojas cronológicas permanecen visibles.

Pruebas nuevas: `FinalAdjustments70Test`, `final-adjustments70.test.js`, `final-adjustments70-browser.cjs`, `agenda-density70-browser.cjs` y `agenda-registration-layout70-browser.cjs`. Los recorridos leen las credenciales privadas de la demo local, exigen URL/esquema locales, no contienen contraseñas y realizan los clics con Chromium. La geometría comprueba igualdad de familia tipográfica (`poppins, sans-serif`) y panel derecho de360–420px en escritorio. Las expectativas anteriores de la pantalla retirada y de edición ajena se sustituyen por redirección/autorización y bloqueo, conservando pruebas de privacidad, integridad, concurrencia y dinero.

Errores de prueba detectados durante la preparación: identidad MEDICO con permisos ajenos al perfil clínico, campos horarios de fixture sin `forceFill`, y clic sobre checks que se revierten temporalmente hasta completar la petición. Se corrigió la preparación/espera real, sin relajar los controles del producto. Las expectativas heredadas de listado200 y escritura entre trabajadores se sustituyen por la nueva redirección/bloqueo. La prueba de foco detectó recorte del panel y el orden de aparición del aviso; se corrigieron, incluido un margen interno para que la última fila y su borde no queden recortados por fracciones de píxel. La comprobación móvil exige también que la cita esté dentro del viewport. Las capturas de detalle esperan el contenido cargado, en lugar del mensaje transitorio «Consultando cita».

## Cómo volver a probar

1. Abrir `http://127.0.0.1:8000` y la guía privada `/demo-guia`. Las cuentas y contraseñas locales se conservan fuera de Git.
2. **Admisión / DEMO70-E:** pulsar «Ver cita reprogramada» en la fila roja10:20. La cita vigente14:00 debe quedar visible y enfocada, con borde temporal, también en móvil.
3. **Julio / DEMO70-C:** indicar Reevaluación. **Admisión:** completar sus tres checks sin confirmar previamente. Debe entrar en Listos/naranja, salir de Pendientes y mantener la confirmación disponible. Confirmar; Julio inicia reevaluación y finaliza; Admisión registra salida.
4. **Menú Citas:** solo Agenda operativa. `/admissionist/appointment`, `/admin/appointment` y `/receptionist/appointment` conservan protección/redirección según acceso.
5. **Admisión / DEMO70-A:** corregir motivo o ficha. **Comercial:** abrir la misma cita y comprobar consulta sin edición, mensaje y bloqueo servidor. **Comercial / DEMO70-P:** puede corregir sus propias notas; la reserva permanece privada hasta confirmarla por un flujo autorizado. Las pruebas HTTP también cubren otro usuario con UPDATE efectivo.
6. Revisar ambas agendas al100%: sin franja vacía superior, nombres de profesionales completos y alertas a la derecha. Admisión abre la lista a ancho completo; seleccionar una fila abre el detalle y «Volver a la lista» recupera el ancho. Los filtros adicionales están en «Más filtros». Revisar también390px. Los colores completos siguen identificando los mismos estados.
7. Para repetir: acceso local **Reiniciar datos DEMO70**, o ejecutar `storage/app/demo-pr70/reiniciar-datos-demo.ps1`. Requiere escribir `REINICIAR DEMO PR70`, comprueba conexión/nombre descartables y guarda respaldo antes de restaurar.

## Esquema y entrega segura

No se agregan tablas, columnas, estados MySQL, módulos, roles ni categorías de permisos. No se modifica `.env`, relaciones doctor/paciente, mecanismo financiero ni despliegue. Los respaldos SQL y las credenciales permanecen exclusivamente en el directorio privado local ignorado por Git.

El entorno manual usa PHP8.3.35 en127.0.0.1:8000 y MariaDB11.8.9 en127.0.0.1:33343; la suite MariaDB usa otra base descartable en33342 y la matriz visual SQLite otro servidor en8108. Las integraciones externas de la demo siguen bloqueadas/simuladas. Los procesos auxiliares de QA propios se detienen al terminar; permanece la demo manual.

El PR sigue siendo Draft #70. Revisión, respaldo y eventual despliegue/rollback requieren una tarea posterior autorizada; se conserva el plan anterior de [INTEGRACION-MAIN-68.md](INTEGRACION-MAIN-68.md). Esta entrega no ejecuta migraciones ni modifica producción.

# Cuarta ronda: continuación local del 20/10/2026

Esta entrega continúa el trabajo existente; no sustituye su historia. La guía funcional está en [diagnóstico de cuarta ronda](RONDA_4_DIAGNOSTICO_Y_GUIA.md), la cobertura anterior en [matriz de rondas](MATRIZ_CUMPLIMIENTO_RONDAS.md) y los resultados finales en [QA](QA_Y_EVIDENCIAS.md).

## Estado recuperado y protección

Se verificó HEAD 0ba1c23bcf8e52da0e641bda155f39c860a1c843 en agenda-v2, rama feat/agenda-operativa-flujo-reevaluaciones-v2. Los cuatro archivos pendientes coincidían con los señalados por el usuario: diagnóstico, admission-board.js, agenda-manage-appointment.js y browser-agenda-v2-round4.cjs. Se revisó su diff y se protegieron en 9c78b3b. El checkpoint previo 5299381 y el baseline c8b91fa permanecen en la historia. La última edición rechazada por cuota no se había ejecutado.

Se preserva también un archivo ajeno sin seguimiento, dad}sad}sa (ayuda del pager), excluido de los commits. Los cinco archivos previamente modificados del checkout principal se comparan por SHA256 con el acta inicial; no se editan otros worktrees.

GitHub confirmó PR46 cerrado, Draft y sin fusionar, con head remoto c8b91fa. Esta continuación no hace push, no reabre ni modifica el PR y no crea otro. No puede acreditar GitHub Actions para un SHA exclusivamente local: esa verificación queda pendiente de autorización para publicar.

## Integración de main

Fetch confirmó origin/main 76e6ad21d69372d429698de35142689dfd6bd873, que incorpora PR53, PR54 y PR55 sobre PR51 ya integrado. Se ensayó merge-tree y se revisó el merge detenido antes de confirmar. Los nueve archivos nuevos/cambiados corresponden al preloader, exportación CSV/XLSX, referencias de pagos y pruebas/workflow de reportes. No hubo conflicto textual ni selección indiscriminada de una versión. Merge local c598129 conserva Agenda2.0 y los reportes. El revert de PR50 permanece; no se reincorporan sus cambios de catálogo.

Las exportaciones conservan todas las sedes como valor fijo, profesional nativo opcional, máximo31 días, rol ADMINISTRADOR activo más VIEW_AUDIT, CSRF/sesión y restricciones de Agenda. CSV mantiene BOM y columnas alineadas; XLSX incluye autofiltro, primera fila fija, montos numéricos e identificadores como texto. Los números provienen de pagos atribuidos por la proyección económica, no del snapshot legado. Varios abonos conservan sus referencias y ceros iniciales. Ningún reporte escribe dinero o reevalúa citas.

## Cambios nuevos de esta continuación

### Distribución de etiquetas

El renderizador diario construía cinco celdas y añadía badges dentro del nombre. agenda-layout.css forzaba ese contenedor a display:block; el resto del ancho no se utilizaba. Ahora la celda principal contiene identidad/atención y una zona semántica agenda-day-indicators a la derecha, con CSS Grid y proporciones flexibles. No se usan posiciones absolutas ni desplazamientos artificiales. A mayor ancho se usan chips de11–12px, separados del nombre. En móvil, las columnas se reorganizan y las etiquetas pasan debajo de la identidad, conservando texto y foco. Nombres largos se consultan mediante título, selección y gestor, sin superponerse a etiquetas.

Se mantiene el componente común, deduplicación, prioridad de alertas y +N accesible. Las etiquetas provienen de atributos persistidos. TELECONSULTA, herida y postoperatorio pueden coexistir. En columnas semanales estrechas se evita el salto letra por letra mediante nowrap y elipsis; el título y nombre accesible incluyen todos los indicadores autorizados. La vista mensual mantiene sus agregados, sin exponer identidades desde disponibilidad.

Se retira el chip redundante Pago real registrado de la presentación compartida; el estado económico, importes y paid_real continúan disponibles. La categoría financiera interna conserva su significado en la leyenda como Con pago atribuido; no se repite como chip del contexto seleccionado ni entre los indicadores de la fila. No se elimina su significado ni se convierte una etiqueta en autorización financiera.

### Capacidad mensual

RegularCapacityService filtraba por secured (50%). Ahora el indicador anónimo de intervalos regulares ocupados exige authority=PAYMENTS, asignación inequívoca, paid_cents>0 y dinero efectivo positivo. Un abono0,01 cuenta aunque secured siga siendo falso. Se conservan los filtros de intervalos regulares elegibles, sede y estados que liberan capacidad; adicionales/fuera de horario no inflan capacidad regular. Un snapshot sin pagos, una atribución ambigua o crédito sin nuevo pago no se presentan como cobro nuevo. El campo histórico ocupacion_segura conserva su nombre por compatibilidad, con semántica documentada y tooltip actualizado.

No cambia AppointmentEconomicPosition, secured, confirmación, reserva, disponibilidad, anticipo, saldo o políticas de atención por esta modificación. Las pruebas de umbrales usan ahora pagos reales de0,01 en vez de snapshots para comprobar también el cambio de regla; se conserva el negativo de snapshots y se afirma que el crédito mantiene su regla económica aun sin contarse como nuevo dinero.

### Gestor y operaciones simultáneas

El bloqueo pendiente se completó: conserva el estado disabled previo de inputs, selects, textareas y botones, incluidas restricciones por permiso, y lo restaura al finalizar. La captura de clics bloquea operaciones competidoras mientras hay solicitud o confirmación abierta; las acciones de la confirmación siguen accesibles. La consulta de disponibilidad comparte el bloqueo y no sobrescribe una fecha que el usuario pudiera estar cambiando. Cancelar la confirmación no envía una petición; errores conservan el texto y rehabilitan controles; una reprogramación cancelada se identifica como tal. Documentos y cierre reutilizan la coordinación existente.

El backend conserva bloqueo, UUID/revisión, auditoría y permisos. No se habilita edición ordinaria de importes ni cambio de profesional/servicio de citas pagadas. Las decisiones de teleconsulta, procedimientos y costo cero de la cuarta ronda permanecen intactas.

## Reproducción de QA y hallazgos de esta continuación

La primera suite SQLite terminó1130/10028. MariaDB recorrió1142 casos y reportó23 errores de montaje: el helper de AgendaStabilizationTest generaba una serie como Q2670, superior a los cuatro caracteres reales de la columna. Los nuevos escenarios de capacidad hicieron crecer el autoincremento y revelaron la dependencia del ID. El helper codifica ahora el mismo identificador en base36, sin ampliar la columna ni reducir la validación; sus32 pruebas dirigidas pasan. Se repiten las suites completas y se conservan los reportes fallidos como antecedentes, sin contarlos como aprobados.

El primer Chrome coincidió con la carga de dos suites PHP y agotó20 segundos al navegar. No se aumentó el timeout. Los recorridos se ejecutan secuencialmente. Otra repetición detectó correctamente que la cola activa no incluía fixtures del19/10 al ser20/10: se mantiene la regla de mismo día.

Para repetir los datos existentes sin modificar sus fechas ni debilitar esa regla, IsolatedAgendaV2Qa admite un reloj ficticio explícito, solo después de verificar el esquema/datadir/puerto/usuario exactos y solo para el navegador. El comando CLI tests/Support/agenda-v2-browser-clock.php --use-fixture-date fija el día del manifiesto al19/10/2026,23:59 America/Lima. No cambia Windows, MariaDB, pacientes ni el código de negocio; PHP/SQLite/MariaDB de suites no lo usan. Las cookies de sesión del replay duran hasta cerrar el navegador para evitar que la fecha ficticia expire la cookie en el reloj real; autenticación, servidor de sesiones y CSRF siguen activos. El historial sigue registrando eventos ficticios; los tiempos de rendimiento usan relojes monotónicos/reales del navegador y Server-Timing.

Al finalizar se desactiva mediante --real-time. Para revisar manualmente reevaluaciones de los fixtures del19/10, activar expresamente ese replay, volver a iniciar sesión y desactivarlo al terminar. En tiempo real, una cita de un día anterior debe quedar fuera de la cola activa; es comportamiento esperado, no una corrección de datos.

El chequeo de consola adicional distingue exactamente siete URLs de recursos heredados (Google Fonts, LineIcons y jQuery CDN), bloqueadas intencionalmente por la CSP local self-only. Los mensajes quedan registrados como bloqueos esperados, sin permitir conexiones externas ni ocultar excepciones JavaScript, errores HTTP locales u otros mensajes desconocidos. No se declara la consola literalmente vacía. Retirar referencias externas del tema heredado queda como deuda separada; no se rediseña globalmente el ERP para silenciarla.

## Pendientes y riesgos

- Pendiente de negocio: atención clínica remota completa. Se permite registrar, consultar, cambiar modalidad de forma autorizada antes de atención y reprogramar; se bloquean acciones de presencia física.
- Sin nueva operación de descuento ordinario100%: se mantiene exoneración autorizada y reconocimiento histórico verificable.
- Cambiar profesional/servicio o ajustar importes pagados requiere sus operaciones económicas autorizadas, no edición directa.
- Aprobación visual humana pendiente. Capturas y mediciones locales no acreditan capacidad del hosting ni rendimiento productivo bajo carga.
- No hay antivirus documental nuevo. Permanecen MIME, firmas, tamaño, almacenamiento privado, acceso por documento, PDF.js, auditoría y versiones.
- GitHub Actions del SHA local pendiente porque se prohíbe push. No se reutiliza CI de c8 ni main como validación del código final.
- Producción/Hostinger no se inspeccionaron ni modificaron.

## Migración y reversión

Esta continuación no añade migraciones a la cuarta ronda: permanece 2026_10_19_040000_add_agenda_v2_attendance_and_preferences. Es aditiva: attendance_mode, custom_label y preferencias por usuario. Solo se ensaya en QA exacta. No transforma pagos ni eventos de hotfixes. El semáforo y el layout no requieren backfill.

No autoriza despliegue. Una liberación futura requiere respaldo coordinado BD/archivos, ensayo de migración y rollback, assets/backend coherentes y revisión humana. El código anterior no comprende c:UUID, teleconsulta ni documentos PATIENT_PROVIDED: conservar lectores/políticas compatibles o bloquear temporalmente toda la superficie incompatible, incluidas lecturas que fallen. Conservar columnas, preferencias, ledgers y archivos posteriores; no hacer down con datos nuevos ni restaurar una BD antigua descartando operaciones.

## Prueba manual local

Usar http://127.0.0.1:8092, fecha19/10/2026 y MÉDICO RONDA CUATRO FICTICIO. Las cuentas agenda-r4-admin@example.invalid, agenda-r4-admission@example.invalid y agenda-r4-commercial@example.invalid comparten una contraseña ficticia consultable solo en storage/app/qa-v2/ACCESO_MANUAL_QA.md o browser-private.json, ignorados por Git.

1. Seleccionar TELE_ADDITIONAL, comprobar indicadores a la derecha y abrir +N. Probar doble clic, Alt+Enter o Gestionar cita en móvil.
2. Modificar motivo; con otra sesión modificar la cita y comprobar409 con borrador conservado. Cancelar cambio de modalidad y provocar validación sin perder controles.
3. Reprogramar con disponibilidad y confirmación. Revisar que modalidad, pagos e historial se mantengan.
4. En Admisión, CUSTOM → Otro procedimiento → nombre significativo → confirmar. ADMISION marca/desmarca; el contador y origen deben recalcularse.
5. Documentos → + Adjuntar archivo: PDF ficticio privado, visor y versiones. COMERCIAL no obtiene permisos de pagos o atención.
6. Favoritos: marcar, recargar, regresar con la misma cuenta y comparar con otra.
7. Reportes: ADMIN con VIEW_AUDIT descarga CSV/XLSX, filtra columnas en Excel y revisa operaciones00001984/00002004 del caso ficticio adicional. No hay selector editable de sede; ADMISION/COMERCIAL reciben403.

El servidor es persistente y no lo cierra el runner. Reinicio seguro: tools/qa/start-agenda-v2-manual.ps1 -RestartOwnedServer. Detener: storage/app/qa-v2/stop-manual-qa.ps1; -StopDatabase apaga solo MariaDB QA. No usar runners que reconstruyen fixtures para una sesión manual.

## Cierre técnico local

Fuente funcional final: d93f4841d68bb864c2aa2545284f27d81df88d1c. Checkpoints/commits de esta continuación:9c78b3b (cuatro archivos recuperados), c598129 (main53–55), bd51b52 (gestión, capacidad y etiquetas) y d93f484 (eliminación del marcador redundante y comprobaciones finales). La confirmación posterior de este informe incorpora evidencias e inventario, sin cambiar las fuentes verificadas; su SHA se entrega en el acta local/final de la conversación.

Resultado nuevo:135 sintaxis PHP +36 JS; SQLite1130/10028; MariaDB1142/10141;12 carreras; JavaScript324; Chrome43 recorridos funcionales y10 superficies de consola sin errores inesperados. Cero fallos/errores/omisiones finales. Se conservan los fallos iniciales y sus causas. Salida: seis muestras, mediana277ms clic→resultado,2,5ms clic→request,105,03ms aplicación,33,55ms SQL;51 consultas y un POST por muestra. [Detalle verificable](QA_Y_EVIDENCIAS.md#resultado-final-local-del20102026).

El código está preparado para revisión técnica local. No está autorizado para publicar ni desplegar. Pendientes: aprobación visual humana y GitHub Actions del SHA local, que requiere publicación autorizada. El flujo remoto completo y nuevas operaciones financieras/profesional/servicio siguen sujetos a las decisiones descritas; no se implementan parcialmente mediante escrituras peligrosas. La tercera ronda de documentos y las reglas anteriores permanecen bajo regresión completa.

Servidor local persistente disponible; reloj de replay desactivado y tiempo real restaurado tras terminar las pruebas. Para reevaluaciones de la cohorte del19/10, ejecutar explícitamente el reloj QA y volver a iniciar sesión:

```powershell
$qaPhp = (Get-Content storage/app/qa-v2/manual-server.json -Raw | ConvertFrom-Json).executable
& $qaPhp tests/Support/agenda-v2-browser-clock.php --use-fixture-date
# Revisar únicamente casos ficticios del19/10 en127.0.0.1:8092.
& $qaPhp tests/Support/agenda-v2-browser-clock.php --real-time
```

Para repetir las suites: php vendor/phpunit/phpunit/phpunit; para MariaDB usar -c phpunit.agenda-v2-mariadb.xml (identidad exacta QA verificada). JavaScript: node --test con todos los tests/JavaScript/*.test.js. Navegador: browser-agenda-v2-round4.cjs; las19 regresiones usan AGENDA_V2_FIXTURE_SET=round3-regression, AGENDA_V2_EVIDENCE_SET=round3-regression y AGENDA_V2_ROUND2_STAGE=after; luego browser-agenda-v2-round3-functional.cjs y browser-agenda-v2-final-console.cjs. No usar el runner que resetea fixtures o detiene el servidor. Las referencias reales son los archivos y comandos en este worktree, no una instalación PHP productiva.

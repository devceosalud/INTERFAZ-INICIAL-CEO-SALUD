# Corrección visual puntual — Admisión

Rama: codex/fix-admision-colores-20261022. Base integrada: f29019a1f3649149a31af3447e67e009970a7277 (merge #65). Padre inmediato previo al merge: 3e1ca22b8e7e07bb2ddf18221cd8ff70499242a7.

## Causa y alcance demostrados

El merge #65 NO modifica CSS de las filas, AgendaLegend, AgendaAppointmentPresentation ni el envío de variables CSS desde renderRows. Las reglas de relleno ya están idénticas en d245918, 3e1ca22 y f29019a. El main 1f840e9 NO las contiene. El responsable informa que Hostinger está configurado para publicar main: redistribuir esa fuente antigua no instala el flujo integrado ni su CSS. No basta corregir otra rama.

La captura posterior muestra además textos anteriores al merge y no incluye el filtro de pendientes. La reproducción local combinando CSS 1f840e9 con JS integrado vuelve a mostrar blanco/celeste y franjas, con 52 discrepancias de superficie. El bloque completo recupera los fondos correctos. No se atribuye falsamente una eliminación de reglas al merge. La prueba reproduce una combinación de versiones; el archivo realmente servido en Hostinger requiere comprobar SHA y hash HTTP, sin deducirlo solo de la captura.

El último commit inmediatamente anterior al merge que conserva los colores es 3e1ca22; el merge integrado f29019a también los conserva. a8bd76d8 incorporó las reglas finales sobre el estilo histórico blanco/celeste. La comparación de hashes está en COMPARACION-FUENTES.md. La diferencia de cantidad de citas por rol y los cambios reales de estado no se interpretan como diferencias de paleta.

## Cambio mínimo de producto

**Único archivo de producto:** public/css/scheduling/admission-board.css. Se explicitan las tres reglas indicadas por el usuario en el mismo lugar y con igual semántica que la base integrada. La edición renueva la huella de contenido usada por AgendaAssets. No se cambia el mapeo, prioridades, intensidades ni colores. No se añade !important global ni un mapa paralelo. Esta edición por sí sola no cambia la fuente de despliegue de Hostinger.

JavaScript queda byte a byte idéntico a la base integrada y sigue estableciendo --ad-status-background desde presentation.fringe.color/fondo y --ad-status-color desde presentation.color. Se retiró el guard JavaScript exploratorio antes de la entrega. PHP de aplicación, vistas, rutas, permisos, filtros, estados, pagos y flujo #65 no cambian.

## Tabla ANTES / DESPUÉS

ANTES correcto se refiere a d245918/3e1ca22 y al CSS integrado, no al CSS incompleto de main. Las tres reglas hacen que el fondo ocupe fila y cinco celdas tanto normal como seleccionada; hover conserva fondo y distingue con contorno.

Caso | Estado servidor | Clave visual | ANTES correcto / DESPUÉS (mismo fondo)
--- | --- | --- | ---
PROGRAMADA_SIN_PAGO | PROGRAMADO | SIN_PAGO_REAL | `#fdf0d9` / `#fdf0d9`
PAGO_REAL | PROGRAMADO | PAGO_REAL | `#fff2a6` / `#fff2a6`
EXONERADA | PROGRAMADO | SIN_PAGO_REAL | `#fdf0d9` / `#fdf0d9`
LLEGADA | PACIENTE_LLEGO | PRESENTE | `#99f6e4` / `#99f6e4`
EN_ESPERA | EN_ESPERA | PRESENTE | `#99f6e4` / `#99f6e4`
EN_ATENCION | EN_ATENCION | PRESENTE | `#99f6e4` / `#99f6e4`
REEVALUACION_PENDIENTE | EN_ATENCION | PRESENTE | `#99f6e4` / `#99f6e4`
REEVALUACION_LISTA | EN_ATENCION | LISTO_REEVALUACION | `#ffc28a` / `#ffc28a`
FINALIZADA | ATENDIDO | ATENDIDO | `#74dccb` / `#74dccb`
SALIDA | ATENDIDO | SALIDA_REGISTRADA | `#99f6e4` / `#99f6e4`
CANCELADA | CANCELADO | DESACTIVADA | `#fecaca` / `#fecaca`
PROCEDIMIENTOS_INCOMPLETOS | EN_ATENCION | PRESENTE | `#99f6e4` / `#99f6e4`
BLANCA_SIN_PAGO | REEVALUACION | SIN_PAGO_REAL | `#ffffff` / `#ffffff`

La cita programada sin pago/exonerada conserva el fondo de franja PROGRAMADO (#fdf0d9), sin afirmar pago real. El amarillo #fff2a6 se conserva para dinero real atribuido. El blanco corresponde a los supuestos históricos SIN_PAGO_REAL sin esa franja programada. Reevaluación pendiente y procedimientos incompletos no sustituyen el color principal; listo conserva naranja según prioridad existente. Una exoneración no crea ni acredita dinero.

## Pruebas y evidencias

- PHP SQLite: **47 pruebas, 855 aserciones**, sin fallos; incluye 13 escenarios para ADMINISTRADOR, COMERCIAL, ADMISION y MEDICO vinculado, más el recorrido integrado existente. Los snapshots de citas/eventos permanecen iguales y existe un solo pago ficticio real atribuido de la cohorte.
- AgendaAssetsTest adicional: **2 pruebas, 4 aserciones**, correcto; confirma versionado por contenido incluso sin cambios de tamaño/fecha.
- JavaScript completo: **359/359**. Tres pruebas nuevas protegen fila/celdas, selección/hover y el contrato de variables existentes.
- Chromium real: **312 verificaciones**, sin errores JS; 13 escenarios, escritorio y móvil, normal/selección, comparación con CSS incompleto y perfiles autorizados. Se reprodujeron 52 diferencias con CSS de main; el CSS integrado completo pasa igual antes y después, y la corrección final conserva esa presentación.
- El hover de la fila con pago real se comprueba en navegador; los ocho PNG muestran escritorio/móvil, incluidos fondos amarillo y naranja/rojo en distintas posiciones del scroll.
- No se ejecutó MariaDB local adicional: no cambia PHP de aplicación ni esquema. La suite general de GitHub se ejecutará sobre el SHA publicado; no confundir su estado con las pruebas locales.
- No se modificó ninguna base productiva. Los scripts de QA rechazan .env y una base ficticia preexistente; usan SQLite local, cuentas example.invalid y servicios externos deshabilitados.

Evidencias en qa/: matriz JSON con estado/clave/color/clase CSS/fondo computado de cada celda, informes y capturas. Las capturas con datos reales del usuario no forman parte del commit. El ANTES de navegador es una reproducción deliberada con CSS histórico, no una captura atribuida a producción.

## Archivos

- public/css/scheduling/admission-board.css: único cambio de producto, tres reglas de fondo completo.
- tests/JavaScript/admission-row-colors.test.js: contrato CSS y variables JS.
- tests/Concerns/BuildsAdmissionColorData.php: 13 escenarios exclusivamente ficticios compartidos por pruebas/QA.
- tests/Feature/Scheduling/AdmissionColorCompatibilityTest.php: igualdad de estado/clave/color por perfil, lectura sin cambios de datos.
- tests/Support/admission-colors-browser-seed.php y admission-colors-browser.cjs: cohorte local y verificación con navegador real.
- docs/admision-colores/: diagnóstico, comparación de hashes, entrega, instalación/rollback, capturas y resultados.

## Instalación y rollback

INSTALACION.md contiene comandos exactos condicionados a autorización. Comprobar HEAD real y archivos servidos; detenerse si no está instalada la base integrada f29019a o si existen cambios locales. La configuración main 1f840e9 requiere resolver primero la fuente aprobada, no pulsar Redistribuir ni instalar a ciegas un CSS.

No se cambió Hostinger, main ni automatizaciones. Sin migraciones, .env, paquetes o nuevas dependencias. No hacer merge/deploy automáticamente. El rollback de esta corrección vuelve a f29019a, manteniendo todo el flujo integrado; nunca a main antiguo ni restaurando BD.

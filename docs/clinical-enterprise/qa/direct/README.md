# Envío directo — validación final del PR72

Base162de591; datos exclusivamente ficticios; sin cambios de diseño, permisos, esquema o automatización. [Contrato y alcance](../../ENVIO-DIRECTO-PR72.md), [diff de producto](product.diff), [cinco archivos](archivos-producto.txt), [verificación de alcance](scope.json).

## Suites completas

| Suite | Pruebas | Aserciones | Fallos/errores/omitidas/avisos |
|---|---:|---:|---|
| SQLite | 1284 | 11887 |0/0/0/0|
| MariaDB11.8.9 | 1318 | 12193 |0/0/0/0|
| JavaScript |369|—|0 fallos,0 omitidas|

[JUnit SQLite](sqlite.xml), [MariaDB](mariadb.xml), [JavaScript](javascript.log), [resumen](results.json). Las siete carreras específicas de Clinical Enterprise permanecen: doble envío manual con el mismo UUID200/200 y operaciones competidoras200/409, una fila por solicitud y una auditoría por operación. La prueba anterior de general se adapta para comprobar su conservación histórica y el rechazo de nueva creación; no se elimina cobertura para obtener verde.

## Navegador y roles

Seis grupos nuevos: A1 sin solicitudes/oculto/API422; Q14 Radiografía+Curación/actualización sin recarga/un clic/módulo/sin popup; replay y doble clic; Cirugía+RM automáticas sin botón; Laboratorio posterior envía solo uno; roles/ID/parametrización rechazados y móvil390 sin desborde. [Resultado directo](browser-direct.json).

Repetidos22+10+4 controles anteriores,7 grupos PR72,6 filtros,6 refinamientos,8 grupos de catálogo,8 de selectores y5 viewports del popup. Fondos:208 verificaciones repetidas sin discrepancias. [Todos los logs y conservación económica](browser-regressions.json), [colores](colors.json).

La prueba de snapshot vincula la oferta a la misma lista/versión que el detalle. Una selección concurrente nueva no habilita silenciosamente otro procedimiento:409 antes de escribir. Se verifica que no cambian citas, pagos, comprobantes, realización de exámenes, reevaluación ni salida. Los motivos históricos y automáticos permanecen byte a byte; los nuevos motivos manuales son null. Las únicas diferencias económicas de la batería histórica son el TICKET corregido por el administrador y las dos citas creadas por el test sin adelanto, ambos flujos expresamente autorizados.

## Capturas reales y cita correcta

| Estado | Captura |
|---|---|
| Antes: Q14 con cero solicitudes mostraba el botón | [Antes](before/appointment-14.png), [popup retirado](before/popup-before.png) |
| Ahora: Q14 con cero solicitudes, sin botón | [Después](after/no-procedures-Q.png) |
| A1 restaurada: cero solicitudes y sin botón | [A1](after/no-procedures-A.png) |
| Dos solicitudes confirmadas pendientes, botón visible | [Q14 pendiente](after/button-pending-2.png) |
| Un clic abre el módulo directamente | [Seguimiento](after/direct-module.png) |
| Ya enviados: el botón desaparece | [Q14 enviada](after/already-sent.png) |
| Solo RM automática: sin botón | [M15](after/automatic-only-M.png) |
| Solo un procedimiento agregado pendiente | [Q14 laboratorio](after/button-pending-1.png) |
| Móvil | [Después](after/mobile-after.png) |

Antes del reinicio, A1 contenía tres solicitudes ya seguidas por las pruebas manuales; se registró ese estado real en findings.json. No se confundió con Q14 vacío. La BD anterior se respaldó privada antes de cada restauración. Una aserción antigua por etiqueta reutilizada también coincidía con otro paciente; ahora el test se vincula al enlace de appointment_id14, sin cambiar el producto para ocultar un fallo.

## Demo / publicación futura

[Estado restaurado](demo-final-state.json):23 citas,2 pagos S/120,2 TICKET,200 médicos y7 seguimientos. A sin botón; Q pendiente; U ya enviado; M/S automáticos; V general histórico. Guía/credenciales/scripts privados en la demo8000; reset con confirmación y respaldo.

No hay sexta migración. Las cinco existentes y las pendientes reales deben contrastarse con migrations/esquema productivo durante el preflight autorizado. [Runbook](../../PREPARACION-PRODUCTIVA-PR72.md). No se consultó ni modificó Hostinger/producción.

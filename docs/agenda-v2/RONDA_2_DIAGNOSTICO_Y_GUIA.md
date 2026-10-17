# Segunda ronda — diagnóstico y revisión local

## Evidencia del caso parcial

La cita ficticia original n.º 1 no se alteró durante la investigación. Su auditoría demuestra: selección 2/0 NOT_READY; realización 2/1 NOT_READY; SEND de ADMISION V2 FICTICIO el 16/10 a las 22:05:35 UTC, con motivo explícito, 2/1 MANUAL READY; después 2/2 MANUAL READY. El 17/10 se desmarcó a 1/2 manteniendo esa decisión, se desmarcó a 0/2 quedando SUSPENDED y se volvió a 1/2 recuperando MANUAL READY.

Esto explica el naranja observado con datos reutilizados. El autor y origen antes quedaban poco visibles y la cola mezclaba categorías. No se encontró evidencia de conteo incorrecto, pérdida de selección, caché HTTP o autorización implícita en la matriz reproducida. La auditoría sintética está en [original-manual-audit.tsv](evidence/round2/original-manual-audit.tsv).

Antes de editar producción fuente se crearon citas ficticias independientes y se ejecutó Chrome sobre 58c5f92: 2/0 y 2/1 sin envío no estaban listas, 2/2 automático sí; desmarcar bajaba a 1/2 y salía; 3/2 no, 3/3 sí, 0/0 no. El envío parcial explícito sí producía MANUAL READY. [Estados anteriores](evidence/round2/before-results.json).

La prueba adicional de cabecera dañada detectó un fallo real: `ready()` confiaba únicamente en status READY. Un encabezado AUTOMATIC 2/1 forzado en una BD de integración ficticia se mostraba listo. [Prueba fallando antes](evidence/round2/stale-ready-before.txt). La protección ahora exige origen/progreso coherentes y mantiene esa cita fuera de la cola activa. Las escrituras normales ya recalculaban correctamente. No se atribuye este caso artificial a producción sin evidencia.

## Regla y explicación visible

| Solicitados | Realizados | Autorización | Resultado |
|---|---|---|---|
| 2 | 0 | Ninguna | Pendiente, sin naranja; envío prohibido |
| 2 | 1 | Ninguna | Pendiente, sin naranja |
| 2 | 1 | ADMISION confirma con motivo | Listo MANUAL, naranja y autor visible |
| 2 | 2 | Ninguna | Listo AUTOMATIC, naranja |
| 3 | 2 | Ninguna | Pendiente, sin naranja |
| 3 | 3 | Ninguna | Listo AUTOMATIC, naranja |
| 0 | 0 | Ninguna | Sin reevaluación |

Desmarcar una realización automática quita el estado listo y la fila activa sin borrar eventos. Una decisión manual anterior explícita puede mantenerse con al menos un realizado; sin realizados se suspende. WITHDRAWN requiere nuevo envío explícito. Estados finales, fuera de jornada y prioridades de conciliación/reembolso conservan sus reglas. No se crea otra cita, cobro ni indicación clínica para conseguir naranja.

## Probar en la laptop

1. Abrir [ERP local](http://127.0.0.1:8092) en Chrome. Usar las cuentas ficticias del archivo local ignorado `storage/app/qa-v2/browser-private.json` y la guía `storage/app/qa-v2/ACCESO_MANUAL_QA.md`. Las cuentas originales se conservan. Para escenarios de esta ronda se añadieron `agenda-r2-admin`, `agenda-r2-admission` y `agenda-r2-commercial` bajo `@example.invalid`, con la misma contraseña ficticia local; nunca credenciales productivas.
2. Fecha 17/10/2026, profesional «MÉDICO RONDA 2 FICTICIO». Seleccionar la cita «R2 FRESH2». El estado inicial tras QA es 1/2 AUTOMATIC anterior, pendiente, sin naranja. ADMISION puede realizar Laboratorio y luego desmarcarlo. Abrir otra sesión (incógnito u otro perfil) para comparar Nicol y Agenda operativa tras el polling.
3. «R2 MANUAL2» conserva 1/2 con autorización explícita de ADMISSION RONDA 2 FICTICIO. Ver autor/motivo y pendientes; retirarla solo con un envío/retirada explícitos y motivo. No confundir este fixture con el automático.
4. En Agenda operativa seleccionar horario disponible y paciente/servicio: motivo se encuentra en Registro rápido, antes de guardar/pagar. Vacío o solo espacios enfoca el campo. En paciente nuevo se muestra el mismo textarea en el modal. Los pagos de una cita existente no lo vuelven a exigir.
5. En Admisión probar búsqueda/DNI, filtros de médico y Más filtros; Restablecer; indicadores y resumen económico superiores; pestañas de Atención/Observaciones/Pagos/Documentos. «R2 HISTORY» muestra «No registrado». Una observación «.» se acepta; espacios se rechazan.
6. En Reevaluaciones alternar Listos, Pendientes e Historial. Listos excluye suspendidos y retirados; el progreso y el origen acompañan al color. En móvil usar «Volver a la lista».
7. Seguimiento de citas conserva consultas y Nicol, sin crear. POST heredado devuelve 403 incluso para ADMINISTRADOR. En Maestro Profesional / Servicio, el profesional MFR ficticio habilita CONSULTA MEDICA MFR FICTICIA QA y mantiene Traumatología bloqueada sin DevTools.

Para detener el entorno manual, usar el script local existente `storage/app/qa-v2/stop-manual-qa.ps1`, que verifica proceso/ruta/puerto antes de detener el servidor; revisar su guía para detener también la MariaDB ficticia si corresponde. No cerrar otros PHP/MySQL ni otros worktrees.

## Evidencia visual

[Antes escritorio](evidence/round2/before-1366.png) · [Antes móvil](evidence/round2/before-mobile.png)

[Después 1366×768](evidence/round2/after-1366.png) · [1440×900](evidence/round2/after-1440.png) · [1920×1080](evidence/round2/after-1920.png) · [Móvil detalle](evidence/round2/after-390.png) · [Móvil listado](evidence/round2/after-mobile-list.png) · [Nicol integrada](evidence/round2/after-queue.png).

Las capturas completas permiten revisar también el scroll. Escritorio contiene scroll interno en listado/detalle; móvil prioriza un panel por vez. Las aserciones de geometría y cero errores JS complementan la inspección visual, sin sustituir la aceptación humana del personal.

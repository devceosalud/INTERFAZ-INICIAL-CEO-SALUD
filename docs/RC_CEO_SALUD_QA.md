# RC CEO Salud — QA reproducible

Código de aplicación probado: `b388bd3f4816e30506e913aec33234518949db5b`. Hash del árbol de aplicación: `a95709f76233b2e83b9e603581af47d8877c9b7a0bb355aff9f2c3f75b550ed5`. Los commits posteriores de cierre contienen herramientas/evidencias/documentación; no sustituyen estos resultados por los antiguos 866/258. El SHA final del PR y su repetición de suites se registran en el PR de integración.

## Ambiente y aislamiento

Windows local, PHP 8.3.35, Laravel 9.52.21, MariaDB 11.8.9, Node 26.3.0, Chrome 155.0.8059.79/Playwright instalado. Sin Docker ni staging Linux/Hostinger. No se utilizó la BD productiva ni se hicieron consultas a Hostinger en esta ejecución.

- PHP general: SQLite en memoria con variables forzadas, sin `.env`; proveedores externos sustituidos por loopback/dobles. No interpreta credenciales productivas.
- Navegador/jornada: `IsolatedRcQa`, MariaDB loopback 33322, `ceosalud_qa_rc`, directorio propio `.rc-mariadb-1189/data`; HTTP 8026/8027, sesiones/cookies independientes, CSRF verdadero incluso con APP_ENV testing. Fixtures ficticios creados desde UI; root y catálogos mínimos sembrados con guard estricto de puerto/esquema/usuario/versión/datadir.
- Integración/performance: `IsolatedRcSuiteQa`, 33323 `ceosalud_qa_rc_suite`, directorio distinto, HTTP 8028/8029. Las suites MariaDB y los benchmarks destructivos de fixtures **se ejecutan secuencialmente**, no sobre la misma BD a la vez. Las bases anteriores 33318–33321 y los demás worktrees se conservaron.
- Storage::fake usa token por PID para aislar archivos de suites paralelas, sin habilitar renombrado de bases Laravel paralelo. Los fixtures del navegador no usan rutas falsas de pruebas unitarias.
- Mail, colas, RENIEC/SMS/bancos y captura externa bloqueados. Clarity conserva su código; el router de QA bloquea todo egress y no certifica su privacidad productiva.

## Suites finales ejecutadas

| Suite | Resultado | Evidencia |
|---|---|---|
| PHP completa | **878 pruebas, 6.850 aserciones, 0 fallos** | `evidence/rc/final/full-php-candidate.txt` |
| MariaDB completa de integración | **372 pruebas, 4.153 aserciones, 0 fallos** | `evidence/rc/final/full-mariadb-candidate.txt` |
| JavaScript | **261 aprobadas, 0 fallos/omitidas** | `evidence/rc/final/full-js-candidate.txt` |
| Lint PHP/JS y patrones de secretos | Sin hallazgos de las comprobaciones ejecutadas; ver número de archivos en static-rc.json | No equivale a pentest ni PHPStan |
| Composer plataforma / autoload PSR-4 estricto | Requisitos evaluados success; autoload optimizado estricto exit 0 | composer-platform-final.json / composer-psr4-strict.txt |
| Composer audit | **NO aprobado: Laravel mantiene 4 avisos únicos, uno alto** | composer-audit-security-update.json |

No se suman PHP y MariaDB como cobertura única: varias pruebas de integración heredan casos de las suites funcionales. Las carreras reales están en `tests/Integration/AgendaConcurrencyTest.php`, `RcConcurrencyTest.php` y `WorkerConcurrencyTest.php` y se ejecutaron dentro de la suite MariaDB completa.

## Comandos

Desde este worktree RC; `$php` es el ejecutable aislado 8.3.35 ya verificado. Nunca cambiar estos comandos para apuntarlos a producción.

```powershell
$php = (Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
& $php vendor/phpunit/phpunit/phpunit
& $php vendor/phpunit/phpunit/phpunit -c phpunit.rc-suite.xml
powershell -NoProfile -File tools/qa/run-js.ps1 -Report full-js-candidate.txt
node tools/qa/audit-rc.cjs
# Solo reanudar procesos propios existentes; no reinicializa datos
powershell -NoProfile -File tools/qa/resume-rc-runtime.ps1
# Exclusivamente fixture propio 33322; guarda evidencia anterior y exige backup ensayado
powershell -NoProfile -File tools/qa/rehearse-owned-rc.ps1
node tools/qa/run-browser.cjs bootstrap permissions-schedules jornada-reserves jornada-pay jornada-conversion jornada-confirm-care jornada-security prices schedule-edit overlap reevaluation documents mobile legacy ocr-negative polling documents-poll
# Ensayo separado: no acreditó ocultación real; conservar fallo/observación
node tools/qa/run-browser.cjs visibility
# Solo después de terminar la suite MariaDB, sobre 33323
& $php tests/Support/rc-nicole-benchmark.php candidate --suite --reset-owned
powershell -NoProfile -File tools/qa/run-nicole-http.ps1
node tools/qa/nicole-http-cycle.cjs
& $php tests/Support/rc-index-evidence.php
powershell -NoProfile -File tools/qa/backup-restore-rc.ps1
```

`COMPOSER_ROOT_VERSION=dev-rc-integracion-preproductiva` permitió terminar el CLI de Composer cuando su detección Git se bloqueaba. Autoload estricto se ejecutó con `dump-autoload --no-plugins --no-scripts --no-interaction --optimize --strict-psr`. La extensión zip se habilitó solo mediante bandera del PHP local para instalar dependencias, sin modificar php.ini ni Hostinger.

## Defectos corregidos y regresiones repetidas

1. Horarios podían reducir cobertura y dejar una ADICIONAL confirmada fuera del bloque. La selección de citas protegidas incluye confirmadas/LEGADO y reservas con dinero también ADD; disponibilidad exclusiva financiera sigue siendo regular. Locks, versión HMAC/lock_version, auditoría y rollback. Probado por UI, suite y carreras.
2. Se rechazaban unos solapamientos y otros se aceptaban con aviso. Se aplicó la decisión expresa del negocio: rechazar todo **nuevo** solapamiento al crear/editar; fixtures heredados se conservan sin borrarlos. Carrera de altas produce un ganador y un rechazo.
3. Edición de notas podía apoyarse en CREATE del propietario. Ahora exige UPDATE también en DTO/UI/servidor; grant/revoke desde editor y 403 en pantalla antigua sin escritura.
4. Documentos operativos carecían de reemplazo privado versionado y separación consistente de evidencia médica/pagos. R14 añade capacidades explícitas, clasificación, cadena única, razón/actor, HMAC/UUID y conservación de archivo anterior; evidencia protegida no se sustituye con esta acción. Pruebas de permisos, citas ajenas, stale/replay, rollback y archivos.
5. Nicole revalidaba filas pero dejaba su detalle documental abierto obsoleto. Se añadió revisión independiente del detalle, autorización cada vez, respuesta mínima si no cambia y protección contra respuestas tardías; preserva selección, scroll y expansiones. Dos sesiones verifican subida, nueva versión y revocación sin recarga.
6. Formularios heredados dependían de FullCalendar CDN, devolvían respuestas tardías de paciente/médico y borraban servicio seleccionado tras fetch de edición. Se reutilizó FullCalendar local, limpiaron dependencias y protegieron versiones; datalist para hora, precio común y reprogramación doble probados. Eliminados logs de identidad y respuestas de error HTML sin sanear.
7. Día predeterminado UTC discrepaba con Lima en Agenda/horarios/legado. Se alineó fecha operativa sin cambiar timestamps globales ni históricos; pruebas de frontera temporal y navegación.
8. Controles selectpicker duplicados en horarios e input de comprobantes interceptaba clics de documentos. Ajustes limitados al módulo; móvil/navegador repetidos sin cambio global de paleta.
9. Dependencias antiguas presentaban avisos de seguridad. Se actualizaron transitivas compatibles en lock; Laravel permanece con avisos pendientes. Namespace `Api/AdditionalRate` alineado con PSR-4 para Linux, autoload estricto aprobado.
10. Suites simultáneas aislaban BD pero compartían fake filesystem: tres fallos de conteo en MariaDB. Se aisló por proceso sin reducir aserciones; focal 17/169 y suites completas PHP/MariaDB repetidas aprobadas.

## Fallos de QA preservados, sin ocultarlos

Los archivos originales/errores permanecen en storage/app/qa-rc (privados, fuera del PR cuando contienen sesiones). Evidencia saneada de fallos relevantes se incluye en `evidence/rc/final/failed-attempts.json`.

- Conteos de pruebas 866/258, 876/371 y 877/371 corresponden a árboles previos, no se utilizan como certificación final.
- Primer performance de 2.000 citas p95 1.641,705 ms excedió el objetivo local de 1.000 ms; se conservó, se optimizó y se repitió.
- Captura de imagen ficticia produjo error de protocolo Chrome; se reintentó captura y se repitió jornada. Un timeout HTTP posterior ocurrió después de confirmar: se inspeccionó persistencia, sin repetir cobro; nueva jornada ficticia limpia ejecutó circuito completo.
- Prueba negativa intentó cobrar una cita ya ATENDIDA: recibió 409 legítimo antes de evaluar sobrepago. Se dirigió a la ADD confirmada todavía cobrable, conservando expectativas estrictas de 422 para sobrepago y banco repetido y comparación completa de pagos.
- Métrica inicial de polling incluía cancelación de carga inicial por “Aplicar filtros”. Se separó explícitamente ese tramo manual (máximo observado 2) del polling estable (máximo 1), sin ocultar el dato. Se corrigió selector de fixture inexistente `#admission-patient` por el input real `name=patient`; se repitió toda la etapa.
- **No aprobado:** ocultación real en Chrome con ventana, dos intentos mantienen ambas pestañas visible y expiran. No se modificó document.hidden para fabricar éxito; unit tests cubren la rama pero el ensayo real queda pendiente. Móvil emulado sí comprobó innerWidth, DPR, meta viewport e interacción.

## Alcance de la recomendación

Los flujos focales locales son revisables; no se certifica producción. R05, R12, R13, R19 y R27–R30 siguen parciales; R25/R26 faltantes y diferidos; R21–R23 cancelados. Ver matriz JSON/Markdown para cobertura exacta, sin confundir un formulario con cumplimiento. Bloqueos productivos: Laravel, privacidad Clarity, staging/equivalencia web, respaldo/restauración productivos, autodespliegue, configuración financiera, permisos/sede/horarios y aprobación de responsables.
# Evidencias ficticias de revisión local

Los PNG son capturas reales de Chrome contra Laravel local, con autenticación y CSRF. No son maquetas. Todos los nombres/correos/pacientes son ficticios; el navegador bloquea solicitudes externas. No se incluyen SQLite, credenciales temporales, .env, sesiones ni archivos privados. El reloj QA es 21/10/2026 09:00 Lima para reproducir reglas existentes de mismo día; timestamps persistidos se muestran en UTC donde así los serializa el ERP.

- `medico/01-antes-cuenta-existente.png` y `02-despues-vinculo-auditado.png`: reutilización de la misma cuenta e historial administrativo.
- `medico/03-permisos-medicos-minimos.png`: editor de roles existente.
- `medico/04-antes-llegada-medico.png`, `05-inicio-medico-desktop.png`: antes/después de llegada e inicio propio.
- `medico/05a-listo-manual-sin-procedimientos.png`: listo manual de Admisión dentro de EN_ATENCION, sin finalizar.
- `medico/05b-indicacion-retirada-historial.png`: retiro con conservación de indicación anterior.
- `medico/06-pendiente-medico-desktop.png`, `07-pendiente-medico-mobile.png`: pendiente secundario y procedimientos.
- `medico/08-pendiente-admision-desktop.png`, `09-pendiente-admision-mobile.png`: filtro/contador pendiente y mismo color operativo.
- `medico/10-listo-admision-desktop.png`, `11-listo-admision-mobile.png`: dos procedimientos completados, listo y exclusión de pendiente.
- `medico/12-finalizacion-medica.png`: finalización diferenciada sin salida física.
- `medico/13-medico-sin-vinculo-403.png`, `14-revocacion-historial.png`: denegación y revocación auditada.
- `medico/database-invariants.json`: comprobación directa local tras finalizar y desvincular:5 usuarios iniciales/finales,1 cita,1 pago60, precio120/saldo60, salida nula, médico autor de cinco eventos y solicitante de dos procedimientos completados por Admisión.
- `reprogramaciones/01-antes-agenda-desktop.png` a `10-destino-vigente.png`: seis escenarios de regresión de la funcionalidad visual ya auditada, desktop/móvil/semana/mes, intervalo reutilizado y segundo traslado.

Los browser-results.json contienen casos PASS y capturas. Los XML de PHPUnit permiten inspeccionar cada caso, aserciones y suite. Los grupos de la matriz se solapan; no sumar contadores de grupos. Los hashes de fuente normalizan únicamente CRLF a LF.

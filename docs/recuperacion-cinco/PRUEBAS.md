# Resultados de esta candidata

## Ejecuciones finales

| Validación | Resultado observado | Evidencia |
|---|---|---|
| PHPUnit completo SQLite en memoria | **1.283 pruebas, 11.745 aserciones**, 0 errores/fallos/warnings/omitidas | qa/recovery-sqlite-final.log; qa/phpunit-summary.json |
| PHPUnit completo MariaDB 11.8.9 aislada | **1.310 pruebas, 11.997 aserciones**, 0 errores/fallos/warnings/omitidas | qa/recovery-mariadb-final.log; qa/phpunit-summary.json |
| JavaScript completo | **364/364**, 0 fallos/omitidas | qa/recovery-js-final.log |
| Sintaxis | **91 archivos PHP/JS**, sin errores | qa/syntax.json |
| Cachés Laravel | route:cache, view:cache, config:cache y sus limpiezas: todos código 0 | qa/recovery-preflight.json |
| Chromium: atención integrada | 7 comprobaciones, escritorio y móvil, sin errores JS | qa/flow/browser-results.json; seis capturas; database-invariants.json |
| Chromium: excepción/economía/reprogramación/vínculo | 5 comprobaciones completas desde cohorte limpia, sin errores JS | qa/extra/browser-results.json; seis capturas |
| Chromium: paleta histórica | **208 comprobaciones**, 13 escenarios × 4 perfiles × 2 tamaños × normal/selección; hover adicional en escritorio; sin errores JS | qa/colors/browser-color-matrix.json y capturas |
| Contratos de fuente | Bloque CSS de PR67 intacto y tres migraciones idénticas a f29019a1 | qa/source-contracts.json |

Las carreras económicas se incorporaron a `phpunit.agenda-v2-mariadb.xml`: el directorio anterior no las incluía. La suite final incluye 27 pruebas adicionales de integración respecto a SQLite, entre ellas identidad médica, revocación, doble envío, correcciones concurrentes, caja y documentos fiscales. Reejecución focal de economía: 6 pruebas/52 aserciones (qa/recovery-economic-concurrency.xml).

## Recorridos e invariantes comprobados

- Reprogramación HTTP y UI desde la cita original al destino; misma identidad, historial, duración y pagos; historial de cambios sucesivos, cursor/paginación, enlaces, disponibilidad del origen y ausencia de efecto sobre conteos/saldos. Pruebas de ambas agendas, de permisos y de reservas privadas.
- Llegada COMERCIAL sin conceder appointment.update; identidad médica propia, cuenta/médico activos y vínculo verificable; no inicio sin hora de llegada aunque exista EN_ESPERA heredado. Dos botones después de iniciar; indicación, filtro Pendientes, procedimientos 2/3 y 3/3, Listos automático, prioridad naranja, manual sin procedimientos y parcial con motivo sin marcar pendientes como realizados. Finalización bloqueada cuando pendiente; retirada médica motivada; salida física posterior e idempotente.
- El recorrido UI principal se leyó después en SQLite: una cita, un pago60, tarifa120, saldo60, tres procedimientos solicitados por el médico y verificados por Comercial, tres eventos clínicos, timestamps de llegada/inicio/fin/salida, vínculo y roles ADMINISTRADOR + MEDICO conservados. No se fabricaron pagos para colores o atención.
- Corrección económica: rol ADMINISTRADOR obligatorio aunque otro rol tenga permisos históricos; revisión firmada y vigente, motivo, antes/después, clave idempotente, mismo servicio/catálogo y pago intacto al cambiar tarifa/descuento. Pruebas de descuentos fijos/porcentaje/retirada, recargos y tarifa; caja abierta/cerrada, fiscal/SUNAT, documentos, créditos/devoluciones, exceso y atribución ambigua. Carreras con cajero RECEPCION dueño del turno y editor ADMINISTRADOR separados.
- Confirmación sin adelanto solo ADMISION; oferta y endpoint protegidos; motivo/confirmación/revisión/UUID; conflictos de disponibilidad; mismo saldo y cero pagos/vouchers nuevos; distinción de exoneración/pago; indicador amarillo solo si hay evento autorizado y sigue sin dinero; visibilidad después de confirmar y reserva privada ajena oculta; recorrido posterior llegada→inicio→finalización→salida. Al ingresar dinero real cambia la presentación y conserva la auditoría de la excepción.
- Migraciones: registros heredados conservados, FK, índice único, rol preexistente sin reasignación, ambigüedad de guard detenida, vínculo no borrable cuando hay uso/historial. Las tres migraciones fuente coinciden con la referencia instalada informada; no se ejecutaron sobre producción.
- Continuidad: suites completas de autenticación, roles, pacientes, horarios, disponibilidad, reservas, precios, pagos, comprobantes, caja, conciliación, documentos y operaciones ERP anteriores; no solo pruebas aisladas de las clases recuperadas.

## Fallos encontrados y correcciones, sin ocultarlos

1. Primer arranque focal: PHP no podía escribir cachés locales bajo el sandbox; 8 errores de bootstrap, sin aserciones. Se ejecutaron las pruebas en el checkout aislado con autorización de herramientas, manteniendo el guard de SQLite en memoria y servicios externos bloqueados. No se cambió el guard de seguridad para evitar ese error.
2. Primer JavaScript: las pruebas recuperadas de la pantalla médica leían el JS excluido. Se adaptaron al código real del panel Admisión, conservando cancelación, doble envío, reintento de UUID, selección tardía, restricciones de acciones y protección de mensajes. Esto permitió añadir bloqueo durante refresco y pruebas de la excepción sin adelanto; no se restituyó una segunda pantalla para hacerlas pasar.
3. Primera suite completa SQLite: **1 fallo entre 1.281 pruebas**, porque la señal sin adelanto se aplicaba a confirmadas históricas sin autorización. Se exigió el evento de confirmación auditado y se añadió regresión explícita; paleta original restablecida sin cambiar estados. Se conserva qa/recovery-sqlite.log.
4. Primera MariaDB: **4 fallos entre 1.308 pruebas**. Uno era el mismo color; uno, el smoke que esperaba el puerto33324 ocupado por otro runtime; dos eran fixtures concurrentes que pretendían cerrar caja con un ADMINISTRADOR sin RECEPCION. Se dio a la nueva instancia el puerto fijo33341 con la misma guarda estricta de versión/esquema/usuario/datadir y se corrigieron los fixtures para usar un cajero real del rol existente dueño de su turno. No se debilitaron permisos productivos. Se conserva qa/recovery-mariadb.log.
5. Navegador: encontró que el backend devolvía el nuevo indicador pero la lista/detalle filtraban su clave. Se incluyó en la presentación visible y se volvió a ejecutar el recorrido desde una cohorte limpia. No bastó la prueba PHP para detectarlo.
6. Harness de reprogramación: esperaba ver referencias dentro de un desplegable cerrado y usaba una URL equivocada del editor de usuario. Se abrió el historial por su control real y se corrigió la navegación a la ruta existente; no se cambió producto para satisfacer el harness. Se conserva evidencia del intento previo en qa/extra/attempt1/.
7. Harness de colores añadido a la cohorte existente: colisión de serie ficticia RV01 y navegación móvil en detalle que ocultaba la lista. El fixture genera series distintas y transaccionales, y el harness vuelve a la lista antes de capturar. La ejecución final de 208 verificaciones usa solo la candidata actual; no presenta una reproducción deliberada de CSS antiguo como evidencia productiva.

## Entorno y reproducción

Checkout nuevo sin .env; dependencias de composer.lock reutilizadas y autoload regenerado, sin actualización de paquetes. PHP local 8.3.35; SQLite en memoria. MariaDB 11.8.9 nueva en loopback33341, con esquema `ceosalud_qa_agenda_v2_suite`, usuario QA y datadir `storage/app/qa-v2/mariadb` de este checkout; el puerto33324 existente se preservó. `CEO_RECOVERY_LOCAL_QA=OWNED_LOOPBACK_33341` solo admite esa alternativa fija en Windows; CI conserva puerto33324/servicio3306 y la identidad estricta original. No se acepta una conexión arbitraria, .env ni DSN externo.

```text
php vendor/phpunit/phpunit/phpunit --log-junit storage/recovery-sqlite-final.xml
php vendor/phpunit/phpunit/phpunit -c phpunit.agenda-v2-mariadb.xml --log-junit storage/recovery-mariadb-final.xml
node --test tests/JavaScript/*.test.js
```

Chromium usa el servidor QA en `127.0.0.1:8107`, SQLite propia en `storage/app/qa-reschedule-visual/browser.sqlite`, CSRF y autenticación reales, reloj ficticio fijo, comunicaciones externas bloqueadas y cuentas `example.invalid`. `integrated-admission-browser-seed.php` rehúsa sobrescribir una BD existente. Los intentos locales se archivaron antes de repetir desde cero. Credenciales aleatorias y bases solo existen en storage ignorado; no están en Git ni en las capturas. El seed de colores solo permite anexar al contexto local si existe su marcador y se solicita explícitamente `--append-recovery`.

Los resultados son de esta candidata local. El estado de CI remoto, si se menciona en la conversación/PR, debe consultarse para el SHA publicado. No equivalen a comprobar Hostinger, datos reales o configuración ignorada del servidor. Pilar sin cuenta sigue siendo el límite funcional explícito de la entrega; sus acciones administrativas nunca se atribuyen como firma médica.

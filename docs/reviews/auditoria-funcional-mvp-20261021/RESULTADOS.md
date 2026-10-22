# Resultados verificables — revisión funcional

Entornos ficticios aislados; sin producción. Base 2e81877557fc07d7bb6bf3f31e06f2f100b958e8. **Las pruebas positivas del contrato de lectura NO aprueban un flujo médico autenticado. La Fase A sigue bloqueada y Fase B no comenzó.**

| Comprobación final | Resultado | Evidencia |
|---|---|---|
| PHP completa, SQLite memoria / PHP8.3.35 / PHPUnit9.6.34 | 1200 pruebas, 10649 aserciones, sin fallos | evidence/php-sqlite.txt y .xml |
| PHP seleccionada, MariaDB11.8.9 | 81 pruebas, 881 aserciones, sin fallos | evidence/php-mariadb.txt y .xml |
| JavaScript completa, Node26.3.0 | 344 pruebas; todas pasan, ninguna omitida | evidence/javascript.txt |
| Navegador después, Chrome155.0.8059.79 / Playwright | 6 comprobaciones visuales correctas y rechazo403 de inicio administrativo | evidence/functional/functional-audit-after.json |
| Navegador, contrato independiente de indicación | 5 escenarios correctos, escritorio/móvil, cero errores JS | evidence/functional/reevaluation-read-contract-results.json |
| Regresión original reprogramaciones | 6 escenarios correctos, cero errores JS | evidence/reschedule/browser-results.json |

El diagnóstico de navegador ANTES registra tres diferencias visuales y HTTP200 indebido para inicio por Admisión. Es evidencia histórica del hallazgo, no un fallo pendiente de la guardia actual: DESPUÉS ese intento devuelve403. La capacidad de actuar como médico verificado sigue sin implementarse conforme a la instrucción del responsable.

Las 12 pruebas nuevas verifican llegada auténtica administrativa y reintento; rechazo de inicio/finalización por los cuatro roles con permiso genérico; ruta heredada; restricción administrativa de llegada; contrato de evento pendiente y color intacto; lista vacía no lista; todos los procedimientos completados cambian a listo; envío parcial/manual y retirada con requisitos existentes; indicación retirada/cita inactiva/médico cambiado; filtros combinados y privacidad; ausencia de un escritor médico público o proxy administrativo. Los eventos de indicación se insertan exclusivamente como fixtures del contrato futuro en testing.

El navegador prueba la actualización inmediata tras completar Laboratorio desde el control real de Admisión, contadores1→0 pendientes y1→2 listos en la cohorte; no cambia cita, estado clínico, tiempos o dinero. También verifica filtros excluyentes, selección y colores de todas las celdas/móvil. El contrato conserva el autor sintético y la realización por la cuenta ficticia de Admisión. No existe una cuenta médica vinculada ni se afirma probar inicio/indicación/finalización autorizados.

También se comprobó la salida física administrativa separada sobre la cita ficticia ATENDIDO, conservando economía, mostrando el distintivo y sin habilitar finalización médica.

La regresión original conserva sus escenarios, cambiando solo expectativas de textos: mueve12:20 a24/10, libera origen con marca, Admisión sin duplicados, semana/mes sin ocupación ficticia, nueva cita confirmada en origen con su pago real ficticio, segunda reprogramación y enlace al destino vigente. La suite PHP incluye además los28 casos económicos de la candidata anterior y regresión de creación/pagos/atención administrativa/reportes. Los puntos4 y5 no se han implementado por estas pruebas.

## Reproducción y alcance MariaDB

```text
php vendor/bin/phpunit --log-junit storage/app/audit-final-php.xml
node --test tests/JavaScript/*.test.js
php vendor/bin/phpunit -c phpunit.agenda-v2-mariadb.xml --filter "MedicalIdentityAndReevaluationProjectionTest|RescheduleVisualHistoryTest|AdmissionBoardTest|ManualReevaluation|OperationalProcedure|AgendaV2ReversalTest|AppointmentEconomicEditTest" --log-junit storage/app/qa-v2/audit-phase-a.xml
```

MariaDB se ejecutó únicamente en la exportación QA fuera del repositorio, puerto127.0.0.1:33331, DB ceosalud_qa_reschedule_suite y datadir propio. Como en la candidata anterior, solo el bootstrap de esa exportación adapta puerto/nombres/credenciales sintéticas; mantiene guardias de identidad, versión y ausencia de .env antes de migraciones desechables. El código de aplicación se verifica mediante fingerprints. No se ejecutó la suite completa contra MariaDB ni nuevas carreras médicas: el escritor clínico está bloqueado. La futura migración de vínculo no se ha escrito ni probado porque primero requiere aprobación.

## Reproducción navegador

Siempre checkout QA sin .env, PHP compatible y dependencias del lock. Seeders rechazan DB previa; autenticación/CSRF reales, reloj ficticio21/10/2026, conexión únicamente loopback8099 y llamadas externas bloqueadas. Credenciales aleatorias solo en storage ignorado, no en evidencias.

```text
php tests/Support/functional-audit-browser-seed.php
php -S 127.0.0.1:8099 -t public tests/Support/reschedule-visual-browser-router.php
```

La ejecución `browser-functional-audit-phase-a.cjs before` corresponde al código base2e81877, antes de corregir. Para reproducir ese diagnóstico usar una exportación separada de esa base con los helpers nuevos; no cambiar la rama de revisión ni ejecutar allí el guardia esperando que acepte una acción clínica. La comparación después usa esa misma cohorte de QA preservada, ahora servida por el código revisado.

```text
php tests/Support/functional-audit-indication-fixture.php
node tools/qa/browser-functional-audit-phase-a.cjs after
node tools/qa/browser-reevaluation-read-contract.cjs
```

La fixture de indicación está identificada explícitamente como contrato sintético; no autentica al autor como médico ni concede permisos. Para repetir el último script en la misma cohorte existe `--reset-fictional-completion`, que desmarca el procedimiento mediante el endpoint normal local con versión/UUID y conserva el historial. Para la regresión original usar otro checkout/cohorte limpios y el seeder/script originales de reprogramaciones. Las rutas locales de Playwright/Chrome en scripts se deben adaptar en otro entorno revisor.

No se entrega storage, SQLite, contraseñas, tokens, datos productivos o logs de servidor. Solo JSON, TXT/XML de pruebas y capturas generadas con pacientes ficticios. Los manifest/preflight son comprobaciones de archivos locales, no certifican Hostinger. No se evaluó Safari, dispositivos físicos ni carga productiva. La lista de bloqueos y de criterios médicos no ejecutables está en MATRIZ_Y_ENTREGA.md.

Verificaciones de cierre: 19 archivos PHP/JS con sintaxis válida; 541 fingerprints en candidata, exportación MariaDB y base2e81877; 31 PNG ficticios antes/después/contrato/regresión. Sin cambios de esquema, dependencias o modelos User/Doctor. El tiempo registrado en el log completo no se usa como benchmark de rendimiento.

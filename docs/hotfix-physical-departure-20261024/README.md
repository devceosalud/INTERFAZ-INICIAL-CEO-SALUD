# Hotfix: autorización de salida física

Base verificada: `main` `ea1f63401c3b1d20c7005591f4298c4ae744d839`.
Rama independiente: `codex/hotfix-physical-departure-20261024`.

## Problema y cambio

La transición no clínica exigía `appointment.update` y ADMISION/ADMINISTRADOR. El listado heredaba esas condiciones, excluía a COMERCIAL y podía ofrecer salida a MEDICO + ADMINISTRADOR.

`AdmissionCareService::canRegisterDeparture` ahora es la fuente común de disponibilidad para el detalle y la transición bloqueada. Exige cuenta activa, versión de sesión vigente, ADMISION o COMERCIAL, `appointment.mvp.access`, `appointment.view` y visibilidad autorizada de la cita. No exige ni concede edición general. MEDICO y MEDICO 2 quedan excluidos incluso con roles operativos o administrativos adicionales. ADMINISTRADOR puro no recibe esta acción; ADMINISTRADOR con ADMISION/COMERCIAL explícito y sin identidad médica sigue sujeto a los mismos requisitos operativos.

Solo corresponde a PRESENCIAL, agenda LEGADO/CONFIRMADA, estado ATENDIDO, `hora_atendido` registrada y `hora_salida` nula. Una cita marcada ATENDIDO sin constancia temporal de finalización no se corrige inventando datos: requiere revisión autorizada. Se mantiene la salida después del cierre administrativo por rechazo del retorno, que ya registra su finalización y auditoría.

| Cuenta | Finalizar atención médica | Registrar salida física |
| --- | --- | --- |
| Médico responsable autorizado | Según reglas clínicas actuales | No |
| MEDICO + ADMINISTRADOR | Según reglas clínicas actuales | No |
| MEDICO 2 con otros roles | Según PR #74, sin modificarlo aquí | No |
| ADMISION | Sin ampliación de acceso clínico | Sí, con condiciones de salida |
| COMERCIAL sin appointment.update | No | Sí, con condiciones de salida |
| ADMINISTRADOR puro / otros perfiles | Sin cambios | No |
| Cualquier cuenta en TELECONSULTA | Según hotfix #73 | No |

## Integridad y concurrencia

Se conserva el endpoint y contrato existentes. La UI sigue renderizando exclusivamente `care_actions`, por lo que no necesita duplicar permisos en JavaScript. `careActions` despacha la salida finalizada por separado de las acciones de atención en curso; no se modifican estas últimas.

La transición vuelve a verificar identidad/permisos dentro de la transacción, con bloqueo ordenado de roles y usuario. Conserva los bloqueos existentes de operación, profesional y cita, UUID y hash de payload. Reintentar una operación ya realizada reutiliza el resultado solo si el actor conserva autorización y alcance. Dos claves distintas compitiendo por la misma salida producen un único ganador; el intento perdedor no deja operaciones ni auditorías huérfanas.

La escritura sigue limitada a `hora_salida`, `updated_by_user_id`, marcas de actualización, operación idempotente y evento `ADMISION_SALIDA_REGISTRADA`; `estado_cita` permanece ATENDIDO. No cambia pagos, saldo, procedimientos, reevaluación, comprobantes ni historia clínica.

## Archivos de producto

- `app/Services/Scheduling/AdmissionCareService.php`: autorización específica, comprobación viva y bloqueo transaccional.
- `app/Services/Scheduling/AdmissionBoardService.php`: disponibilidad común y separación de la salida de las acciones clínicas.

Se amplían las pruebas de salida, HTTP de roles y concurrencia. Las pruebas antiguas que suponían salida por administrador puro se adaptan al nuevo actor operativo; las citas sintéticas finalizadas incluyen ahora `hora_atendido`. No se elimina ninguna prueba.

Archivos de pruebas: `AdmissionDepartureTest.php`, `AgendaV2Round3Test.php`, `PhysicalDepartureAuthorizationTest.php`, `Integration/AgendaV2/AgendaV2ConcurrencyTest.php` y el generador local ficticio `tests/Support/agenda-v2-round3-seed.php` (el perfil de salida usa Comercial, no administrador puro).

## Validación y coordinación

Resultados consolidados y capturas ficticias: `evidence/validation.json`.

| Comprobación | Resultado |
| --- | --- |
| SQLite afectadas | 55 pruebas / 818 aserciones, sin fallos |
| MariaDB 11.8.9 afectadas, incluidas carreras | 58 / 847, sin fallos |
| Integración virtual #73 + #74 + salida, SQLite | 28 / 418, sin fallos |
| Misma integración, MariaDB | 28 / 418, sin fallos |
| JavaScript completo | 369 aprobadas, 0 fallos |
| Chromium real | 6 comprobaciones: dos recorridos completos y rechazo de salida remota para cuatro perfiles; capturas escritorio y móvil, sin errores JS |
| SQLite completo | 1.298 / 11.853; 18 fallos y 2 errores heredados, mismo conjunto exacto que la base documentada |
| MariaDB completo | 1.334 / 12.176; 19 fallos y 2 errores: los 20 heredados más una expectativa del puerto de QA descrita abajo |

PHP local 8.3.35; bases descartables independientes, sin `.env`, integraciones externas bloqueadas. La ejecución completa MariaDB utiliza un adaptador privado y estricto del guard de QA para el puerto 33347 y el datadir de este checkout. Los puertos habituales ya estaban ocupados y no se interrumpió ningún servidor existente. `ApplicationAndAuthenticationSmokeTest::test_application_boots_with_the_isolated_test_configuration` exige literalmente 33324/33342; falla únicamente al comparar 33347 con 33324, antes de su comprobación HTTP. No se cambió ni omitió esa prueba. Login, sesiones, ruta Admisión y acciones reales se verificaron en Chromium sobre esta misma instancia. GitHub utiliza el runtime estándar y debe comprobarse separadamente; no se declara la suite completa verde.

La simulación de integración con los HEAD de #73 (`74c2746e`) y #74 (`c54877c8`) combina sin conflictos mediante `git merge-tree`, sin fusionar ramas ni cambiar sus HEAD. Este PR no incorpora sus permisos ni sus funciones de teleconsulta; verifica la compatibilidad conjuntamente en QA aislado. El acceso independiente a Seguimiento de pacientes permanece intacto.

La base conserva fallos heredados conocidos en fixtures ClinicalEnterprise que usan la clave antigua singular de seguimiento. No se cambia configuración, autorización de seguimiento ni pruebas ajenas para ocultarlos. El reporte final debe distinguir estos fallos de cualquier regresión nueva.

Integridad posterior al recorrido: dos salidas y dos actores operativos distintos, ningún permiso `appointment.update` en esas cuentas, una sola auditoría por salida, ninguna cita ni pago creado por salir; estado ATENDIDO conservado. La teleconsulta mantiene llegada y salida nulas. Además, las regresiones afectadas incluyen el recorrido con pago real sintético anterior y confirman economía intacta. No hay modificaciones en assets, Blade, rutas, permisos, configuración de seguimiento ni migraciones.

## Demo y comprobación manual

Demo independiente: `http://127.0.0.1:8021/demo-guia`; credenciales ficticias solo en la guía local privada. Las demos 8000 y 8020 se conservan.

1. Admisión registra llegada de la cita ficticia presencial.
2. Julio ficticio inicia y finaliza atención. No debe ver Registrar salida; una petición directa debe recibir 403.
3. Comercial o Admisión abre la misma cita finalizada y confirma Registrar salida. Debe desaparecer la acción, quedar ATENDIDO y aparecer la hora real de salida.
4. La teleconsulta ficticia no ofrece salida para ningún rol; la API rechaza la petición sin escrituras.

La guía contiene además una cita ficticia nueva `DEMO70 SALIDA QA REPETICION MANUAL`, sin iniciar, para repetir todo el recorrido sin borrar las evidencias ya obtenidas.

## Publicación y rollback propuestos (no ejecutados)

No requiere migraciones, nuevos permisos, roles, configuración ni tareas de datos. Revisar el PR y sus checks; confirmar nuevamente el HEAD de main y la compatibilidad con #73/#74 antes de aprobar. Respaldar código y BD conforme al procedimiento operativo vigente; publicar solo mediante autorización posterior.

Smoke posterior autorizado: cuenta médica finaliza pero no registra salida; ADMISION y COMERCIAL con solo acceso/consulta registran salida presencial una vez; TELECONSULTA nunca. Confirmar actor y único evento, estado ATENDIDO y economía intacta.

Ante problemas, revertir el código de este PR mediante un commit revisado y volver a publicar la versión autorizada. No eliminar salidas ni eventos ya registrados, no retroceder migraciones ni borrar auditoría. Considerar que volver al código anterior restaura el defecto de autorización; restringir temporalmente la operación si fuese necesario. No se ejecutó merge, despliegue ni acceso a Hostinger/producción.

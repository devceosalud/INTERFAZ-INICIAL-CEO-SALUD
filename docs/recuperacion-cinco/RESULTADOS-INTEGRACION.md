# Resultados de integración

Los resultados posteriores a las cinco observaciones manuales del PR #70 están en [OBSERVACIONES-PR70.md](OBSERVACIONES-PR70.md) y `qa/observaciones70/`. Los resultados siguientes corresponden a la primera candidata integrada y se conservan como antecedente.

Base: main `a57b2d04e8302279be1c775c7e8bc913a170f209`, merge del #68. Referencia preservada: PR #69, `f201e35f3e6f985de00058bed833321ce8a894bd`. No merge, force push, despliegue ni ejecución productiva.

## Candidata integrada

| Requerimiento | Resultado y recorrido comprobado |
|---|---|
| Reprogramaciones | Referencia roja desde horario anterior, cadena de fechas/horas, enlace a la misma cita vigente, duración conservada y slot original libre. Probado por HTTP, JavaScript y editor real en Chromium. Privacidad de reservas e historial cubiertos por regresiones. |
| Atención y reevaluación | Flujo #68 fortalecido: llegada por ambos roles, inicio por médico propio con llegada, indicación independiente, confirmación operativa, 0/3 y 2/3 pendientes, 3/3 listo, manual con 0/3 y motivo, retorno médico real obligatorio, finalización y salida separadas. Rechazo con motivo y confirmación conserva indicación y pendientes, sin segunda firma. Rechazo posterior al retorno bloqueado, incluso con snapshot antiguo y dos conexiones simultáneas. |
| Colores | 13 estados × cuatro perfiles × escritorio/móvil × normal/seleccionado = 208 comprobaciones de fondos completos; hover adicional comprobado. Prioridades del #67 conservadas; naranja/turquesa/amarillo/rojo derivados de datos, sin mutar estados para pintar. |
| Economía | ADMINISTRADOR revisa y guarda corrección con motivo, contrato, auditoría y control de revisión. En navegador: precio120/pago60/saldo60 → descuento20 → precio100/pago60/saldo40, mismo servicio y pago. Regresiones cubren caja cerrada, devolución, crédito, fiscal, conciliación y carreras entre corrección/cierre/facturación. |
| Sin adelanto | ADMISION confirma con motivo explícito, ocupa disponibilidad mediante CONFIRMADA, saldo120/pago0, sin exoneración ni voucher ficticio. Señal amarilla exige evento real. Otra reserva privada continúa oculta. Regresiones de permisos, revisión, disponibilidad y pago posterior. |

**Pilar continúa pendiente de validación clínica completa.** No se ha validado una cuenta médica productiva propia. La contingencia administrativa conserva identidad y auditoría, por defecto está deshabilitada y no constituye firma ni segunda revisión médica. La vinculación de Julio no se modificó; su redirección exclusiva por usuario ID2 tiene regresión.

## Ejecuciones nuevas

| Verificación | Resultado |
|---|---|
| PHPUnit, SQLite en memoria, PHP 8.3.35 | **1.223 pruebas, 11.209 aserciones, sin fallos** |
| PHPUnit, MariaDB 11.8.9 local aislada, PHP 8.3.35 | **1.248 pruebas, 11.431 aserciones, sin fallos** |
| JavaScript, Node 26.3.0 | **351 pruebas, sin fallos ni omitidas** |
| Chromium, flujo integrado mediante botones reales | **15 controles, sin errores JavaScript** |
| Chromium, editor económico / reprogramación / editor de vínculo | **4 controles de recorrido, sin errores JavaScript** |
| Chromium, colores | **208 verificaciones de filas y celdas, sin diferencias** |
| Verificación posterior de BD ficticia del navegador | **21 controles de integridad** |
| Concurrencia de episodios, incluida en MariaDB | **4 pruebas, 33 aserciones**: snapshot previo al retorno, carrera retorno/rechazo y doble confirmación/rechazo |
| Lint PHP y `git diff --check` | Sin errores al cierre |
| Tres migraciones históricas | Fuente byte a byte igual a #69; hashes en `qa/integration/migration-source.json` |

Los totales anteriores del #69 (1.283/1.310/364) no se atribuyen a esta candidata. No se importaron sus pruebas dependientes del módulo/rutas clínicas alternativos descartados. No se eliminaron pruebas de main para obtener verde. Se adaptaron fixtures y expectativas al vínculo FK real y al contrato nuevo de roles. Las suites completas incluyen regresiones del ERP de caja, facturación, autorizaciones, reservas y rutas heredadas, además de las cinco recuperaciones.

Evidencia reproducible: [resultados JSON](qa/integration/results.json), [SQLite](qa/integration/sqlite.log), [MariaDB](qa/integration/mariadb.log), [JavaScript](qa/integration/javascript.log), [flujo Chromium](qa/integration/browser-flow.json), [economía y reprogramación](qa/economy-integration/browser-results.json), [colores](qa/colors/browser-color-matrix.json), [integridad](qa/integration/data-integrity.json). JUnit completo y cohortes/credenciales locales permanecen en `storage/app/qa-integration` y `storage/app/qa-reschedule-visual`; no se publican credenciales ni bases.

## Fallos encontrados y correcciones

1. COMERCIAL figuraba como lector de procedimientos: ahora verifica checks y autoriza retorno limitado, sin recibir UPDATE general. Tres pruebas antiguas que exigían rechazar esas nuevas acciones se adaptaron y siguen verificando privacidad y ausencia de ampliación de permisos.
2. Cuatro regresiones de Admisión esperaban que roles operativos completaran clínicamente una cita. Ahora el recorrido usa médico vinculado; los roles no autorizados conservan respuestas 403. No se desactivó la regresión económica ni OCR.
3. Fixture con `doctor_id=999` violaba la FK recuperada: se crea otro profesional real ficticio. Otro fixture no creaba el permiso UPDATE antes de asignarlo. La matriz de colores requería ahora un evento de confirmación operativa real antes de considerar listo al paciente.
4. La edición heredada permitía entrar/salir de EN_ATENCION: se bloqueó ese atajo. La reversión simple no retrocede una atención médica iniciada.
5. `MAX(id)` anidado podía usar el snapshot previo aunque el SELECT exterior tuviera FOR UPDATE. La carrera inicialmente permitía tanto retorno como rechazo. Las escrituras consultan eventos actuales bajo bloqueo; error MariaDB1020 provoca reintento completo con snapshot nuevo, sin continuar una escritura parcial. Las cuatro carreras pasan con conexiones independientes.
6. El panel de una cuenta solo MEDICO intentaba ocultar una pestaña ausente. Se conserva el componente existente y la redacción económica; no se muestra un enlace a Agenda operativa si no hay URL autorizada.
7. Reglas naranja de selección sobrescribían el fondo de fila aunque las celdas mantuvieran la paleta. Se corrigieron para usar la variable del servidor. La revisión final también retiró marcadores residuales de un bloque CSS de integración; `diff --check` y la matriz Chromium se repitieron después.
8. Se unificó la acción visible «Autorizar reevaluación manual» durante el episodio médico y se retiró su botón de envío duplicado en ese contexto. La ayuda ya no exige marcar un examen para autorizar el retorno con 0/3. Los orígenes históricos por procedimientos conservan servicio, auditoría y compatibilidad.
9. Ajustes del arnés, separados de defectos del producto: puerto local del router, espera de token CSRF tras acceso médico y uso de clic para un checkbox que espera confirmación del servidor. No se omitieron los recorridos por esos problemas.

## Arquitectura, esquema y límites operativos

No se agregan agendas, módulos, tablas, roles ni categorías de permisos a la arquitectura productiva heredada. Se restauran las tres migraciones históricas y sus capacidades existentes; no se inventa un sustituto para `users.doctor_id`. Las referencias de reprogramación son proyecciones de eventos, no citas nuevas. El retorno y rechazo siguen en la misma cita y en las tablas de eventos/procedimientos existentes.

MySQL conserva los estados existentes: sin adelanto se representa con CONFIRMADA + pago pendiente + evento; las etapas de retorno se derivan de eventos y revisiones existentes. `appointment_events.event_type` tiene capacidad para los eventos utilizados. El esquema local MariaDB usa InnoDB, FK y unicidad para el vínculo médico. No se ejecutó una nueva inspección ni migración productiva: antes del despliegue se debe verificar `migrations` y equivalencia real del esquema. La migración histórica del vínculo se detiene si la columna existe pero su registro falta, por diseño; no debe forzarse ni revertirse.

El [inventario de archivos](ARCHIVOS-INTEGRACION.md) explica cada cambio. La [matriz técnica y de permisos, identidad y plan de despliegue/rollback](INTEGRACION-MAIN-68.md) forma parte de la revisión. La aprobación del PR no sustituye la validación operativa de cuentas reales ni autoriza un despliegue automático desde este trabajo.

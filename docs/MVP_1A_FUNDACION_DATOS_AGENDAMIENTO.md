# MVP-1A — Fundación mínima de datos de agendamiento

## Estado de validación

> **LOCAL CHECKPOINT. NO APROBADO PARA DEPLOY HASTA VALIDAR MYSQL.**

### VALIDADO

- código y migrations, por revisión;
- SQLite, para compatibilidad general con el esquema heredado;
- 100 pruebas locales verdes.

### PENDIENTE ANTES DE PR / MERGE / DESPLIEGUE

- aplicar las tres migrations en un MySQL sano;
- verificar `RESTRICT` en `site_id`;
- verificar `SET NULL` en `responsible_user_id` y `updated_by_user_id`;
- ejecutar el rollback;
- reaplicar las migrations después del rollback.

La instancia MariaDB local está degradada (`innodb_force_recovery = 1` y tabla de privilegios corrupta) y **no cuenta como evidencia**. No se instaló `doctrine/dbal`, no se repara XAMPP y no se cambian dependencias para sortear la limitación de SQLite: el comportamiento real debe validarlo MySQL.

Ninguna migration se ha ejecutado en producción.

## Objetivo

Agregar solo el delta de datos que los siguientes incrementos de agenda necesitan: sede, responsable de gestión y último modificador. No implementa disponibilidad, pre-reserva, pagos, COSTO 0, adicionales, sobreagenda, RENIEC, UI ni llamador.

Todo el cambio es aditivo y local. No se ejecutaron migrations productivas, no se modificaron migrations históricas, no se eliminaron ni renombraron columnas y no se tocó el enum productivo.

## Migrations creadas

| Archivo | Efecto |
|---|---|
| `2026_09_29_120000_create_sites_table.php` | crea `sites` |
| `2026_09_29_120100_add_scheduling_foundation_to_appointments_table.php` | añade 3 columnas nullable + 1 índice a `appointments` |
| `2026_09_29_120200_add_site_to_doctor_schedules_table.php` | añade `site_id` nullable a `doctor_schedules` |

Las tres se aplican sobre la base heredada existente sin requerir backfill.

## Tablas y campos nuevos

### `sites`

- `id`;
- `codigo` string unique;
- `nombre` string;
- `estado` enum `ACTIVO`/`INACTIVO`, default `ACTIVO`;
- `timestamps`.

Deliberadamente no incluye facturación, almacenes, consultorios, teléfonos ni ningún otro dominio.

`codigo` se conserva porque aporta valor real y verificable: el plan de despliegue exige que el alcance del piloto se configure **sin nombres hardcodeados**, y un código estable permite referirse a la sede desde configuración sin depender de un id autoincremental que difiere entre entornos.

### `appointments` (aditivo)

- `site_id` nullable, FK a `sites`, `ON DELETE RESTRICT`;
- `responsible_user_id` nullable, FK a `users`, `ON DELETE SET NULL`;
- `updated_by_user_id` nullable, FK a `users`, `ON DELETE SET NULL`;
- índice `appointments_site_fecha_doctor_index` sobre `(site_id, fecha_cita, doctor_id)`.

### `doctor_schedules` (aditivo)

- `site_id` nullable, FK a `sites`, `ON DELETE RESTRICT`.

## Decisión: columnas vs tabla 1-a-1

**Elegido: columnas en `appointments`. No se creó `appointment_scheduling_details`.**

El plan anterior proponía esa tabla 1-a-1, pero su contenido previsto era `booking_type`, canal, marca de adicional e idempotencia. **Ninguno de esos campos pertenece a MVP-1A**: el tipo de agendamiento y la adicional son de MVP-6, y la idempotencia de MVP-4. Lo que MVP-1A necesita realmente son tres referencias.

Comparación:

| Criterio | A. Columnas en `appointments` | B. Tabla 1-a-1 |
|---|---|---|
| Joins para filtrar agenda por sede | ninguno | uno obligatorio en la consulta principal |
| Mostrar responsable en lista de citas | directo | join adicional |
| Filas nuevas creadas | 0 | una por cita, incluidas las heredadas |
| Compatibilidad con citas históricas | inmediata, columnas nulas | requiere decidir si se crean filas vacías |
| Complejidad para 3 referencias | mínima | desproporcionada |

Sede y responsable son datos **esenciales** de la cita: participan en el filtro principal de la agenda y en su visualización. Ponerlos detrás de un join sería el "join artificial" que el principio de diseño rechaza. Tres columnas FK tampoco convierten `appointments` en una tabla gigante.

La tabla 1-a-1 no queda descartada: se reevaluará en el incremento que introduzca de verdad `booking_type`, canal e idempotencia, donde el volumen de atributos puede justificarla. No se creó ahora solo porque apareciera en el blueprint.

## Estrategia de sede

CEO Salud opera hoy una sede, y está confirmado que el ERP debe quedar preparado para varias.

- `appointments.site_id` es **nullable** por compatibilidad con las citas históricas y **no se hace backfill**. Asignar una sede a citas pasadas sería inventar certeza histórica no demostrable.
- `ON DELETE RESTRICT` en lugar de `SET NULL`: nular la sede de una cita histórica destruiría silenciosamente el dato de dónde se atendió, que es información de auditoría. La vía soportada es **desactivar** (`estado = INACTIVO`), no eliminar. El scope `Site::activo()` excluye las inactivas sin afectar la historia.
- `doctor_schedules.site_id`: **sí se añadió**, y no por simetría. La disponibilidad es función de sede, profesional y fecha. Sin sede en el horario, el motor de MVP-2 no podría distinguir en qué sede se sirve un bloque y mostraría el mismo bloque en todas las sedes, lo que es incorrecto por definición para una agenda multisede. Añadirla después obligaría a reescribir la consulta central del motor y su índice.

## Estrategia de autoría y responsable

Los tres conceptos quedan separados y no se reinterpreta ninguno:

| Campo | Significado | Origen |
|---|---|---|
| `user_id` | creador histórico | heredado, **sin cambios de semántica** |
| `responsible_user_id` | responsable actual de gestión/producción | nuevo, nullable |
| `updated_by_user_id` | último usuario que modificó la cita | nuevo, nullable |

`updated_by_user_id` se incluyó en este incremento por una razón técnica concreta: la documentación marca como **RIESGO** los locks prolongados al alterar `appointments`. Añadir las tres columnas y el índice en una sola migration evita un segundo `ALTER TABLE` sobre la tabla productiva en un incremento posterior.

Ninguna FK nueva usa `ON DELETE CASCADE`. `SET NULL` garantiza que la cita sobreviva a la eliminación de un usuario. Se evaluó `RESTRICT` y se descartó: bloquearía el borrado de usuarios en la administración existente, lo que cambiaría el comportamiento de un flujo heredado y excede un incremento aditivo.

## Compatibilidad histórica

- Todas las columnas nuevas son nullable; ninguna cita ni horario heredado necesita datos nuevos para seguir siendo válido.
- Sin backfill de ningún tipo.
- El enum `estado_cita` no se tocó, incluidos los valores productivos `PACIENTE_LLEGO` y `REEVALUACION` que no están en la migration versionada.
- Las cuatro horas del llamador siguen sin agregarse al ERP.
- No se interpreta `additional_rate_id` ni `cita_doble` como cita adicional.
- `numero_cita` conserva su restricción unique.
- La suite heredada de Fases 0/1 y de MVP-0 continúa verde.

## Rollback

Cada migration retira exclusivamente lo que creó:

1. `down()` de `doctor_schedules` elimina su FK y la columna `site_id`;
2. `down()` de `appointments` elimina el índice y las tres columnas con `dropConstrainedForeignId`, que retira primero la FK;
3. `down()` de `sites` elimina la tabla.

Ninguna columna heredada participa en el rollback y no existe rollback de datos porque no hubo backfill.

**Limitación verificada:** el rollback no pudo ejecutarse en este entorno. `doctrine/dbal` no está instalado, de modo que Laravel usa DDL nativo, y el SQLite local es 3.33.0, anterior al 3.35.0 que introdujo `ALTER TABLE ... DROP COLUMN`. El rollback es válido en MySQL, donde `DROP COLUMN` es nativo, pero no se ha podido demostrar. Queda pendiente.

## Pruebas

`tests/Feature/Scheduling/SchedulingDataFoundationTest.php` aporta 12 pruebas:

- migrations desde cero crean `sites` con exactamente las columnas mínimas;
- las columnas nuevas existen y las columnas heredadas críticas siguen intactas;
- una cita heredada con las tres referencias nulas sigue siendo válida y legible;
- un horario heredado sin sede sigue siendo válido;
- las relaciones nuevas resuelven y creador, responsable y modificador permanecen distintos;
- una sede puede servir horarios médicos;
- desactivar una sede conserva sus citas y el scope la excluye;
- eliminar una sede nunca elimina citas históricas;
- eliminar al responsable nunca elimina la cita;
- eliminar al último modificador nunca elimina la cita;
- el flag `SCHEDULING_MVP_ENABLED` sigue apagado por defecto y `/scheduling-mvp` devuelve 404;
- MVP-1A no crea ninguna de las 10 estructuras de incrementos posteriores.

La caracterización del CASCADE heredado **no forma parte de este incremento**: vive en `tests/Feature/Legacy/ParentDeletionCharacterizationTest.php` y se documenta en `AUDITORIA_BORRADOS_HEREDADOS.md`, para que MVP-1A no arrastre el problema heredado.

Suite completa: **100 pruebas verdes**, repartidas en 76 previas, 12 de MVP-1A, 10 de caracterización de borrados heredados y 2 de regresión del `DoctorController`.

### Limitación de SQLite que afecta la fuerza de estas pruebas

`Illuminate\Database\Schema\Grammars\SQLiteGrammar::compileForeign()` es un no-op: SQLite solo crea claves foráneas declaradas al **crear** la tabla, no al alterarla. Por tanto, en la suite SQLite las tres FK nuevas **no existen** y sus acciones referenciales `RESTRICT`/`SET NULL` no se ejercitan. Las pruebas de supervivencia de la cita son correctas como garantía observable, pero la acción referencial en sí queda declarada y no verificada.

Las FK heredadas de `appointments` sí existen en SQLite porque se declararon al crear la tabla, así que el CASCADE heredado sí es verificable y está cubierto.

### Validación en MySQL/MariaDB aislado — NO CONCLUIDA

Se buscó infraestructura local con estas conclusiones verificadas:

- Docker: **no instalado**;
- `mysql`/`mysqld`/`mariadb` en PATH: **ausentes**;
- servicios Windows de MySQL o MariaDB: **ninguno**;
- puertos 3306-3309 escuchando: **ninguno**;
- XAMPP: **existe**, con MariaDB 10.4.32 configurado en `127.0.0.1:3308` y detenido.

Se arrancó esa instancia en localhost y se creó la base desechable `erp_mvp1a_tmp`. Se comprobó con una prueba desechable que InnoDB aplica correctamente `ON DELETE SET NULL`.

La validación se **detuvo deliberadamente** al descubrir dos defectos de la instancia:

1. arranca con `innodb_force_recovery = 1`, es decir en modo recuperación tras un fallo;
2. su tabla de privilegios `global_priv` está corrupta (`ERROR 1034`), lo que impide crear el usuario temporal exigido y obligaría a usar `root`.

Una instancia degradada no es evidencia aceptable para afirmar el comportamiento de las claves foráneas, así que las tres migrations **no se han validado** en un motor real. Estado: **PENDIENTE**.

Limpieza realizada: `erp_mvp1a_tmp` eliminada, servidor devuelto a su estado original detenido, credenciales temporales no escritas en ningún archivo, `.env` sin modificar, `erpceosalud` y `llamador` intactas y nunca consultadas por las migrations.

Queda pendiente validar en un MySQL sano y aislado: migrations desde cero, estructura resultante, `RESTRICT` de sede, `SET NULL` de responsable y modificador, rollback y reaplicación.

Nota adicional: el motor local es MariaDB, no MySQL, así que incluso reparado no sustituiría del todo a una verificación en el motor productivo.

## Mejoras adyacentes encontradas

Corregidas ahora, dentro del alcance:

- `BuildsBaselineData` gana `createSite()`, alineado con el estilo de los helpers existentes;
- `User::responsibleAppointments()` distingue explícitamente las citas gestionadas de las creadas, evitando que `appointments()` se lea como ambigua;
- `DoctorController@delete` devolvía `code => 1` en su rama de fallo y ahora devuelve `code => 0`. Es una corrección puntual autorizada, cubierta por `tests/Feature/Admin/DoctorDeactivationTest.php` y detallada en `AUDITORIA_BORRADOS_HEREDADOS.md`.

Antes del piloto:

- `users` no tiene columna de estado ni borrado lógico, así que la "desactivación lógica de un usuario" que pide el requisito **no existe todavía** en el esquema. Se resuelve en MVP-1B, requerido antes del piloto;
- los diez métodos `delete` de los maestros usan `find()` sin comprobar el resultado, de modo que un `id` inexistente falla sin respuesta legible.

Backlog TO-BE:

- instalar `doctrine/dbal` o actualizar SQLite para poder probar rollbacks de columnas en la suite: es un cambio de dependencias y no cabe en un incremento aditivo;
- `doctor_services` no tiene FK ni unique según la matriz de drift;
- la carga duplicada de archivos de rutas en `RouteServiceProvider`/`web.php`.

## Riesgos

1. **CRÍTICO, heredado y no corregido aquí:** las cinco FK de `appointments` se crearon con `ON DELETE CASCADE`. La auditoría de `AUDITORIA_BORRADOS_HEREDADOS.md` demuestra que hoy el riesgo es **latente y no alcanzable por la UI**, porque todos los flujos expuestos desactivan en lugar de eliminar. Se corrige en MVP-1B, no aquí.
2. Las acciones referenciales de las FK nuevas están declaradas pero **no verificadas en ningún motor real**: SQLite no las crea y la instancia MariaDB local está degradada.
3. El rollback no está demostrado en ningún entorno.
4. `site_id` queda nulo en toda la historia; cualquier consulta futura por sede debe tolerar nulos y no asumir que la ausencia significa otra sede.
5. El índice `(site_id, fecha_cita, doctor_id)` se añadió anticipando el motor de MVP-2; su utilidad real debe medirse cuando exista la consulta.

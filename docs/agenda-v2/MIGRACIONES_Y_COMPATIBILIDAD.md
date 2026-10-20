# Migraciones y compatibilidad

Estado vigente de la continuación: [entrega exclusivamente local del20/10/2026](CONTINUACION_20261020.md). PR46 cerrado y sin fusionar; no publicar ni reabrir sin autorización. Los resultados anteriores son antecedentes.

Estado vigente: [cuarta ronda, gestión de citas, procedimientos personalizados y teleconsulta](RONDA_4_DIAGNOSTICO_Y_GUIA.md). Los resultados y decisiones anteriores se conservan como antecedentes.

## Integración de #47–#49: lectura compatible sin transformación

Los eventos REEVALUACION_MANUAL_MARCADA/DESMARCADA permanecen en appointment_events. La última identidad de evento determina la marca directa, con usuario y hora originales; no se importan como procedimientos ni indicaciones. Las nuevas escrituras mantienen ADMISSION_MANUAL_V1 para que un rollback a main dcb9b2b pueda leerlas.

No hay migración nueva por esta integración, ni backfill productivo. Las tres tablas aditivas de v2 siguen siendo requisito previo al código cuando se libere desde main. Una cita con ambos mecanismos se deduplica al consultar, con sus orígenes independientes. No se elimina ni reescribe ningún ledger. La paginación de historia solo añade un endpoint de lectura. [Estrategia completa](INTEGRACION_MAIN_Y_HOTFIXES.md).

## Segunda ronda: sin migración adicional

Se inspeccionaron `appointments.motivo_consulta` y `appointment_admission_tasks.body`: ambas columnas son TEXT. Motivo conserva máximo técnico 2000 y pasa a requerido solo para nuevas citas/reservas. Observación pasa del mínimo de tres al mínimo de un carácter significativo, con máximo 16000 compatible con TEXT/utf8mb4. Históricos nulos permanecen nulos y muestran «No registrado»; no se escribe texto ficticio ni se hace backfill.

Las tres tablas de la primera ronda se reutilizan. Contadores, origen y estado se proyectan defensivamente sin reconciliación masiva. Contrato de cola actualizado: `rows` activa; `groups` separa pendientes/historia. Publicar los JS, Blade, CSS y backend del mismo SHA. Los consumidores internos de la cola están actualizados juntos.

Migración nueva: `database/migrations/2026_10_16_020000_create_appointment_operational_procedures.php`.

Es aditiva: crea appointment_operational_reviews, appointment_requested_procedures y appointment_procedure_events. No altera columnas de appointments, patients, payments, vouchers, indicaciones clínicas ni tareas libres; no modifica filas históricas ni ejecuta backfills. Usa claves foráneas con restrictOnDelete, unicidad de cita, cita/procedimiento y actor/UUID, e índice de estado/ready_at/ID.

Una cita anterior sin procedimientos tiene versión cero, lista vacía y no entra en reevaluación. Una reserva histórica pagada permanece pendiente hasta una nueva operación autorizada de pago real positivo o su confirmación explícita vigente. Leer el feed, documentos o detalle no confirma ni reconcilia dinero. Los procedimientos libres anteriores no se interpretan como solicitudes estructuradas.

La retirada de una solicitud conserva su fila desactivada y su auditoría. Añadirla nuevamente restablece verificación a pendiente para evitar heredar una realización de una solicitud retirada. Se conserva quién solicitó, quién verificó, las fechas y los snapshots antes/después. Las claves no dependen de acentos o de la etiqueta visible.

El código nuevo requiere las tres tablas antes de recibir tráfico: **migración primero, código después** en una futura liberación autorizada. El baseline anterior ignora las tablas nuevas, por lo que un rollback de código puede conservarlas. La migración down se niega a borrar tablas pobladas: no debe utilizarse migrate:rollback para retirar esta función después de su uso. Si las tres tablas están vacías, down puede retirarlas en un esquema desechable o una operación específica autorizada. Nunca eliminar auditoría ni procedimientos para forzar un rollback.

La prueba AgendaV2CompatibilityTest recrea el esquema sin las tablas nuevas, registra una tarea histórica ficticia, aplica up y compara íntegramente appointments, appointment_events, tareas/eventos libres, payments y vouchers. Verifica también que down con datos bloquea sin eliminar historia. Las suites SQLite/MariaDB incluyen los contratos anteriores de catálogo PR #45, asignación profesional/especialidad, importes, documentos privados, OCR, Caja e indicaciones.

Pruebas MariaDB: instancia exclusiva local 11.8.9, puerto 33324, datadir `storage/app/qa-v2/mariadb`. Esquemas ceosalud_qa_agenda_v2_suite y ceosalud_qa_agenda_v2_browser, usuarios limitados a cada esquema. IsolatedAgendaV2Qa rechaza .env, conexiones no locales, puerto/nombre/usuario/directory/version distintos, URL de BD o socket alternativo. CI usa únicamente un servicio desechable, con marcador explícito, host/puerto mapeado, versión y datadir propios del contenedor. El arnés de pruebas reutiliza el detector DDL existente para reinicializar fixtures tras commits implícitos de MariaDB; no cambia aislamiento productivo.

No se ejecutó esta migración en Hostinger ni en una BD productiva. Un respaldo y un ensayo en fixtures no acreditan la existencia, integridad ni restaurabilidad de un respaldo productivo.

## Tercera ronda

No se requiere migración: PATIENT_PROVIDED cabe en purpose varchar(32); se reutilizan relaciones, actor, timestamps, versiones y operations.document_id. No hay transformaciones productivas ni backfill. Los ficheros previos y sus propósitos se conservan; una carga aportada no se acredita como orden ni comprobante. Rollback de código debe conservar compatibilidad documental y bloquear escrituras durante la reversión, porque el reemplazador anterior a esta ronda no preserva el propósito nuevo. Ver el plan vigente.

## Cuarta ronda: una migración aditiva

2026_10_19_040000_add_agenda_v2_attendance_and_preferences: attendance_mode varchar16 defaultPRESENCIAL en appointments; custom_label varchar500 nullable en solicitudes; agenda_professional_preferences con FK usuario/profesional restrictivas, unicidad, favorito, contador y last_selected_at timestamp(6). Clave personalizada c:UUID de38 caracteres cabe en procedure_key40; el texto no se almacena en la clave. No se transforman pagos, indicaciones, eventos o procedimientos anteriores. QA comprobada sobre la BD ficticia existente y migraciones de ambas suites. El ajuste de precisión de un borrador local se limita al helper QA guardado y no es un comando productivo.

No ejecutar down ni revertir a código que desconozca modalidades/claves nuevas con datos de cuarta ronda. Conservar esquema/historia y usar lector/política compatible o bloquear escrituras durante reversión. No hay migración de pagos ni permisos de producción. [Impacto y rollback](RONDA_4_DIAGNOSTICO_Y_GUIA.md).


## Revisión del gestor y reprogramaciones

Sin migraciones nuevas ni backfill. Se reutilizan appointment_events.metadata/motivo; se conservan pagos, comprobantes, tarifas, documentos e indicaciones. El motivo se solicita en el modal/arrastre actuales y se admite en el endpoint sin inventar razones históricas. La anotación es una proyección, no una fila adicional de cita. Ajustes financieros o archivo de contexto al reasignar pacientes siguen pendientes y no crean tablas en esta entrega. [Detalle](GESTION_RAPIDA_Y_REPROGRAMACION.md).

# R05 — reevaluación indicada y referido explícito

Rama codex/mvp-reevaluaciones-indicadas, base PR Draft #6 (6f19a47). PR #5 permanece intacto.

**Regla implementada:** sin indicación médica acreditada se cotiza consulta normal. Con indicación escrita privada y atención origen ATENDIDO + LEGADO/CONFIRMADA del mismo paciente/service_id: fecha programada contra fecha de atención; día0 S/0 sin exoneración discrecional, días1–15 catálogo de reevaluación,16+ catálogo normal. Otro médico es válido para el mismo servicio. Referido explícito del Dr. Julio Quiroz: S/250 normal y S/220 en días1–15 para ambos servicios confirmados; día0 S/0. Origen y responsable se conservan. No inferir referido desde un recargo del catálogo. Para Quiroz y sus dos servicios, seleccionar un recargo de referido sin origen acreditado se rechaza; otros médicos mantienen sus tarifas vigentes.

**Control:** registro exige appointment.reevaluation.indication.record y appointment.view, evidencia física privada y atestación administrativa de orden del médico tratante de la atención origen. No evidencia verbal. Fuente, paciente/service_id inmutables, médico, fechas, documento/hash, referencia operativa, actor y operación quedan auditados. Se revalidan bajo locks ordenados al crear, cobrar y confirmar; una corrección de origen no transfiere la indicación a otra identidad. Un documento puede respaldar órdenes diferenciadas; misma fuente/hash/referencia no admite duplicación con otra UUID. Permiso se crea y asigna expresamente con el editor de roles/permisos existente, previa aprobación de participantes; esta migración no escribe permissions/roles ni asigna facultades. No hay portal médico nuevo. El registro devuelve únicamente indication_id; el reintento no revela el ID de una reserva privada vinculada por otro trabajador.

**Uso único:** indicación se bloquea antes del médico y de crear la cita, se vincula dentro de la misma transacción y queda ligada a un solo appointment_id. Reintento conserva cita. CANCELADO y ATENDIDO no liberan vínculo. Evidencia vinculada/purpose REEVALUATION_ORDER no se lista, descarga, edita ni elimina para usuarios con solo permisos ordinarios de Agenda; requieren capacidad específica y visibilidad de la atención. Subida directa almacena documento e indicación atómicamente y compensa archivos si falla; una imagen nunca registra dinero.

**Límites reales (R05 PARCIAL):** reprogramación conserva la indicación y recalcula cuando no existe pago/ticket. Si el cambio altera el importe y ya hay dinero, ticket o confirmación que perdería el 50%, devuelve422 sin modificar cita/pagos. Hace falta R25 ledger/ajuste auditado y financiación del nuevo precio. Reasignación explícita tras cancelación aún pendiente; no hay reutilización automática. Interfaz mínima por número de indicación, no panel de Nicole. Navegador de estos nuevos controles pendiente, no certificado.

Calendario clínico: CEO_SCHEDULING_TIMEZONE=America/Lima. Los timestamps y app.timezone global no se modifican. Validación del límite futuro probada con UTC14 a las02:00, que todavía corresponde al día13 en Lima. Verificar configuración efectiva de Hostinger antes de publicar.

**Catálogo confirmado por operador / configuración a revisar en staging antes de habilitar:**
```dotenv
CEO_QUIROZ_DOCTOR_ID=1
CEO_QUIROZ_REFERRAL_SERVICE_IDS=1,3
CEO_QUIROZ_TRAUMA_REFERRAL_RATE_ID=2
CEO_QUIROZ_PIE_REFERRAL_RATE_ID=3
```
Sin mapa configurado el referido falla cerrado. Usa tarifa estándar o el referido correspondiente al servicio; el fijo aprobado reemplaza el antiguo recargo y no lo suma dos veces. No combinar con otra campaña/recargo no aprobado. Descuentos explícitos R06 sí se aplican sobre250/220. No se cambian doctor_services ni additional_rates productivos.

**Migración aditiva** 2026_10_13_190000_create_reevaluation_indications: tabla nueva, FK RESTRICT, claves únicas cortas compatibles con MariaDB, columnas nullable pricing_rule/referral en appointments y purpose en appointment_documents. Ningún backfill ni cambio de registros históricos. down bloquea borrado de evidencia/auditoría. Ejecutada solo en QA descartable. Revisión/autorización de ejecución productiva sigue pendiente.

**Pruebas ejecutadas (PHP8.3.35, ficticias):**
- Suite PHP/SQLite completa final:762 tests,5849 assertions,OK.
- Suite MariaDB11.8.9 QA33320 completa final:248 tests,2988 assertions,OK.
- Caso referido+descuento+50%neto+ticket+reintento incluido en ambas suites finales.
- JavaScript:212/212,0 fail/skip.
- Carrera de dos trabajadores con médicos distintos:1/9; repetida3 carreras independientes,3/27,OK. Incluida además en suite MariaDB248.
- Primeros fallos quedaron corregidos: fixtures de tipo documental/defaults; wrapper MariaDB (guard correcto), nombres FK largos; contrato de precio automático anterior; carrera que devolvía500 SAVEPOINT en perdedor. Ahora201/422 y un único vínculo.
- Prueba de documento privado: subida repetida no duplica, lectura permitida y denegada, listado/edición/borrado denegados para permiso ordinario, cotización legítima sin documento privado en respuesta.

**Publicación/rollback pendiente de autorización:** backup BD/archivos/adjuntos con restauración ensayada, flags/mapeos revisados, crear capacidad web y asignar solo participantes aprobados, migraciones aditivas de6/7 y smoke con datos ficticios en staging equivalente. Mantener Clarity intacto; privacidad sigue pendiente antes de datos reales. Durante rollback detener operaciones; conservar tablas/adjuntos/ledger y pagos posteriores, no restaurar automáticamente una BD vieja. Un rollback total a6 pierde restricciones de evidencia médica y política indicada: mantener aplicación en mantenimiento o conservar el parche de privacidad hasta corrección; no reabrir escrituras médicas/financieras con política antigua.

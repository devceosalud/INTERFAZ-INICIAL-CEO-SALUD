# Investigación previa a implementación — 22/10/2026

Base comprobada por fetch: origin/main = 033c3fd60ba7620dd32d82d96f8e21c64fdea759. Referencia preferente: f29019a1f3649149a31af3447e67e009970a7277. PR66: 2f9c6405d2cac2e88e853f7466cfb5958a6fcbf7. La rama de recuperación nace directamente de main; no se fusiona ninguna rama previa.

| Requisito | Main | Referencia avanzada | Decisión y evidencia requerida |
|---|---|---|---|
| Reprogramaciones | Eventos REPROGRAMADA y consulta básica | RescheduleHistory ampliado, duración, médico/sede originales, enlaces, ambas agendas | Recuperar cambios selectivos y RescheduleVisualHistoryTest; verificar disponibilidad original, una sola cita y privacidad |
| Atención/reevaluación | Operación y procedimientos previos; sin identidad clínica integrada | Llegada Comercial, ClinicalAccess, MedicalCareService, panel Admisión, procedimientos, retorno manual, salida separada | Recuperar servicios y contratos; descartar pantalla médica independiente; recorrido completo y denegaciones por rol |
| Colores | PR67: variable JS y fondos completos CSS | PR66 conserva paleta y añade harness/pruebas | Conservar exactamente el bloque PR67 y sus pruebas; recuperar pruebas útiles de PR66 |
| Economía | Guardas y correcciones económicas anteriores | AppointmentEconomicEdit, ExistingAppointmentPricing, snapshot, controles de caja/ventas | Recuperar corrección revisada con rol ADMINISTRADOR, permisos históricos, motivo, antes/después, idempotencia y bloqueos fiscales; probar vías antiguas |
| Sin adelanto | Solo pago positivo o exoneración autorizada | Solo confirmación explícita de exoneración | Implementar excepción distinta, solo ADMISION, CONFIRMADA + pago pendiente, sin pagos/vouchers ficticios, señal amarilla y auditoría; conservar privacidad |

## Dependencias y límites detectados

Las tres migraciones históricas de economía/vínculo/rol médico están ausentes en main y presentes en f29019a1. Deben recuperarse con sus nombres y semántica, sin nuevas migraciones ni categorías. El vínculo users.doctor_id permite ClinicalAccess comprobar cita propia, revocación y cuenta activa; no existe sustituto equivalente demostrado. appointments.doctor_id se conserva. No se reasignan cuentas ni se ejecutan migraciones productivas.

La contingencia UnlinkedDoctorAdmissionFallback es administrativa, desactivada por configuración predeterminada, exige motivo y cesa ante cualquier vínculo médico actual o histórico. No habilita indicaciones médicas ni firma en nombre del profesional. No permite declarar completo el recorrido clínico de Pilar sin cuenta verificada.

No recuperar la página, CSS o JS de Mi agenda médica. Mantener solo APIs históricas necesarias y redirección autenticada a Admisión. La vinculación, si necesita UI, debe integrarse al editor existente, por nombre del profesional. No restaurar docs de despliegue antiguas que proponen otra base. PR66 aporta pruebas/harness; no es fuente de una restauración completa de producto.

Se preservan composer.lock, dependencias, tablas, roles y categorías de las tres migraciones ya aplicadas. Pruebas históricas no prueban esta nueva candidata: se deben volver a ejecutar y documentar fallos o límites locales explícitamente.

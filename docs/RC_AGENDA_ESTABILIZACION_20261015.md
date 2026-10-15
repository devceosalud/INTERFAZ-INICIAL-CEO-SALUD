# RC Agenda — estabilización operativa (15/10/2026)

## Alcance
- Cotización de precio en Agenda: espera del reset asíncrono al seleccionar turno, no quedan consultas obsoletas, timeout 12 s y reintento visible; nunca registrar con precio inválido.
- Cita extendida: conserva dos intervalos en BD y presenta etiqueta en Día/Semana; respuesta de registro incluye id, duración y `duration_slots`.
- OCR: comprobantes JPG/PNG y controles del OCR existente se muestran dentro de **Registrar adelanto** del popup/panel. El operador debe verificar importe y N.º de operación; adjuntar NO acredita dinero. El PDF puede adjuntarse pero no tiene OCR.
- Modal: Enter no ejecuta «Guardar y confirmar» involuntariamente. Solo los botones explícitos eligen RESERVE o CONFIRM.
- Roles: etiquetas y descripciones humanas para permisos Spatie, sin cambiar su identificador persistido, casillas marcadas ni roles asignados.

## Regla de negocio protegida
Las reservas privadas pendientes con `responsible_user_id` asignado solo son visibles para el comercial responsable; cuando no hay responsable, el creador conserva visibilidad. **No modificar esta restricción**. No se incorporan cambios del PR #21.

## Validaciones aisladas antes de producción
1. Comprobar sintaxis y tests Node de los cambios JS:
   `node --check public/js/scheduling/agenda.js`
   `node --check public/js/scheduling/agenda-operational-workspace.js`
   `node --test tests/JavaScript/agenda-quote-race.test.js tests/JavaScript/agenda-extended-visibility.test.js tests/JavaScript/agenda-modal-explicit-actions.test.js tests/JavaScript/agenda-payment-ocr-placement.test.js`
2. `php -l` en los PHP modificados y pruebas de PHPUnit **solo con BD efímera dedicada a pruebas**:
   `php artisan test --filter=AgendaDoubleDurationTest`
   `php artisan test --filter=PermissionDisplayTest`
   `php artisan test --filter=AppointmentVisibilityTest`
3. Visual/UI en ambiente controlado: elegir médico/servicio/hora, recibir precio oficial (no S/0.00 provisorio), guardar reserva sin pago con mode RESERVE; para confirmación debe mantenerse adelanto >=50% o exoneración autorizada. No registrar dinero ficticio como cobrado.
4. Cita extendida: marcar dos intervalos desde popup; verificar HTTP 201, ID y `duration_slots=2`; luego vista Día/Semana y continuación. Revisar superposiciones: una reserva sin pago no bloquea del mismo modo que una cita confirmada.
5. Pago por Yape/Plin/transferencia: cargar imagen ficticia, botón OCR, sugerencias editables, confirmación humana. Para dinero real debe cumplirse Caja y conciliación. Comprobar que no se registre un pago por mera carga de imagen.
6. Administración > Roles > Asignar permisos: comprobar etiquetas para COMERCIAL/ADMISION, que casillas y ids no cambian. **No guardar cambios de rol solo para probar el render**.
7. Probar timeout/422 de `POST /scheduling-mvp/agenda/quote`: mensaje + reintento; sin habilitar Guardar con precio desconocido.

## Preparación y rollback
- El código productivo se valida por SHA en Hostinger; no asumir igualdad con laptop ni GitHub.
- Confirmar respaldo de archivos y BD, ausencia de operaciones sensibles activas, condición de auto-despliegue deshabilitado y existencia de rollback viable.
- Publicar únicamente tras pruebas/PR aprobado y Redistribuir controlado.
- Sin migraciones ni Composer nuevo. Rollback de código por commit anterior; **no migrar ni revertir DB**. Cualquier pago registrado durante QA requiere conciliación independiente.

## Pendiente deliberado
**Paleta de semaforización de Agenda** (disponible, llena, reserva, programada, confirmada, adicional, fuera de horario): esperar colores aprobados por CEO Salud; no rediseñar paleta global.

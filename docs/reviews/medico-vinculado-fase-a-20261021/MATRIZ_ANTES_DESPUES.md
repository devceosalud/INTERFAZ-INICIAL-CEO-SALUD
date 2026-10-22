# Matriz ANTES vs. DESPUÉS

ANTES = a8bd76d8e10090decfde56c7402be585da723fcf, no el estado de Hostinger. DESPUÉS = este incremento local. La comparación JUnit conserva los 1200 identificadores de casos anteriores; ninguno fue retirado. Los grupos se solapan y sus cantidades no se suman. Las pruebas comprueban contratos concretos, no prueban la ausencia universal de cualquier defecto en producción.

| Funcionalidad | ANTES: casos | DESPUÉS: casos | Resultado |
| --- | ---: | ---: | --- |
| 1. Crear y guardar citas | 105 | 116 | SIN REGRESIÓN DETECTADA |
| 2. Editar, confirmar, cancelar y reprogramar | 61 | 61 | SIN REGRESIÓN DETECTADA |
| 3. Disponibilidad, ocupación y horarios | 148 | 148 | SIN REGRESIÓN DETECTADA |
| 4. Crear, editar y desactivar médicos | 17 | 19 | SIN REGRESIÓN DETECTADA |
| 5. Crear, editar, activar y desactivar usuarios | 21 | 40 | SIN REGRESIÓN DETECTADA |
| 6. Roles y permisos existentes | 56 | 56 | SIN REGRESIÓN DETECTADA |
| 7. Perfiles actuales y visibilidad | 38 | 64 | SIN REGRESIÓN DETECTADA |
| 8. Adelantos, descuentos, pagos y Caja | 100 | 100 | SIN REGRESIÓN DETECTADA |
| 9. Llegada, procedimientos y reevaluación | 41 | 56 | SIN REGRESIÓN DETECTADA |
| 10. Históricos y usuarios sin vínculo | 106 | 127 | SIN REGRESIÓN DETECTADA |

## Pruebas y funciones exactas por módulo

- **1. Crear y guardar citas**: `MedicalAccessCompatibilityTest`, `LegacyRegistrationParityTest`, `MvpAllRolesFlowTest`, `OperationalRegistrationTest`, `RegularReservationExclusivityTest`.
- **2. Editar, confirmar, cancelar y reprogramar**: `AgendaSafeQuickEditTest`, `AgendaV2ReversalTest`, `LegacyGuardsMvpFlowTest`, `RegularReservationExclusivityTest`, `RescheduleVisualHistoryTest`.
- **3. Disponibilidad, ocupación y horarios**: `DoctorAvailabilityEndpointTest`, `DoctorAvailabilityEngineTest`, `DoctorScheduleOverlapAuditTest`, `DoctorScheduleSafetyTest`, `DoctorScheduleWorkspaceTest`, `LegacyScheduleContractTest`, `RegularReservationExclusivityTest`, `ScheduleCalendarRangeAndDurationTest`, `ScheduleConfigurationTest`, `ScheduleOverlapIntegrityTest`, `ScheduleAuthorizationTest`.
- **4. Crear, editar y desactivar médicos**: `DoctorCatalogIdentityCompatibilityTest`, `DoctorDeactivationTest`, `ActiveDoctorServiceCatalogTest`, `DoctorServicePricingTest`.
- **5. Crear, editar, activar y desactivar usuarios**: `WorkerAccessTest`, `MedicalAccessCompatibilityTest`, `UserDoctorLinkTest`, `UserEditorSessionTest`.
- **6. Roles y permisos existentes**: `MvpGuardNormalizationTest`, `MvpPermissionBootstrapTest`, `SchedulingMvpFoundationTest`, `MvpRoleEditorTest`, `ProvisionalRoleMatrixTest`.
- **7. Perfiles actuales y visibilidad**: `MedicalAccessCompatibilityTest`, `AppointmentVisibilityTest`, `MvpAllRolesFlowTest`, `VerifiedMedicalCareTest`, `ProvisionalRoleMatrixTest`.
- **8. Adelantos, descuentos, pagos y Caja**: `CashierSalesAndPaymentsSmokeTest`, `AppointmentSalesPrivacyTest`, `BankPaymentIntegrityTest`, `BankPaymentSchemaTest`, `DoctorServicePricingTest`, `AgendaV2PaymentsTest`, `AppointmentDiscountRegistrationTest`, `AppointmentEconomicEditTest`, `AppointmentPaymentProofsTest`, `PilotAppointmentPaymentTest`, `ManagementPaymentOperationNumbersTest`.
- **9. Llegada, procedimientos y reevaluación**: `AdmissionBoardTest`, `ManualReevaluationTest`, `MedicalIdentityAndReevaluationProjectionTest`, `VerifiedMedicalCareTest`.
- **10. Históricos y usuarios sin vínculo**: `DoctorCatalogIdentityCompatibilityTest`, `WorkerAccessTest`, `MedicalAccessCompatibilityTest`, `UserDoctorLinkTest`, `ParentDeletionCharacterizationTest`, `LegacyGuardsMvpFlowTest`, `LegacyRegistrationParityTest`, `LegacyScheduleContractTest`.

La prueba adicional DoctorCatalogIdentityCompatibilityTest, caso unlinked, también fue ejecutada sobre a8: 1 prueba /15 aserciones. En el incremento se ejecutan ambos casos linked/unlinked. Repite los mismos payloads HTTP de creación/edición/desactivación y comprueba IDs, usuarios y citas históricas inalterados. MedicalAccessCompatibilityTest comprueba reservas para médicos con/sin vínculo, pago real de60, rechazo de doble slot, JSON genérico de usuarios, permisos heredados y cambios de sesión selectivos. UserDoctorLinkTest prueba la migración sobre registros heredados ficticios no vacíos, FK/UNIQUE, restricciones de rollback y preservación de datos.

## Compatibilidad de transiciones y actores

| Acción | ANTES a8 | DESPUÉS | Control / función |
| --- | --- | --- | --- |
| Llegada, llamado y salida administrativa | Actores administrativos autorizados por reglas existentes | Igual; ADMISIÓN registra llegada; médico no adquiere este permiso | AdmissionCareService::perform, sin modificación |
| Iniciar/finalizar por POST scheduling.mvp.admission.care | 403 para transición médica; no existía médico verificado | Sigue403, incluso con permiso genérico de actualizar | AdmissionCareService sin modificación; VerifiedMedicalCareTest::test_legacy_routes_stay_closed_for_medical_changes_even_if_generic_permissions_are_added |
| Cambiar estado clínico por admissionit.appointment.update / admissionit.schedule.update |403 médico en a8 |Sigue403; edición ordinaria sin cambio clínico conserva contrato |LegacyAppointmentMutation sin modificación; misma prueba y LegacyGuardsMvpFlowTest |
| Inicio por entrada médica propia | No disponible |200 solo MEDICO activo vinculado al médico responsable, permiso start, llegada registrada y estado permitido |MedicalCareService::perform; ClinicalAccess::lock; MedicalBoardController::care |
| Finalización por entrada médica propia | No disponible |200 solo propietario con finish y EN_ATENCION; no salida física |MedicalCareService::perform |
| Indicar/retirar reevaluación pendiente | Proyección existente, sin escritor médico autorizado |Propietario con indicate/withdraw, motivo, revisión y auditoría; estado/color intactos |MedicalCareService::perform; AppointmentReevaluationService::indication |
| Seleccionar procedimientos |ADMINISTRADOR conforme a reglas existentes |Igual + MEDICO responsable, permiso procedures.request, atención activa y reglas vigentes de fecha |OperationalProcedureService::mutate, parámetro médico false por defecto |
| Completar procedimientos / envío parcial |Admisión/admin autorizados, condiciones y motivo vigentes |Igual; médico no recibe estos permisos |OperationalProcedureService::mutate |
| Listo automático |Lista no vacía completada según reglas existentes |Igual; sustituye pendiente activo, sin crear otra cita |AppointmentReevaluationService |
| Listo manual EN_ATENCION sin procedimientos |No disponible |ADMISION/ADMINISTRADOR con permisos existentes, indicación activa y cita elegible; no vía Comercial |ManualReevaluationService::eligible /set |
| Marca manual ATENDIDO |Actores heredados, incluido COMERCIAL con sus condiciones |Igual; no se transforma en indicación médica |ManualReevaluationService::canUpdate /eligible |
| Cuenta sin vínculo / otro médico / desactivada |Sin acceso médico |403 en entrada médica; módulos ordinarios mantienen sus reglas |ClinicalAccess::actor /appointment /lock |
| Cambio de rol, desactivación, desvinculación |Controles de usuario existentes; sin identidad médica |Invalidación del actor afectado y revalidación clínica transaccional; otras cuentas intactas |UserDoctorLink::change; UserController::update; MedicalIdentityConcurrencyTest |

Un rol ADMINISTRADOR o ADMISION por sí solo no permite las acciones clínicas nuevas. La cuenta clínica necesita vínculo verificado, MEDICO y sus capacidades. La migración no asigna ese perfil a ninguna cuenta. La comparación se hace contra a8, que ya contenía los bloqueos médicos; no se presenta una acción anteriormente permitida en a8 como bloqueada por este incremento.

## Matriz de permisos mínimos MEDICO

| Capacidad Spatie | Función |
| --- | --- |
| appointment.clinical.view |Consultar únicamente sus citas |
| appointment.clinical.start |Iniciar atención propia |
| appointment.clinical.finish |Finalizar atención propia |
| appointment.clinical.reevaluation.indicate |Indicar pendiente con motivo |
| appointment.clinical.reevaluation.withdraw |Retirar justificadamente la indicación |
| appointment.clinical.procedures.request |Seleccionar procedimientos de su propia atención |

No incluye appointment.update, permisos económicos, gestión de usuarios/roles ni citas de otro médico. Las definiciones y etiquetas están en ClinicalCapability y PermissionDisplay; el editor Spatie existente permite revisarlas. La vinculación administrativa no equivale a asignar estos permisos.

## Color, filtros e invariantes

Los once escenarios médicos de navegador verifican pendiente=true/listo=false, EN_ATENCION y misma presentación antes/después. Después de realizar dos procedimientos verifican pendiente=false/listo=true y el cambio de contadores/filtros; también prueban listo manual sin procedimientos y retirada médica con historial. Admisión no puede modificar la indicación original. Los seis escenarios visuales originales comprueban origen libre, marca histórica roja, reserva posterior coexistente, segundo traslado, destino y separación de KPI/ingresos. No hay REGRESIÓN abierta detectada en las pruebas ejecutadas. La validación operativa en staging con responsables reales sigue siendo requisito de activación, sin emplear datos productivos en esta entrega.

# RC CEO Salud — seguridad y compatibilidad

**NO-GO productivo.** Ver `evidence/rc/final/release-summary.json` para código/hash, resultados y manifiesto. No hubo acceso productivo ni cambios en Clarity. Los datos de QA son exclusivamente sintéticos; navegadores y proveedores externos están bloqueados en el router de pruebas, sin alterar la instalación productiva de Clarity.

## Autorización efectiva

El editor existente se reutiliza; la autorización se reevalúa en cada petición. No existe una excepción financiera general por ADMIN. Se probaron altas de usuarios desde UI, grant/revoke de CREATE, UPDATE, PAY y gestión de horarios, sesión antigua, solicitudes directas, UI previamente abierta y CSRF real. Las operaciones sin permiso no escribieron dinero. La conversión a ADD exige capacidad adicional y confirmación explícita, conserva la misma cita, cotiza de servidor y no cobra ni confirma por sí sola.

Matriz operativa de desarrollo (los grants productivos **no se aplicaron**):

| Operación | Capacidades / política exigida | Resultado probado |
|---|---|---|
| Consultar Agenda/Nicole | MVP_ACCESS + VIEW + visibilidad de cita | Reservas privadas ajenas no revelan identidad ni economía |
| Crear paciente/cita/reserva | CREATE y acceso/visibilidad correspondientes; identidad validada | ADMIN y comerciales autorizados por UI; sin CREATE, 403 |
| Editar nota/datos operativos | UPDATE, visibilidad y versión | Revocar con pantalla abierta produce 403, sin auditoría ni escritura adicional |
| Reprogramar | RESCHEDULE y controles de destino/precio/ocupación | Legado y Agenda conservan historial; ajustes económicos incompatibles se bloquean |
| Cobrar | PAYMENT_SUBMIT + visibilidad + saldo + UUID/banco único | Grant funciona; revoke impide cobro en sesión existente y replay; OCR no registra dinero |
| Confirmar | Capacidad efectiva y acción expresa, mínimo 50% neto, disponibilidad | 20/100 rechazado; 50/100 permite confirmar expresamente |
| ADD / fuera de horario | Capacidad de modalidad separada y reglas financieras | ADD explícito por UI; 50% también aplica a modalidades especiales |
| Exonerar | ZERO_COST_APPROVE + motivo + actor/auditoría | Sin permiso o motivo se rechaza; ADMIN no tiene bypass |
| Gestionar horarios | Rol ADMIN/ADMISION/COMERCIAL + doctor.schedule.manage | 403 por endpoint sin capacidad; grant/revoke dinámico; versiones y locks |
| Ver documentos operativos | appointment.documents.view + visibilidad | 404 uniforme sin permiso/cita ajena; no-store y descarga privada |
| Subir/reemplazar documento | documents.manage + UPDATE + visibilidad, versión y motivo para reemplazo | Archivo anterior retenido pero no descargable por ruta ordinaria; UUID y cadena única |
| Acreditar reevaluación | appointment.reevaluation.indication.record + atención válida/evidencia escrita | Sin indicación no hay descuento; documento privado, uso único concurrente |
| Desactivar/reactivar | Administrador autorizado, motivo, versión de acceso, último admin protegido | Sesión/token revocados; cookie anterior no vuelve a funcionar al reactivar |

Las capacidades exactas y combinaciones se encuentran en los servicios, rutas y suites, no se deducen de botones. Un usuario con varios roles conserva la unión de sus permisos: la prueba sintética revoca una capacidad de su único origen; en producción hay que inspeccionar también roles múltiples/directos.

## Catálogo heredado: evidencia del operador

Seis roles: cinco guards vacíos (ADMINISTRADOR, ADMISION, CAJA, FACTURACION, RECEPCION) y COMERCIAL web. Los 23 permisos son web. No se necesita normalizarlos. Los fixtures reproducen esa inconsistencia exacta y relaciones; plan/apply/rollback con hash preservan IDs y pivotes, rechazan colisiones y son auditados/idempotentes. La reparación de guards y los seis grants ordinarios son operaciones separadas. ADMISION y COMERCIAL ya tienen WITHDRAW y ADD: señalar su efectividad al normalizar; no asumir que guard vacío era una barrera de seguridad. No se ejecuta una devolución mediante WITHDRAW ni se concede refund.execute globalmente.

Los permisos DOC_VIEW/DOC_MANAGE y SCHEDULE_MANAGE se definen sin grants. No se conceden privilegios extraordinarios por migración ni bootstrap. La selección final de roles, exoneración, indicaciones, documentos y horarios sigue siendo responsabilidad nominada del negocio.

## Archivos, privacidad y finanzas

R14 clasifica exclusivamente documentos operativos y conserva versiones. Evidencia médica acreditada y comprobantes enlazados a pagos no pueden sustituirse por el reemplazo genérico. Documentos heredados sin clasificación no se presumen administrativos inocuos; requieren acceso documental. Nicole muestra etiquetas/enlaces autorizados, no diagnósticos ni contenido de historia clínica. Cambiar o revocar acceso revalida el detalle abierto en cada ciclo, aunque las filas no hayan cambiado.

Archivos privados, control MIME/tamaño, deduplicación por hash/banco, revisión humana, pagos separados, saldo autoritativo, rollback transaccional, locks de médico/cita y UUID se verifican por suites y escenarios HTTP. La prueba OCR incluye Yape/BCP/BBVA ficticios, ambigüedad e imagen ilegible. Las pruebas multiproceso incluyen mismo intervalo, adelantos, indicación única, edición de horarios, estados obsoletos y cuentas. No se declara que estas pruebas demuestren ausencia absoluta de vulnerabilidades.

## Hallazgos de dependencias y límites reales

- Se actualizaron **dentro de las restricciones Composer existentes** Guzzle, PSR7, Symfony 6.4, Commonmark, Flysystem y transitivas. `composer.lock` forma parte del RC; no se cambió de versión mayor de Laravel. Plataforma PHP 8.3.35: todos los requisitos evaluados pasan. Autoload optimizado PSR-4 estricto pasa, incluida corrección de mayúscula del namespace `Api/AdditionalRate` para Linux.
- **ALTO / bloqueo productivo:** Laravel 9.52.21 permanece afectado por el aviso CRLF del validador email [GHSA-5vg9-5847-vvmq](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq). El audit final contiene cinco entradas para cuatro avisos únicos (CRLF duplicado por fuente), además de dos paquetes abandonados. También reporta confusión de URL firmada temporal (medio), bypass de validación de archivos (medio) y XSS debug (bajo). No se ha demostrado explotación del ERP ni se declara mitigación completa; requiere remediación/revisión de compatibilidad del framework antes de levantar NO-GO. No se hizo upgrade mayor silencioso.
- **ALTO / bloqueo de privacidad:** Clarity instalado e intacto. Los [términos de Microsoft Clarity](https://clarity.microsoft.com/terms), apartado 1.b.ii, restringen la recopilación de contenido sensible de salud/finanzas. El responsable debe resolver expresamente admisibilidad, páginas, captura de sesión, formularios, documentos y comunicaciones antes de datos reales. Enmascaramiento por sí solo no acredita cumplimiento. Se respeta la instrucción de no modificar su integración.
- **PENDIENTE de equivalencia productiva:** Linux/PHP web/FPM, CSP/WASM, almacenamiento privado, sesiones, límites de archivo/memoria/tiempo y logs. El router de QA exige CSRF pero bloquea egress; no prueba integraciones reales ni privacidad de la instalación productiva.
- **PENDIENTE de QA de navegador:** dos intentos de Chrome con ventana conservaron ambas pestañas como `visible`; no se logró acreditar la pausa/reanudación por ocultación real. Se conserva el fallo de herramienta y la observación; las pruebas unitarias de visibilidad sí pasan. No se falsificó document.hidden para declararlo real.
- Inspección visual móvil: pantallas operativas utilizables y sin desbordamiento de documento a 390 px; tablas con desplazamiento interno. Cabecera heredada compacta presenta texto próximo al menú: observación visual baja, sin cambio de paleta ni rediseño global. Safari/dispositivos físicos y jornada completa móvil no ejecutados.

No se ejecutó PHPStan (no configurado/instalado) ni un escáner de penetración completo. El análisis ejecutado es lint PHP/JS, autoload PSR-4, audit Composer, revisión de cambios y patrones de secretos; sus límites se conservan en QA.
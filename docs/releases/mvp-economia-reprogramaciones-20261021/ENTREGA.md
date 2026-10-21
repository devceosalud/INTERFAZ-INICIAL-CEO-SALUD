# Candidata integrada MVP — 21/10/2026

Implementación local para revisión. No publicada ni desplegada. Esta entrega amplía la release visual; sus documentos originales permanecen como evidencia histórica y no describen la nueva economía.

## Base y alcance

- Rama: `codex/release-mvp-economia-reprogramaciones-20261021`.
- Padre exacto: `6719af57d5fb01af10ed4ae78f02c61ef2565de3`, release visual validada.
- Base productiva previa informada por el responsable: `1f840e90912d2ce8c55ce7502a22a1d582492bc3` (Hostinger, 20/10/2026 17:08). No se ha inspeccionado el servidor; los archivos retenidos y su esquema requieren preflight autorizado.
- Fuente económica consultada: `codex/agenda-v2-correcciones-economicas`, HEAD `e1d113e147c21c545da4c17e6479506d9e393c13`, y su trabajo local existente. Se reutilizaron selectivamente validaciones, bloqueos, cálculo de recargos e instantáneas mínimas. No se fusionó esa rama ni se importaron su circuito de solicitudes, migraciones económicas, evidencias privadas o interruptor de aplicación automática.

La representación de reprogramaciones se conserva: se leen eventos existentes, la marca del origen es histórica, y el intervalo sigue libre cuando no lo ocupa otra cita. No se crean citas ni eventos FullCalendar de ocupación artificiales. Admisión mantiene su sección separada y sus filtros/visibilidad. Sus servicios, controladores, vistas, CSS y JavaScript permanecen idénticos a `6719af5`; únicamente el archivo de rutas compartido incorpora las rutas económicas sin cambiar las anteriores. La regresión de navegador original se ejecutó sin modificar su script.

## Uso y permisos

En **Gestionar cita → Modificar economía de la cita** se edita la tarifa contractual, el descuento fijo/porcentual o el recargo del mismo servicio. **Corregir adelanto** selecciona un pago real y permite rectificar importe, referencia o ambos. El formulario ordinario que crea citas y registra adelantos permanece intacto.

Flujo: editar y fundamentar → revisar comparación calculada por servidor → confirmar → guardar dentro de una transacción → consultar auditoría. Cancelar la confirmación no envía el guardado. La revisión no persiste solicitudes pendientes ni reserva importes. Vence a los diez minutos y está firmada para usuario, cita, propuesta e instantánea; cualquier cambio concurrente exige volver a revisar. El reintento de una operación ya completada conserva su resultado, incluso después del vencimiento.

En **Roles → Actualizar Permiso**, un administrador podrá asignar a Comercial (o a otro rol elegido):

| Permiso web | Etiqueta |
|---|---|
| `appointment.payment.correction.edit` | Corregir adelantos registrados |
| `appointment.pricing.adjust.edit` | Modificar economía de citas existentes |

También se requiere usuario activo, acceso MVP, permiso de ver Agenda y visibilidad efectiva de la cita y del pago. Registrar pagos no concede corregirlos. Los antiguos permisos `request`/`approve` no habilitan esta edición directa; no se crean en esta candidata. No hay aprobador independiente porque el requisito MVP autoriza al propio editor. Ningún rol o usuario recibe las dos capacidades nuevas automáticamente. No cambian RoleController ni su formulario; solo se añaden las definiciones y sus etiquetas al catálogo existente.

## Integridad y reutilización

- `AppointmentEconomicPosition` sigue siendo la fuente del dinero acreditado; se añade lectura bloqueada opcional solo para las ediciones. Los lectores actuales conservan sus consultas por defecto.
- `AppointmentDiscount` conserva las reglas de descuentos y rechazo del 100 %. La tarifa global y el cotizador de nuevas citas no cambian.
- `AdditionalRate` aporta únicamente reglas de catálogo activas y vigentes para la fecha de la cita, de importe fijo o porcentaje; no se inventa un límite general del 20 %. El recargo modifica el precio, nunca registra dinero.
- Se actualizan la misma fila de pago, la cita y, cuando corresponde, el único TICKET interno y su única línea. No se emite ni sobrescribe ningún documento fiscal.
- `appointment_operations` aporta unicidad usuario/UUID y protección frente a reenvíos. `appointment_events` recibe `ECONOMIA_CITA_EDITADA`, con usuario, fecha, motivo, pago, propuesta, instantáneas financieras anterior/posterior y hashes. La instantánea posterior se calcula después de insertar el evento; su payload se completa antes del commit, sin reescribir historia comprometida.
- Las instantáneas almacenan importes, referencias y claves de relación necesarias; no copian nombres, direcciones, notas clínicas, XML, rutas privadas ni respuestas SUNAT. Los datos omitidos se detectan mediante digest. No se incorpora descarga de evidencias ajenas.
- La auditoría económica tiene paginación de 30 y se filtra por capacidad económica o auditoría existente, además de visibilidad. El historial general anterior no expone las instantáneas.

## Escenarios soportados y límites

Verificados con dinero ficticio: A, precio120/pago50 → mismo pago60/saldo60; B, precio120/pago60/descuento20 → precio100/saldo40; C, precio120/pago60/recargo válido20 → precio140/saldo80. También cambio de referencia, tarifa, descuento porcentual, retirada/rectificación de descuento y selección individual entre varios pagos de un único ticket. Una reserva sin pago puede cambiar precio sin confirmarse ni alterar su estado clínico.

La edición directa se bloquea con explicación cuando hay turno cerrado/congelado, comprobante fiscal o artefactos SUNAT, documento derivado, atribución ambigua, anticipo heredado sin comprobante, crédito/devolución, ticket con varias líneas, comisión, inconsistencia tributaria, tratamiento especial, atención iniciada o exceso de dinero sobre el nuevo precio. No se crean devoluciones, créditos ni movimientos negativos para evitar esos controles. El registro original queda intacto. Un comprobante privado de pago previamente revisado solo se admite si conserva su integridad; no se reemplaza.

Decisiones fuera del MVP: servicios adicionales independientes necesitan su propia documentación económica; no se transforman en recargos. No se aplica un recargo libre arbitrario. Si un recargo histórico carece de desglose verificable, modificarlo exige declarar expresamente la tarifa base mediante corrección contractual; no se deduce usando la tasa actual. Los casos fiscales/cerrados requieren el flujo financiero correspondiente, no una solicitud pendiente implementada aquí. La suficiencia comercial de las reglas actuales y la asignación concreta de permisos deben decidirlas los responsables.

## Base de datos y dependencias

No se crean tablas ni columnas. Se añade una migración de datos idempotente que define dos permisos Spatie y limpia su caché, sin asignarlos. Se reutilizan las tablas de eventos, operaciones, citas, pagos, tickets y líneas ya existentes en la base anterior. No se modifica ningún archivo de migración histórico. La prueba de migración ejecuta `up` dos veces y `down`, verifica ausencia de concesiones y asigna los permisos desde el editor real de roles en SQLite y MariaDB.

No cambian `composer.json`, `composer.lock`, dependencias JavaScript ni configuración. No se requiere compilación de assets; se entrega el JS servido actualmente. Tres intervenciones pequeñas en componentes compartidos son necesarias para coherencia concurrente: cierre de Caja bajo bloqueo/recuento, proyección económica opcionalmente bloqueada y rechazo del carrito obsoleto al adquirir el bloqueo de venta. Se detallan con pruebas en `ARCHIVOS.md`.

Consultar `RESULTADOS.md`, `DESPLIEGUE_Y_ROLLBACK.md`, manifiestos y evidencias. La candidata local no implica validación de Hostinger ni autorización de publicación.

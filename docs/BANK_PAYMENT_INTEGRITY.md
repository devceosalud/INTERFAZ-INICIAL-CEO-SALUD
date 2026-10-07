# Integridad de pagos bancarios del piloto

## Alcance

Corrección de dos blockers: banco/billetera descartado y doble registro bancario entre tickets.
Agenda envía payment.method/origin/operation; el recorder mapea a metodo_pago/entidad_origen/numero_operacion.
Payment no admitía origen/destino en fillable, y la BD heredada local tampoco tenía esas columnas.
Se reutilizan los nombres y semántica de la migration histórica, con una migration aditiva condicional.
No se reconstruye Caja/Sales, se cambia atribución multilínea, ni se modifica RETIRO o crédito.

## Identidad canónica

SHA256 de [medio, entidad canónica, operación]. Medio/operación: trim y mayúsculas; se conserva
la puntuación interna y los ceros. Entidad: espacios normalizados, mayúsculas/acentos normalizados;
alias explícitos BCP/Banco de Crédito del Perú, BBVA/Continental y Interbank.
Otras entidades usan su nombre normalizado. No incluye actor, monto, voucher ni UUID de request.

YAPE y PLIN identifican la billetera por el propio medio: cambiar el banco de origen no crea otra identidad.
El origen informado se conserva como metadata. TARJETA/TRANSFERENCIA requieren entidad; igual número
en bancos distintos puede representar otra operación. Todo no-efectivo requiere operación.
EFECTIVO conserva operación e identidad NULL; no se fuerza una referencia bancaria ficticia.
El registro humano no verifica automáticamente el banco/proveedor; no hay OCR ni conciliación bancaria.
Una referencia legítimamente reutilizada en la misma entidad/medio requiere revisión humana; no se usa
created_at como fecha del movimiento bancario ni se reinicia la unicidad diariamente.

## Protección y legado

Payment calcula la identidad centralmente para todos los escritores Eloquent, incluido el writer
heredado de Admisión, Sales y el cash context automático. UNIQUE payments_bank_key_unique sobre
payments.bank_identity_key (CHAR64 nullable) arbitra concurrencia entre actores/tickets.
La comprobación previa es solo informativa. El conflicto SQL se convierte en ValidationException/422,
sin IDs, vouchers ni información de otro paciente. Las transacciones exteriores revierten el segundo
ticket/pago/contexto. appointment_operations/request_key conserva su idempotencia independiente.

No se backfillean ni modifican pagos históricos. Si una operación histórica identificable coincide
y la entidad/medio es incierto, se rechaza su reutilización para revisión. Registros sin número no
permiten inferir identidad: conservan lectura y necesitan conciliación para cualquier atribución.
Datos legados incompletos no adquieren una identidad inventada. Dinero multilínea continúa rechazado
en los flujos de atribución automática. Crédito RETIRO usa aplicaciones de crédito y no crea Payment.

## Schema y despliegue

2026_10_07_140000_add_bank_identity_to_payments_table.php agrega origen/destino solo cuando faltan,
y clave/índice nuevos sin alterar Payments existentes. Nombre de índice corto, 64 hex caracteres:
sin índice compuesto largo ni identidad dependiente de collation MySQL.
Aplicar schema antes del código consumidor, con escritores financieros pausados durante transición;
código antiguo puede omitir la clave, por lo que no debe coexistir escribiendo con el nuevo.
No ejecutar migrate general. down bloqueado con claves reales; nunca elimina metadata origen/destino
que podría preexistir. SQLite anterior a 3.35 rechaza el DROP COLUMN antes de modificar índices.

LOCAL: MariaDB 10.4.32, ERPCEOSALUD en 127.0.0.1:3308. Solo migration individual, sin producción.
Las 8 filas previas y suma 525.03 conservaron exactamente sus atributos anteriores; sin backfill.
QA A/B/C/D y crédito en transacción revertida. Carrera real con dos procesos/actores/tickets:
ambos pasaron la comprobación previa; uno creó Payment y otro recibió 422 por UNIQUE.
Fixtures de carrera eliminadas selectivamente; huellas originales iguales. .env/roles reales intactos.

## Cobertura

BankPaymentIntegrityTest: retry idempotente, duplicado entre actores/tickets, saldo/semáforo intactos,
alias/bancos distintos, wallets sin bypass por banco de origen, efectivo, referencias obligatorias,
legacy NULL, conflicto SQL convertido y crédito sin otro Payment.
BankPaymentSchemaTest: drift de schema heredado, preservación de filas, NULL legado y rollback protegido.
Regresión Sales/Scheduling/Security/Baseline y casos OperationalRegistration/Withdrawal.
Dos assertions heredadas del commit de ayuda contextual 607cda8 se ajustan a sus textos actuales:
privacidad usa un nombre ficticio inequívoco en vez de la palabra genérica Paciente; Horarios mantiene
su assertion de solo lectura/data-can-manage. No se cambian plantillas ni comportamiento UX.

Regresión final: Sales 22 passed; Scheduling 335 passed / 1 skipped conocido SQLite3.33;
Security 45 passed; Baseline 16 passed; filtro financiero específico 40 passed.
Integración financiera mínima: banco de tarjeta en Sales/alta heredada y presentación del422 operativo.
Agenda/RETIRO/Factiliza/documentos no cambian. Se ejecutan todos los tests JavaScript.
OTROS es únicamente fallback heredado del writer, no una opción del formulario ni del recorder:
lectura histórica conservada; un pago nuevo debe indicar un medio soportado, sin inventar efectivo.

JavaScript completo: 162 passed, 0 fallos. git diff --check del lote limpio;
whitespace heredado protegido conservado y excluido del commit.

# Arquitectura técnica TO-BE del ERP CEO Salud

## 1. Propósito y alcance

Este documento propone la arquitectura técnica objetivo del ERP CEO Salud a partir del Blueprint funcional aprobado provisionalmente. No define código, clases, endpoints, tablas ni migrations definitivas.

La propuesta busca evolucionar el sistema actual sin una reescritura disruptiva, conservando datos históricos y permitiendo entregas graduales verificables.

### Clasificación de afirmaciones

- **Confirmado en código:** observable en archivos versionados del commit base; no garantiza que producción tenga el mismo DDL o comportamiento.
- **Confirmado por negocio:** requisito o regla expresamente validada por Rodrigo.
- **Propuesta técnica:** decisión recomendada para cumplir los requisitos con seguridad y mantenibilidad.
- **Pendiente de negocio:** aspecto que requiere definición empresarial antes de cerrar el diseño.
- **Pendiente de validación productiva:** hecho que debe contrastarse con producción mediante mecanismos seguros y de solo lectura.

## 2. Punto de partida AS-IS

El sistema actual es un monolito Laravel 9 con Blade, Livewire 2, jQuery/AJAX, Eloquent y MySQL en producción. La autenticación es de sesión y la autorización usa roles/permisos de Spatie, con una matriz provisional aplicada en Fase 1.

El ERP concentra en una misma aplicación:

- identidad y usuarios;
- pacientes y responsables;
- médicos, especialidades, servicios y horarios;
- citas y marcadores del llamador;
- ventas, caja, pagos y comprobantes;
- catálogos de productos/servicios;
- consultas RENIEC/RUC y preparación parcial para SUNAT.

Los principales límites técnicos actuales son:

- entidades con varias responsabilidades, especialmente `appointments`, `vouchers` e `items`;
- lógica de negocio dentro de controladores y componentes Livewire extensos;
- duplicación conceptual entre servicios y artículos vendibles;
- estado financiero repartido entre citas, comprobantes y pagos;
- stock representado como saldo mutable sin libro de movimientos;
- ausencia de un dominio clínico completo;
- trazabilidad y auditoría de negocio insuficientes;
- integraciones externas acopladas al flujo síncrono o incompletas;
- modelo todavía centrado en una sola sede.

## 3. Estilo arquitectónico propuesto

### 3.1 Monolito modular evolutivo

**Propuesta técnica:** mantener Laravel como un monolito modular durante la evolución inicial. No se recomienda dividir prematuramente el ERP en microservicios.

Motivos:

- el equipo puede desplegar una única unidad y conservar el flujo actual de Hostinger;
- los dominios comparten transacciones importantes;
- el sistema necesita primero separar responsabilidades y fuentes de verdad;
- una separación lógica bien aplicada permite extraer integraciones o procesos asíncronos más adelante sin imponer ahora complejidad operativa.

Los módulos deben tener límites internos explícitos y comunicarse mediante acciones de aplicación, contratos y eventos de dominio controlados. Compartir la misma base de datos no autoriza a un módulo a modificar directamente las entidades internas de otro.

### 3.2 Capas lógicas

```text
Interfaces
  HTTP / Blade / Livewire / API / comandos programados
        |
Aplicación
  casos de uso, autorización, orquestación, DTO de entrada/salida
        |
Dominio
  reglas, estados, invariantes, políticas y eventos de negocio
        |
Infraestructura
  Eloquent, transacciones, colas, correo, archivos e integraciones externas
```

Responsabilidades:

- **Interfaces:** reciben solicitudes y presentan respuestas. No deciden reglas clínicas, financieras ni de inventario.
- **Aplicación:** coordina un caso de uso completo, valida permisos y límites transaccionales, e invoca reglas del dominio.
- **Dominio:** protege invariantes y transiciones válidas sin depender de una pantalla concreta.
- **Infraestructura:** implementa persistencia, conectores, transporte asíncrono y observabilidad.

No se propone crear repositorios genéricos para cada modelo. Se usarán abstracciones solo cuando exista una frontera real, una consulta compleja reutilizable o la necesidad de sustituir infraestructura.

## 4. Módulos técnicos objetivo

| Módulo | Responsabilidad técnica | Fuente de verdad principal | Dependencias permitidas |
|---|---|---|---|
| Identidad y seguridad | Personas, trabajadores, usuarios, acceso y acciones sensibles | Identidad, vínculo laboral y autorizaciones | Organización, auditoría |
| Organización | Sedes, consultorios, cajas, puntos de emisión y almacenes | Estructura física/operativa | Auditoría |
| Personal | Profesionales, registros, especialidades y oferta profesional | Perfil profesional y habilitaciones | Identidad, organización, catálogos |
| Pacientes | Identidad asistencial, responsables y contacto | Paciente y relaciones de responsabilidad | Identidad, auditoría |
| Agenda | Horarios, bloqueos, disponibilidad, pre-reservas, citas y reconsultas | Reserva del recurso/horario | Pacientes, personal, organización, comercial |
| Atención | Llegada, espera, llamado, consulta, finalización y atención clínica | Estado operativo de la atención | Agenda, pacientes, personal, organización |
| Historia clínica | Registro longitudinal, episodios, notas, diagnósticos, órdenes, recetas, resultados y adendas | Contenido clínico cerrado/validado institucionalmente | Atención, pacientes, personal, auditoría |
| Comercial | Catálogo, tarifas, ventas, cargos/obligaciones y descuentos | Condición económica acordada | Pacientes, personal, organización |
| Pagos | Recepción, evidencia, verificación, aplicación, devolución y reverso | Movimiento de valor y su aplicación | Comercial, caja, auditoría |
| Caja | Turnos, custodia de efectivo, movimientos, arqueo y diferencias | Existencia física por caja/turno | Organización, pagos, auditoría |
| Inventario | Almacenes, movimientos y disponibilidad de productos | Libro de movimientos de stock | Organización, catálogo, comercial |
| Facturación | Documentos tributarios, series, correlativos, envíos y correcciones | Documento fiscal y su ciclo SUNAT | Comercial, pagos, organización, integraciones |
| Integraciones | Adaptadores RENIEC, RUC, SMS, correo, SUNAT y llamador | Registro de intercambios, no datos maestros | Dominios consumidores, auditoría |
| Auditoría | Evidencia inmutable de acciones sensibles y acceso clínico | Evento de auditoría | Todos |
| Reportes | Proyecciones y consultas sin apropiarse de datos fuente | Lecturas derivadas | Todos, con acceso de solo lectura |

## 5. Reglas de dependencia

1. Un módulo propietario es el único que modifica su agregado o fuente de verdad.
2. Las pantallas no escriben directamente en varios módulos para completar un flujo.
3. Las acciones que afectan dinero, correlativos, reservas o stock se ejecutan en transacciones explícitas.
4. Los efectos externos se producen después de confirmar la transacción local.
5. Los reportes leen proyecciones o consultas dedicadas; no incorporan reglas de escritura.
6. Los datos clínicos no se reutilizan como texto libre operativo fuera del dominio clínico.
7. Las integraciones no determinan por sí solas el estado definitivo de una entidad interna.

## 6. Identidad, autorización y auditoría

### 6.1 Separación conceptual

**Confirmado por negocio:** deben mantenerse separados `Persona`, `Trabajador`, `Usuario` y `Profesional de salud`, aunque estén relacionados.

**Propuesta técnica:** `Persona` concentra identidad natural; `Trabajador` representa su relación operativa con CEO Salud; `Usuario` representa credenciales y estado de acceso; `Profesional de salud` agrega atributos regulatorios y clínicos. Un paciente también puede referenciar a una persona, sin convertir automáticamente al paciente en usuario o trabajador.

### 6.2 Autorización por capacidades

La autorización objetivo debe expresar capacidades como:

```text
dominio.recurso.acción
```

Ejemplos conceptuales: leer, crear, modificar, eliminar, aprobar, anular, reabrir, verificar pago o emitir documento fiscal.

Los roles agrupan capacidades, pero la comprobación se realiza en backend mediante políticas o acciones autorizadas. La visibilidad del menú sigue siendo solo una adaptación de interfaz.

Las acciones especialmente sensibles requieren, además del permiso:

- motivo obligatorio;
- identidad del ejecutor;
- fecha/hora;
- estado anterior y posterior relevante;
- referencia al objeto afectado;
- aprobación separada cuando la regla de negocio lo determine.

### 6.3 Auditoría de negocio y logging técnico

Se separan dos conceptos:

- **auditoría de negocio:** evidencia durable y consultable de accesos o cambios sensibles;
- **logging técnico:** diagnóstico de errores, rendimiento e infraestructura.

El log técnico no debe contener historias clínicas, documentos de identidad completos, evidencias de pago ni secretos. La auditoría debe aplicar mínimo acceso y retención definida.

## 7. Consistencia, concurrencia e idempotencia

### 7.1 Operaciones transaccionales críticas

Deben diseñarse como unidades atómicas:

- confirmar o liberar una pre-reserva;
- confirmar cita con adelanto o excepción autorizada;
- registrar, verificar y aplicar un pago;
- abrir/cerrar caja y registrar diferencias;
- asignar un correlativo fiscal;
- registrar venta y sus líneas;
- descontar o revertir stock mediante movimientos;
- cerrar, adicionar o reabrir excepcionalmente un registro clínico.

La verificación de un pago y la autorización de una excepción al adelanto son capacidades distintas: comprobar que el dinero existe no habilita a aprobar una excepción, y aprobar una excepción no convierte un pago no verificado en válido.

### 7.2 Concurrencia

Se requerirán bloqueos o restricciones de unicidad en los puntos de competencia:

- un recurso/horario no puede quedar confirmado para dos citas incompatibles;
- una evidencia u operación de pago no debe acreditarse dos veces;
- un correlativo no debe repetirse por punto de emisión, tipo y serie;
- un turno de caja no debe quedar abierto de forma incompatible;
- un movimiento de stock no debe aplicarse dos veces.

La estrategia exacta se definirá con MySQL aislado antes de implementar, porque SQLite no reproduce totalmente el bloqueo y la concurrencia productivos.

### 7.3 Idempotencia

Los comandos repetibles, reintentos de cola y callbacks externos deben llevar una clave idempotente o identidad natural verificable. Un segundo intento debe devolver el resultado ya registrado o quedar como intento separado, nunca duplicar el efecto económico, fiscal, clínico o de stock.

## 8. Procesos síncronos, asíncronos y programados

### Síncrono

Se mantiene síncrono lo necesario para confirmar inmediatamente una regla local: reservar, registrar una atención, cerrar un registro clínico, crear una venta o aplicar un pago.

### Asíncrono

Son candidatos a cola:

- envío de correo o SMS;
- envío y consulta de estado SUNAT;
- generación de representaciones PDF/XML;
- notificación al llamador;
- actualización de proyecciones pesadas;
- reintentos de integraciones.

**Propuesta técnica:** registrar primero el hecho interno y luego publicar el trabajo externo mediante un patrón de salida transaccional o un registro equivalente. Así se evita que una caída entre la confirmación local y el envío pierda el evento.

### Programado

Son candidatos al scheduler:

- expirar pre-reservas vencidas;
- reintentar integraciones según política;
- detectar procesos atascados;
- conciliaciones y alertas operativas;
- tareas de retención o mantenimiento autorizadas.

**Confirmado por negocio:** la pre-reserva usa 15 minutos por defecto, debe ser configurable, expira automáticamente y una extensión requiere identidad, motivo y auditoría. Quedan pendientes el máximo/número de extensiones y el desempate detallado ante solicitudes simultáneas.

## 9. Integraciones externas

Cada integración debe quedar detrás de un contrato interno con adaptadores separados para producción, pruebas y modo deshabilitado.

| Integración | Contrato interno esperado | Regla de seguridad |
|---|---|---|
| RENIEC/DNI | Consultar identidad y devolver resultado normalizado | Proveedor sustituible, timeout, error controlado, fallback manual y no logging sensible |
| RUC | Consultar contribuyente y estado | Proveedor sustituible, timeout, error controlado y captura manual trazable |
| SMS | Enviar mensaje y consultar resultado | Plantillas controladas, consentimiento y trazabilidad mínima |
| Correo | Enviar notificación/documento | Cola, plantillas, destinatario validado y entorno de pruebas seguro |
| SUNAT | Generar/enviar/consultar documentos y correcciones | Idempotencia, certificados protegidos, artefactos y respuestas auditables |
| Llamador | Publicar y consultar transiciones de atención | Contrato versionado; ERP conserva la fuente de verdad del flujo clínico-operativo |

Ninguna integración real debe ejecutarse en pruebas. Los adaptadores de test usarán fakes o stubs deterministas.

El ERP es la fuente canónica del estado del flujo de atención. El llamador consume o propone transiciones mediante un contrato versionado; no puede sobrescribir por sí solo el estado interno. El contrato definitivo queda condicionado a la auditoría posterior del repositorio `LLAMADOR-PACIENTE-CEO`.

### 9.1 Ciclo fiscal conceptual

```text
PENDIENTE -> GENERADO -> FIRMADO -> ENVIADO -> ACEPTADO
                                         `-> RECHAZADO -> CORREGIDO
ACEPTADO -----------------------------------------------> ANULADO
```

Las transiciones exactas dependen de la normativa y del mecanismo aplicable. Rechazar, corregir o anular exige una capacidad financiera/administrativa específica; operar una caja no concede automáticamente ese permiso.

## 10. Observabilidad y manejo de errores

La respuesta técnica objetivo distingue:

- error de validación del usuario;
- conflicto de negocio o concurrencia;
- falta de autorización;
- dependencia externa no disponible;
- error interno inesperado.

Cada operación relevante debe tener un identificador de correlación. Los errores externos conservan proveedor, tipo de operación, número de intento, tiempos y resultado sanitizado. Los secretos y datos personales sensibles se redactan antes de registrar.

Indicadores mínimos sugeridos:

- pre-reservas expiradas y conflictos de horario;
- pagos pendientes de verificar o aplicar;
- diferencias de caja;
- correlativos y documentos fiscales pendientes/rechazados;
- trabajos de cola fallidos;
- integraciones con errores repetidos;
- accesos y reaperturas clínicas excepcionales;
- movimientos de inventario rechazados o inconsistentes.

El acceso clínico de emergencia o *break glass*, si se habilita, debe requerir motivo, elevar alertas y producir auditoría reforzada; no equivale a un permiso permanente.

## 11. Seguridad de datos clínicos y personales

**Confirmado por negocio:** existirá una historia clínica longitudinal única por paciente, aunque el paciente sea atendido en más de una sede. Cada atención conserva sede, consultorio, profesional, servicio y fecha/hora.

Controles propuestos:

- mínimo privilegio y separación de funciones;
- cifrado de transporte y protección de respaldos;
- acceso clínico auditado;
- bloqueo de edición tras el cierre;
- correcciones mediante adenda, sin reescribir el contenido original;
- reapertura solo como excepción autorizada y auditada;
- archivos clínicos fuera de rutas públicas y con descarga autorizada;
- políticas de retención y exportación por definir con asesoría legal.

No se incorpora firma criptográfica clínica en el alcance inicial. El cierre tendrá autoría, fecha/hora y trazabilidad aplicativa.

## 12. Despliegue y configuración

La aplicación debe conservar configuración por entorno y nunca versionar secretos. La evolución requerirá:

- entornos separados para desarrollo, pruebas, preproducción y producción;
- servicios externos deshabilitados o simulados fuera de producción salvo pruebas expresamente controladas;
- health checks que no expongan información sensible;
- workers y scheduler supervisados cuando se introduzcan procesos asíncronos;
- feature flags para activación gradual de lecturas/escrituras nuevas;
- migraciones compatibles hacia adelante y despliegues en dos pasos cuando cambie el esquema.

## 13. Decisiones técnicas adoptadas provisionalmente

1. Monolito modular Laravel, no microservicios en esta etapa.
2. Separación por dominios y casos de uso, sin repositorio genérico obligatorio.
3. Eloquent continúa como infraestructura de persistencia durante la evolución.
4. Transacciones explícitas para reservas, dinero, caja, correlativos, stock y cierre clínico.
5. Integraciones mediante contratos/adaptadores, con efectos externos desacoplados.
6. Auditoría de negocio separada del logging técnico.
7. Compatibilidad progresiva con el esquema legado mediante ampliación, backfill y corte controlado.
8. MySQL aislado para validar semántica que SQLite no reproduce.

## 14. Decisiones todavía pendientes

- máximo y número de extensiones de pre-reserva, y desempate detallado ante competencia simultánea;
- autorizadores de excepción al adelanto;
- ventana gratuita de reevaluación;
- flujo y estados clínicos exactos por especialidad;
- alcance empresarial de farmacia, lotes, vencimientos, compras y proveedores;
- reglas contables y fiscales definitivas;
- retención legal de datos, evidencias y auditoría;
- contrato técnico con el llamador;
- topología de preproducción, workers y scheduler disponible en hosting;
- RPO/RTO y procedimiento empresarial de continuidad.

Estas decisiones no impiden documentar el marco técnico, pero sí bloquean detalles de implementación de los módulos afectados.

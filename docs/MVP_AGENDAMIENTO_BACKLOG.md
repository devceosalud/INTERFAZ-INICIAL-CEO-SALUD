# MVP de Agendamiento — backlog priorizado

## 1. Convenciones

- **P0:** seguridad/consistencia necesaria antes de escrituras piloto.
- **P1:** alcance funcional obligatorio del MVP.
- **P2:** integración/optimización posterior al núcleo, sin bloquear el primer incremento.
- Tamaño relativo: S, M, L; no representa compromiso de fecha.
- Estado inicial: `PLANNED`, salvo decisiones marcadas `BLOCKED_BUSINESS`.

## 2. Épica E0 — seguridad y activación

| ID | Pri. | Historia técnica/funcional | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E0-01 | P0 | Flag global de agenda nueva apagado por defecto | S | — | backend y UI rechazan/ocultan sin flag |
| E0-02 | P0 | Capacidad `appointment.mvp.access` | S | E0-01 | piloto no depende de nombre de usuario |
| E0-03 | P0 | Catálogo de capacidades por acción | M | — | permisos positivos/negativos cubiertos |
| E0-04 | P0 | Policies/Form Requests para casos de uso | M | E0-03 | ninguna regla sensible depende del menú |
| E0-05 | P0 | Guard de entorno e integraciones fake | S | — | tests abortan ante configuración insegura |
| E0-06 | P0 | Logging sanitizado/correlation id | M | — | sin DNI, paciente, token ni evidencia |

## 3. Épica E1 — fundación de datos

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E1-01 | P0 | Catálogo `sites` y sede inicial | M | E0 | backfill validado, sin hardcode |
| E1-02 | P0 | Asociar sede nullable a horarios/citas | M | E1-01 | historia sigue legible |
| E1-03 | P0 | Detalle uno-a-uno de agendamiento | M | E1-01 | regular/sobreagenda/adicional fuera del enum |
| E1-04 | P0 | Responsable actual y último modificador | M | E0 | creador/responsable/modificador separados |
| E1-05 | P0 | Historial de responsables | M | E1-04 | cambios append-only consultables |
| E1-06 | P0 | Bitácora de eventos de agenda | M | E0 | eventos críticos auditables |
| E1-07 | P0 | Upgrade/rollback MySQL con fixture heredada | L | E1-01..06 | código previo tolera esquema nuevo |

## 4. Épica E2 — disponibilidad y concurrencia

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E2-01 | P0 | Normalizar lectura de horarios recurrentes/fechados | M | E1 | resultado determinista |
| E2-02 | P1 | Excepciones/bloqueos de horario | M | E2-01 | bloqueo parcial/total soportado |
| E2-03 | P0 | Detector de solapamiento por intervalos | M | E2-01 | duraciones variables correctas |
| E2-04 | P0 | Lock profesional/día en MySQL | M | E1-01, E2-03 | carreras serializadas |
| E2-05 | P0 | Idempotencia de mutaciones | M | E0, E2-04 | doble clic no duplica |
| E2-06 | P1 | API/feed Día/Semana/Mes | L | E2-01..05 | mismo motor para todas las vistas |
| E2-07 | P1 | Comparación de profesionales/especialidades | M | E2-06 | filtros y huecos comunes |

## 5. Épica E3 — paciente rápido y RENIEC

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E3-01 | P0 | Búsqueda local por DNI antes de RENIEC | S | E0 | paciente existente seleccionado |
| E3-02 | P1 | Contrato/adaptador RENIEC normalizado | M | E0 | HTTP fake y timeout controlado |
| E3-03 | P1 | Alta de identidad mínima | M | E3-01/02 | agenda continúa sin modal separado |
| E3-04 | P1 | Fallback manual | S | E3-02 | error externo no bloquea |
| E3-05 | P0 | Dedupe concurrente de identidad | M | E3-03 | una sola fila por identidad aplicable |
| E3-06 | P1 | Completitud/verificación posterior | M | E3-03 | misma identidad se completa, no duplica |
| E3-07 | P1 | Definir campos/estados de completitud | — | decisión Q1 | `BLOCKED_BUSINESS` para activación final |

## 6. Épica E4 — cita rápida y responsabilidad

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E4-01 | P1 | Panel rápido desde slot/hora manual | L | E2, E3 | conserva contexto de agenda |
| E4-02 | P0 | Caso de uso crear cita regular | L | E1, E2, E3 | transaccional/idempotente |
| E4-03 | P1 | Responsable inicial autenticado | S | E1-04, E4-02 | separado del creador |
| E4-04 | P1 | Reasignar responsable | M | E1-05 | anterior/nuevo/actor/fecha |
| E4-05 | P1 | Reprogramar con revalidación | L | E2-04, E4-02 | sin estado parcial |
| E4-06 | P0 | Compatibilidad de cita heredada | M | E1 | filas sin detalle siguen operables |
| E4-07 | P1 | Detalle expandido/historial | M | E1-06 | auditoría consultable con permiso |

## 7. Épica E5 — pre-reserva y confirmación

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E5-01 | P0 | Tabla/caso de uso de hold | L | E2-04 | cupo regular retenido una vez |
| E5-02 | P1 | Vencimiento 15 min configurable | M | E5-01 | nuevas reservas usan política vigente |
| E5-03 | P0 | Expirador idempotente | M | E5-02 | libera una vez y es monitoreable |
| E5-04 | P1 | Extensión autorizada/auditada | M | E0-03, E5-01 | motivo y vencimientos conservados |
| E5-05 | P0 | Conversión hold → cita | L | E4-02, E5-01 | una sola cita/hold |
| E5-06 | P0 | Carrera confirmación/expiración | M | E5-03/05 | estado terminal consistente en MySQL |

## 8. Épica E6 — adelanto, evidencia y COSTO 0

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E6-01 | P0 | Evidencia privada de pago | M | E5 | checksum/storage/actor |
| E6-02 | P0 | Verificar/rechazar evidencia | M | E0-03, E6-01 | actor/fecha/resultado |
| E6-03 | P0 | Regla de adelanto 50 % | M | E6-02, E5-05 | backend bloquea insuficiente |
| E6-04 | P1 | Excepción al adelanto | M | E0-03 | separada de COSTO 0 |
| E6-05 | P1 | Maestro aprobadores COSTO 0 | M | E1 | vigencia, activo, sin rol hardcodeado |
| E6-06 | P1 | Solicitud/decisión COSTO 0 | L | E6-05 | importes/motivo/actores auditados |
| E6-07 | P0 | Proyección financiera legacy | L | E6-02/03 | sin pago/voucher doble |
| E6-08 | P1 | Definir verificadores de pago | — | decisión Q5 | `BLOCKED_BUSINESS` para asignar permisos |
| E6-09 | P1 | Definir autoaprobación/límites | — | decisión Q4 | `BLOCKED_BUSINESS` para activar COSTO 0 |

## 9. Épica E7 — excepciones de capacidad

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E7-01 | P1 | Flujo de evaluación sobreagenda | M | E2, E6 auth | no bypass frontend |
| E7-02 | P1 | Autorización de sobreagenda | M | E7-01, decisión Q2 | actor/motivo auditados |
| E7-03 | P1 | Crear adicional con agenda llena | L | E2, E4 | no consume slot regular |
| E7-04 | P1 | Constancia de información/no garantía | S | E7-03 | backend obligatoria |
| E7-05 | P1 | Espera configurable y snapshot | S | E7-03 | valor histórico conservado |
| E7-06 | P1 | Límite y cierre adicional | M | decisión Q3 | `BLOCKED_BUSINESS` para activación |
| E7-07 | P0 | Validar fin de jornada/bloque | M | E2, E7-03 | adicional fuera de horario rechazada |

## 10. Épica E8 — experiencia de agenda

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E8-01 | P1 | Shell desktop de tres paneles | L | E2/E4 | filtros + calendario + panel rápido |
| E8-02 | P1 | Día/Semana/Mes | M | E2-06 | contexto conservado |
| E8-03 | P1 | Lista/comparación profesional | M | E2-07 | selección múltiple usable |
| E8-04 | P1 | Semaforización accesible | M | E4..E7 | color + texto/icono |
| E8-05 | P1 | Hora manual rápida | S | E2 | mismas validaciones backend |
| E8-06 | P1 | Detalle expandido lateral | M | E4-07 | sin cadena de modales |
| E8-07 | P1 | Atajos/prevención doble submit | M | E2-05 | operación común fluida |
| E8-08 | P1 | UAT con grupo piloto | L | decisión Q6 | métricas/feedback registrados |

## 11. Épica E9 — despliegue y observabilidad

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E9-01 | P0 | Pipeline suite SQLite/MySQL | M | E0 | no usa producción |
| E9-02 | P0 | Runbook backup/restore | M | — | restore ensayado |
| E9-03 | P0 | Métricas holds/conflictos/errores | M | E5 | sin PII |
| E9-04 | P0 | Rollback por flag | S | E0 | probado por incremento |
| E9-05 | P1 | Piloto por permiso/alcance | M | Q6 | sin nombres hardcodeados |

## 12. Épica E10 — integración futura con llamador

| ID | Pri. | Historia | Tamaño | Dependencias | Criterio de salida |
|---|---:|---|---:|---|---|
| E10-01 | P2 | Definir contrato de estados/correlación | L | E4 estable | aprobado por ambos sistemas |
| E10-02 | P2 | Outbox/idempotencia ERP | L | E10-01 | no pierde eventos confirmados |
| E10-03 | P2 | Adaptador llamador temporal | L | E10-01 | sin compartir BD |
| E10-04 | P2 | Shadow/reconciliación | L | E10-02/03 | divergencias observables |
| E10-05 | P2 | Verificar no uso legacy | M | logs sanitizados | consumidores = 0 o plan de migración |
| E10-06 | P2 | Contener/retirar legacy | M | E10-05 | rutas directas eliminadas/protegidas |

## 13. Preguntas empresariales bloqueantes

| ID | Decisión | Bloquea | No bloquea |
|---|---|---|---|
| Q1 | Campos mínimos para agendar, campos para completar/verificar y nombres de completitud | activación E3-06/UAT paciente | arquitectura/adaptador/fallback |
| Q2 | Quién autoriza sobreagenda y regla regular excepcional vs sobreagenda | E7-02 | agenda regular |
| Q3 | Máximos de adicionales y cierre/no atención | E7-06/UAT adicional | estructura/tipo/constancia |
| Q4 | Autoaprobación y límites económicos COSTO 0 | activación E6-06 | maestro y solicitud genéricos |
| Q5 | Quién verifica evidencias/adelantos | permisos productivos E6-02 | almacenamiento y workflow genérico |
| Q6 | Usuarios, profesionales y sede del piloto | E8-08/E9-05 | desarrollo detrás de flag |

Preguntas posteriores, no bloqueantes del núcleo: momento exacto de publicación al llamador, contrato de estados, retención de evidencia y política final de privacidad del visor.

## 14. Camino crítico

```text
E0 -> E1 -> E2 -> E4 -> E5 -> E6 -> E7 -> E8 -> piloto
             \-> E3 -/

E10 inicia como diseño después de estabilizar E4 y no bloquea el primer commit del MVP.
```

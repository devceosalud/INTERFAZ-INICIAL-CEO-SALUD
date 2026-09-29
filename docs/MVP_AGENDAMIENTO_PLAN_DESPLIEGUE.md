# MVP de Agendamiento — plan futuro de despliegue

## 1. Estado

Runbook de planificación. No autoriza desplegar, modificar producción ni ejecutar migrations. `main` puede autodesplegar en Hostinger, por lo que ningún cambio llega a `main` sin revisión, backup y ventana controlada.

## 2. Principios

- un incremento por PR lógico y reversible;
- migrations aditivas antes que cambio de lectores/escritores;
- feature flag apagado al desplegar infraestructura nueva;
- backups verificados antes de tocar esquema/datos;
- smoke tests sin enviar mensajes, consultar RENIEC real ni mutar citas ajenas;
- piloto limitado por permiso/configuración, nunca por nombre hardcodeado;
- rollback funcional preferido a rollback destructivo de datos;
- evidencia y auditoría nuevas no se borran al retroceder código.

## 3. Flujo obligatorio

```text
branch de incremento
  -> tests SQLite + MySQL aislado
  -> revisión técnica/seguridad
  -> PR sin merge automático
  -> aprobación de negocio aplicable
  -> backup y restore check
  -> migration aditiva con flag OFF
  -> deploy controlado
  -> smoke test
  -> UAT
  -> piloto limitado
  -> monitoreo
  -> expansión gradual
```

## 4. Preparación previa a cada incremento

1. Congelar alcance y criterios de aceptación.
2. Confirmar rama/base y que el PR no mezcle deuda no relacionada.
3. Ejecutar suite completa y `git diff --check`.
4. Revisar migrations en MySQL equivalente a producción.
5. Medir duración de `ALTER`/backfill con volumen representativo.
6. Confirmar compatibilidad hacia atrás: código anterior tolera esquema nuevo.
7. Confirmar compatibilidad hacia delante: código nuevo tolera filas históricas.
8. Preparar flag, métrica, alerta y rollback.
9. Validar que no exista uso de credenciales reales en tests/config committed.
10. Registrar responsable de decisión, ejecución y verificación.

## 5. Backup y restauración

Antes de una migration productiva futura:

- snapshot/backup completo de BD con fecha y referencia interna;
- copia de artefacto/código desplegado y commit anterior;
- verificación de integridad y acceso restringido;
- prueba de restauración en entorno aislado, no solo “backup exitoso”;
- conteos sanitizados de tablas afectadas;
- plan de restauración con RTO/RPO aceptados por negocio/operaciones.

No copiar PII a equipos personales ni repositorio.

## 6. Estrategia de migrations

### Expandir

- crear tablas nuevas;
- añadir columnas nullable e índices compatibles;
- evitar defaults que reescriban masivamente la tabla si la versión MySQL lo penaliza;
- crear FK solo tras validar huérfanos;
- no modificar enum ni eliminar columnas en el mismo despliegue.

### Backfill

- comando/servicio idempotente, con lotes, progreso y dry-run;
- usar ids/rangos, no offsets frágiles;
- registrar solo cantidades y errores sanitizados;
- pausable y reanudable;
- no inventar responsable/tipo histórico.

### Cambiar lectores/escritores

- activar primero shadow reads/comparación;
- activar escritores a grupo piloto;
- reconciliar diferencias;
- expandir solo con métricas estables.

### Contraer

Fuera del MVP inicial. Eliminar columnas/rutas legacy requiere fase posterior, evidencia de no uso, backup y periodo de deprecación.

## 7. Despliegue por incremento

| Incremento | Orden de despliegue | Activación | Rollback principal |
|---|---|---|---|
| MVP-0 | config/capacidades → código | flag OFF; permisos sin asignar | flag OFF y retirar permisos |
| MVP-1 | tablas → columnas nullable → índices → código lector | sin UI/escritor | código anterior; conservar esquema aditivo |
| MVP-2 | motor nuevo en shadow → comparación → feed piloto | por permiso | volver feed al motor legacy |
| MVP-3 | adaptador RENIEC fake/sandbox → panel piloto | por permiso | volver al alta actual; conservar pacientes creados |
| MVP-4 | casos de uso → panel rápido | usuarios piloto | UI/flag legacy; conservar metadatos |
| MVP-5 | holds/job/evidencia → confirmación piloto | subflags por hold/pago/COSTO 0 | detener job/escritores; conciliación, no borrar |
| MVP-6 | reglas excepcionales | capacidades separadas | retirar capacidades, preservar citas |
| MVP-7 | UI consolidada | enlace/flag de UI | volver interfaz previa |
| MVP-8 | contrato en shadow → dual-run de eventos → corte | flag en ambas apps | detener productor/consumidor; conservar outbox |

## 8. Smoke tests productivos futuros

Solo con datos sintéticos/autorizados y pasos aprobados:

- aplicación, login y dashboard;
- usuario fuera del piloto conserva flujo actual;
- usuario piloto ve agenda nueva cuando flag está activo;
- lectura de una cita histórica autorizada;
- consulta de disponibilidad sin mutar;
- permisos negativos;
- health de job/cola sin disparar integraciones reales;
- carga de assets/calendario;
- revisión de logs sanitizados y errores.

Crear una cita, pago, llamada o mensaje real no es un smoke test pasivo; necesita guion UAT y autorización específica.

## 9. UAT y piloto

### Entrada

- A–D de Fase 5 confirmados;
- grupo de piloto definido;
- capacidades asignadas;
- decisiones empresariales del incremento cerradas;
- runbook/rollback ensayado;
- soporte operativo disponible.

### Alcance recomendado

- una sede inicial;
- conjunto explícito de usuarios y profesionales;
- horarios/servicios seleccionados;
- periodo corto con revisión diaria;
- coexistencia con flujo actual mediante flag, sin doble captura silenciosa.

### Salida/expansión

- cero incidentes críticos abiertos;
- divergencia de disponibilidad/finanzas dentro del umbral aprobado;
- tiempos operativos aceptables;
- permisos y auditoría validados;
- rollback no requerido o incidentes resueltos;
- aprobación de jefatura/operaciones.

## 10. Monitoreo

Métricas mínimas, agregadas y sin PII:

- holds creados/convertidos/expirados/liberados;
- conflictos de disponibilidad;
- intentos idempotentes repetidos;
- tiempo de creación/reprogramación;
- fallos RENIEC y uso de fallback;
- confirmaciones por pago/excepción/COSTO 0;
- autorizaciones rechazadas;
- adicionales/sobrecitas por profesional en agregado;
- errores por endpoint/caso de uso;
- divergencias de proyección legacy;
- cuando exista llamador: pendientes/reintentos/divergencias de integración.

Alertas: tasa anómala de conflictos, job de expiración detenido, evidencia sin verificar, divergencia financiera, 5xx, deadlocks repetidos, outbox estancada.

## 11. Rollback por tipo de fallo

| Fallo | Acción inmediata | Conservación |
|---|---|---|
| UI/rendimiento | flag UI OFF | datos nuevos y auditoría |
| permisos | retirar capacidad/asignación | logs/auditoría |
| disponibilidad incorrecta | volver lector/feed legacy; detener escritores nuevos | holds/citas para reconciliar |
| job de expiración | pausar job, no liberar manualmente en masa | holds y timestamps |
| pago/divergencia | desactivar confirmación, bloquear conciliación automática | evidencias/pagos/autorizaciones |
| migration | volver código si esquema es compatible; rollback DDL solo si vacío/seguro | backup y datos creados |
| llamador | detener integración por flag | outbox/correlaciones/reintentos |

Restaurar toda la BD es último recurso y requiere decisión operativa; no se usa para corregir una sola fila.

## 12. Hostinger y `main`

- comprobar configuración real de auto-deploy antes de cada merge;
- deshabilitar merge automático mientras exista una ventana coordinada;
- el PR aprobado no se mergea hasta confirmar backup, ejecutor y observador;
- registrar commit exacto desplegado y resultado;
- evitar `composer update`; usar instalación reproducible desde lock;
- nunca usar `main` como rama de experimentación;
- no mezclar migrations destructivas con cambios de UI.

## 13. Checklist de cierre de despliegue

- commit/artefacto confirmado;
- migration y backfill con conteos esperados;
- smoke/UAT registrados;
- flag y alcance verificados;
- logs sin PII/secretos;
- métricas/alertas activas;
- backup retenido según política;
- incidencias y decisiones anotadas;
- siguiente expansión o rollback aprobado.

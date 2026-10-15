# RC — rendimiento de Nicole y sondeo

Mediciones sobre el código de aplicación `b388bd3f4816e30506e913aec33234518949db5b`, hash `a95709f76233b2e83b9e603581af47d8877c9b7a0bb355aff9f2c3f75b550ed5`. PHP 8.3.35, MariaDB 11.8.9, Windows local, Node 26.3.0. No staging ni TLS/latencia Hostinger. Datos sintéticos: 20 médicos, 5 días, pacientes distintos, 50 pagados por cada cita de 100; fixtures financieros de carga replicados solo en la BD descartable 33323. No equivalen a pagos bancarios reales.

## Kernel caliente

Tres calentamientos y 30 muestras por tamaño/modo; 30 filas por página. Objetivos locales propuestos: p95 <=1.000 ms, <=30 SQL, <=100 KB, pico <=256 MiB. Se justifican para dejar margen al ciclo de 5 s, sin presentarlos como SLA productivo.

| Citas | Modo | p50 ms | p95 ms | SQL | Bytes máx | Pico MiB |
|---|---|---:|---:|---:|---:|---:|
| 100 | full | 18.931 | 20.528 | 20 | 23406 | 36 |
| 100 | unchanged | 19.817 | 24.628 | 20 | 96 | 38 |
| 500 | full | 46.251 | 50.445 | 20 | 23409 | 44 |
| 500 | unchanged | 45.986 | 52.446 | 20 | 96 | 44 |
| 2000 | full | 148.423 | 159.258 | 20 | 23418 | 66 |
| 2000 | unchanged | 149.079 | 154.927 | 20 | 96 | 66 |

## HTTP real con sesiones independientes

Dos procesos PHP, ocho cookies independientes y CSRF real. Por tamaño/modo: 20 solicitudes sin carga y 10 ráfagas de 8 (80 muestras), sin superponer fixtures/suites. Incluye bootstrap PHP por petición. Objetivos propuestos: p95 <=1.500 ms sin carga, <=4.000 ms en ráfaga, <=40 SQL y <=100 KB. Los tres tamaños cumplieron.

| Citas | Modo | Carga | n | p50 ms | p95 ms | SQL | Bytes máx |
|---|---|---|---:|---:|---:|---:|---:|
| 100 | full | unloaded | 20 | 160.88 | 201.579 | 25 | 23856 |
| 100 | full | 8 simultaneous users | 80 | 371.957 | 696.052 | 25 | 23856 |
| 100 | unchanged | unloaded | 20 | 157.713 | 190.844 | 25 | 96 |
| 100 | unchanged | 8 simultaneous users | 80 | 382.806 | 701.679 | 25 | 96 |
| 500 | full | unloaded | 20 | 188.347 | 216.336 | 25 | 23469 |
| 500 | full | 8 simultaneous users | 80 | 415.251 | 798.765 | 25 | 23469 |
| 500 | unchanged | unloaded | 20 | 205.273 | 227.225 | 25 | 96 |
| 500 | unchanged | 8 simultaneous users | 80 | 441.638 | 830.712 | 25 | 96 |
| 2000 | full | unloaded | 20 | 275.796 | 304.064 | 25 | 23598 |
| 2000 | full | 8 simultaneous users | 80 | 616.814 | 1181.783 | 25 | 23598 |
| 2000 | unchanged | unloaded | 20 | 281.245 | 315.381 | 25 | 96 |
| 2000 | unchanged | 8 simultaneous users | 80 | 623.138 | 1178.069 | 25 | 96 |

### Ciclo tabla + detalle seleccionado (2.000)

Se midieron además las dos peticiones secuenciales de un ciclo sin cambios; revalidan autorización aunque respondan 96 bytes cada una. 20 ciclos sin carga y 80 con ocho sesiones. Objetivos propuestos: p95 <=1.800/4.500 ms, <=80 SQL por ciclo y <=200 KB. Se deja margen de 0,5 s respecto de los cinco segundos y se identifican los límites de dos workers. No se confunde una petición feed con todo el panel.

| Carga | p50 ms | p95 ms | SQL/ciclo | Bytes/ciclo |
|---|---:|---:|---:|---:|
| unloaded | 455.913 | 504.26 | 54 | 192 |
| 8 simultaneous users | 1689.922 | 2265.736 | 54 | 192 |

No incluye consulta inicial de disponibilidad (hasta seis médicos), assets/DOM, DNS/TLS ni servicios externos. Una respuesta completa o un nuevo filtro también puede consultar disponibilidad; un ciclo sin cambios evita repetirla. El detalle se revalida si está seleccionado. No se promete escalabilidad ilimitada.

## Sondeo e integridad

- Intervalo configurado: 5.000 ms. Jornada entre sesiones: cita visible en 4417 ms, 2 feeds, máximo 1 en vuelo, 0 navegaciones. El scheduler espera al trabajo previo: intervalos reales pueden superar cinco segundos.
- Prueba con respuesta de siete segundos: máximo background 1; sin nueva solicitud mientras esperaba. La carga inicial cancelada por Aplicar filtros alcanzó 2 solicitudes observadas; se reporta separadamente, no como sondeo periódico.
- Error 503 conserva filas/totales y se recupera; confirmación abierta, filtros sin aplicar y salida del módulo detienen consultas innecesarias. Pruebas de respuestas tardías en JS, autorización y revisionado en PHP.
- Documento nuevo observado en 1064 ms y reemplazo en 5656 ms; cero recargas. Revocar VIEW retira enlace y descarga produce 404 desde sesión existente. Estos tiempos incluyen cambio de foco a la sesión de Nicole.
- Ocultación real de pestaña **no acreditada**: Chrome de QA conservó ambas visible; unit tests sí cubren la rama. No se declara aprobado ese ensayo de navegador.

## Consultas, índices y límites

La proyección económica pasó de recorridos repetidos por cita a índices en memoria y relaciones solo de las 30 filas visibles. SQL constante: 20 en kernel y 25 en feed HTTP para 100/500/2.000; no crece una consulta por cada cita en ese recorrido. No demuestra ausencia de N+1 en todos los módulos.

`index-evidence.json` contiene SHOW INDEX y EXPLAIN reales en 2.000 filas: rango por fecha usa ALL (sin índice dedicado), médico usa FK y estima 100 filas, historial usa índice de paciente y filesort acotado. El barrido por fecha es riesgo de crecimiento; medir y evaluar índice fecha/médico en una entrega revisada antes de crecer, sin inventar que ya existe. No se agregó una migración sin necesidad demostrada en esta carga.

El panel limita 31 días/2.000 resultados y pagina 30; si el conjunto excede el límite pide filtrar, no omite filas en silencio. Paginación no implica que el cálculo de totales no lea el conjunto filtrado. Memoria y permisos/sesiones PHP web pendientes de verificar en Hostinger.

Evidencias completas JSON y comandos: `performance-candidate.json`, `performance-http-*.json`, `performance-cycle-2000.json`, `index-evidence.json`, `polling-metrics.json`; reproducir según RC_CEO_SALUD_QA.md.

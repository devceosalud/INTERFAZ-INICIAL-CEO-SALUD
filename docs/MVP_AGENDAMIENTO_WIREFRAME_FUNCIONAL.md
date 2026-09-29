# MVP de Agendamiento — wireframe funcional

## 1. Objetivo UX

Un usuario entrenado de ADMISION o del futuro rol/capacidad comercial debe registrar una cita común en pocos segundos sin perder el contexto de la agenda. Desktop es prioritario; la interfaz combina la maqueta interna, el sistema anterior como referencia operativa y FullCalendar actual, sin copiar literalmente ninguno.

## 2. Estructura principal

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ Agenda CEO Salud   [Sede] [Fecha ◀ Hoy ▶] [Día|Semana|Mes] [Buscar cita]    │
├──────────────────────────────────────────────────────────────────────────────┤
│ Especialidad [▼]  Médico [multi-select]  Servicio [▼]  Estado [▼] [Limpiar] │
├───────────────┬──────────────────────────────────────┬───────────────────────┤
│ PROFESIONALES │ CALENDARIO / AGENDA HORARIA          │ AGENDAMIENTO RÁPIDO   │
│               │                                      │                       │
│ ☑ Dr. A       │ 08:00  Disponible                    │ DNI [________] [Buscar]│
│   6 libres    │ 08:30  Confirmada · pago parcial     │ ✓ Paciente registrado │
│               │ 09:00  Pre-reserva · vence 06:42     │ o Nuevo · datos pend. │
│ ☑ Dra. B      │ 09:30  Bloqueado                     │                       │
│   3 libres    │ 10:00  Adicional · no garantizada    │ Médico/servicio       │
│               │ 10:30  Disponible                    │ Fecha [ ] Hora [ ]     │
│ [Comparar]    │                                      │ Responsable [yo ▼]    │
│               │ [clic slot] [hora manual]            │ Precio/adelanto        │
│               │                                      │ [Pre-reservar]         │
├───────────────┴──────────────────────────────────────┴───────────────────────┤
│ DETALLE EXPANDIDO (drawer inferior/lateral, no nueva página)                │
│ paciente | cita | pago | autorizaciones | responsable | historial           │
└──────────────────────────────────────────────────────────────────────────────┘
```

En pantallas medianas, el panel rápido se convierte en drawer lateral. No se diseña mobile completo en el MVP, pero las acciones críticas no deben quedar inaccesibles.

## 3. Barra superior

- sede visible aunque inicialmente exista una;
- fecha con navegación anterior/siguiente y retorno a “Hoy”;
- selector Día/Semana/Mes persistiendo filtros;
- búsqueda de cita por referencia/paciente con resultados mínimos autorizados;
- indicador de modo piloto y entorno cuando no sea producción;
- sin datos clínicos o financieros innecesarios en títulos globales.

## 4. Filtros y profesionales

### Lista izquierda

Cada profesional muestra:

- nombre corto autorizado;
- especialidad;
- inicio/fin de bloque visible;
- libres/ocupados/holds en agregado;
- indicador de bloqueo o ausencia;
- checkbox para comparación.

La selección múltiple no dispara una consulta por profesional; el backend entrega un resultado agregado evitando N+1.

### Comparación

- Día: columnas por profesional con la misma escala horaria.
- Semana: profesionales seleccionados mediante filtro/leyenda, evitando demasiadas columnas.
- Mes: densidad agregada por día; al elegir día se abre vista Día conservando filtros.

## 5. Calendario y agenda horaria

FullCalendar actual puede conservarse como motor de renderizado si:

- consume un feed versionado del nuevo motor;
- no calcula disponibilidad ni autorización por sí mismo;
- representa intervalos y duraciones reales;
- mantiene navegación/filtros sin recarga completa;
- permite detalle por teclado y no solo drag & drop.

### Interacciones

- clic en slot disponible: precarga fecha/hora/profesional;
- clic en espacio no disponible: abre evaluación y explica por qué está ocupado; no guarda automáticamente;
- hora manual: campo editable con validación inmediata y backend final;
- drag & drop: solo atajo para solicitar reprogramación; muestra resumen antes de confirmar;
- clic en cita: abre drawer de detalle, no abandona calendario.

## 6. Panel de agendamiento rápido

### Estado inicial

Solo muestra:

1. DNI;
2. estado de identificación;
3. profesional/servicio/fecha/hora;
4. responsable;
5. precio y condición básica;
6. acción principal.

### Flujo de paciente

```text
DNI completo
  -> Buscando local…
     |-- encontrado: ✓ PACIENTE REGISTRADO
     `-- no encontrado: consultando RENIEC…
          |-- éxito: PACIENTE NUEVO · completar mínimos
          `-- fallo: RENIEC no disponible · [Ingreso manual]
```

No abrir modal aparte. Conservar DNI, slot y filtros ante timeout/error.

Estados visibles:

- `PACIENTE REGISTRADO` — verde + icono + texto;
- `PACIENTE NUEVO` — azul + icono + texto;
- `DATOS PENDIENTES` — ámbar + icono + texto;
- `REVISIÓN REQUERIDA` — rojo/ámbar según caso, nunca solo color.

### Acción principal

- si la política requiere pago posterior: **Crear pre-reserva**;
- si existe confirmación permitida: **Confirmar cita** con resumen;
- doble clic deshabilitado visualmente y protegido por request key backend;
- éxito mantiene fecha/filtros y limpia solo el formulario.

## 7. Semaforización

Paleta definitiva pendiente de accesibilidad/branding. La semántica mínima:

| Concepto | Texto/icono obligatorio | Tratamiento visual sugerido |
|---|---|---|
| Disponible | “Disponible” + círculo vacío | neutro/verde suave |
| Pre-reserva | reloj + vencimiento | ámbar |
| Confirmada | check + condición de pago | azul/verde |
| Pago pendiente/parcial | moneda + texto | ámbar/azul |
| Bloqueada/ausencia | candado | gris/rojo suave |
| Sobreagenda | capas + “Sobreagenda” | violeta |
| Adicional | plus + “No garantizada” | naranja |
| Cancelada/no asistió | texto tachado/icono | gris |
| En atención/atendida | estado textual | color operativo acordado |

El color nunca es el único portador de significado. Tooltips no sustituyen texto crítico.

## 8. Detalle expandido

Tabs o secciones dentro del mismo drawer:

### Resumen

- número de cita;
- paciente/completitud;
- sede, profesional, servicio, fecha/hora/duración;
- tipo regular/sobreagenda/adicional;
- estado productivo.

### Pago

- precio snapshot;
- adelanto requerido/recibido/verificado;
- evidencia privada con acceso autorizado;
- excepción al adelanto o COSTO 0 claramente separadas.

### Responsabilidad

- creado por;
- responsable actual;
- último modificador;
- acción **Reasignar** si existe capacidad.

### Autorizaciones

- solicitudes y decisiones;
- solicitante/aprobador/motivo/fecha;
- importes original/aprobado para COSTO 0.

### Historial

- línea de tiempo de eventos sanitizados;
- no mostrar payload técnico o datos que el rol no necesita.

## 9. Pre-reserva

Al crear:

```text
PRE-RESERVA ACTIVA · vence 12:45 (14:32 restantes)
[Registrar evidencia] [Extender] [Liberar]
```

- contador informativo; el servidor decide vigencia;
- al vencer, refrescar estado y disponibilidad;
- extensión solicita motivo y muestra vencimiento nuevo antes de confirmar;
- si otro usuario obtuvo el cupo, mostrar conflicto y alternativas sin perder paciente.

## 10. Pago y COSTO 0

### Evidencia

- monto, medio, número de operación cuando aplique y archivo;
- estado `Pendiente de verificar`, `Verificado` o `Rechazado`;
- verificador no se puede seleccionar desde el cliente;
- archivo no se expone por URL pública.

### COSTO 0

Flujo en detalle avanzado:

1. precio original visible;
2. motivo obligatorio;
3. enviar solicitud;
4. aprobadores se determinan por maestro/capacidad;
5. decisión y precio aprobado quedan visibles en historial.

No convertir el toggle `es_exonerado` legacy en aprobación automática.

## 11. Sobreagendamiento y adicional

### Espacio ocupado/no disponible

El clic abre:

```text
Horario no disponible
Motivo: cita/hold/bloqueo
[Buscar alternativa]
[Solicitar sobreagenda]  (solo con capacidad)
[Evaluar adicional]      (solo si agenda regular llena)
```

### Adicional

El formulario muestra:

- fin real del bloque;
- carga y adicionales existentes;
- espera configurable informada;
- checkbox explícito “Paciente informado de que la atención no está garantizada”;
- razón y responsable;
- advertencia si excede bloque, con guardado rechazado por backend.

## 12. Reprogramación

- acción desde detalle o drag & drop;
- nueva disponibilidad consultada sin perder la cita original;
- resumen antes/después;
- impacto en hold/pago/llamador explicado;
- conflicto deja la cita original intacta;
- responsable no cambia automáticamente.

## 13. Mensajes y errores

| Situación | Mensaje funcional |
|---|---|
| RENIEC falla | “No pudimos consultar identidad. Puedes continuar manualmente.” |
| Cupo tomado | “Otro usuario tomó este horario. La agenda se actualizó; elige una alternativa.” |
| Hold vencido | “La pre-reserva venció y el cupo fue liberado.” |
| Sin permiso | “No tienes autorización para esta acción.” |
| Pago insuficiente | “El adelanto verificado no alcanza el mínimo requerido.” |
| Evidencia pendiente | “La evidencia aún debe ser verificada.” |
| Sobreagenda sin aprobación | “Este horario requiere una autorización válida.” |
| Adicional fuera de bloque | “La cita adicional no puede extender la jornada configurada.” |

No mostrar excepciones, SQL, stack traces, tokens ni respuestas RENIEC.

## 14. Atajos y accesibilidad

- foco inicial en DNI cuando se abre desde slot;
- Enter avanza solo si la acción es inequívoca;
- Escape cierra drawer sin guardar y solicita confirmación si hay cambios;
- teclado navega slots y controles;
- estados anunciables por lector de pantalla;
- contraste WCAG razonable;
- loaders localizados, no bloquear toda la agenda;
- deshabilitar acción durante envío sin depender de ello para idempotencia.

## 15. Qué conservar y qué simplificar

### Conservar

- FullCalendar y sus escalas si superan pruebas;
- filtros actuales útiles de médico/especialidad;
- cálculo visual de duración;
- densidad operativa del sistema anterior;
- paneles diferenciados rápido/detalle.

### Simplificar

- un solo motor de disponibilidad backend;
- un panel rápido en vez de cadena de modales;
- una respuesta normalizada de paciente/RENIEC;
- drawer de detalle en vez de navegar entre páginas;
- permisos por acción en vez de duplicar pantallas por rol;
- semaforización única con leyenda persistente.

### No conservar

- reglas de negocio exclusivamente en JavaScript;
- botones que importan/guardan de forma ambigua;
- nombre de usuario como autorización;
- escritura directa a BD del llamador;
- colores sin texto;
- recálculo de precios históricos al abrir una cita.

## 16. Validación del wireframe

Antes de diseño gráfico final, ejecutar walkthrough con:

- una cita común de paciente existente;
- paciente nuevo con RENIEC/fallback;
- pre-reserva y pago;
- reprogramación;
- reasignación;
- sobreagenda;
- adicional;
- vista de CAJA/FACTURACION con mínimo privilegio.

Registrar tiempo, pasos, errores y pérdidas de contexto; ajustar componentes sin cambiar reglas del backend.

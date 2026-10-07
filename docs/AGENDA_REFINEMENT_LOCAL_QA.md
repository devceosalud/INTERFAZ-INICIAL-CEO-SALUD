# Refinamiento de Agenda: revisión local sin commit

Base: `9cf2b189dd91d98bd07e73cca3ebcc73ad2a04e8`, rama
`pilot/agenda-horarios-clientes`. Rodrigo autorizó el commit local después de
QA y tests verdes. Este informe no autoriza despliegue. Fecha: 2026-10-06.

## Clasificación e intervalos

- `FUERA_HORARIO` es una clasificación propia. El alta exige una acción y endpoint
  explícitos, un intervalo íntegramente fuera de los bloques activos del médico
  y dentro del mismo día. No sirve para eludir la cadencia de un horario regular.
- `occupyingInterval` bloquea el intervalo exacto de la atención especial;
  `consumingRegularSlot` la excluye de la capacidad regular. Ambos respetan
  reservas privadas pendientes y estados que liberan el intervalo.
- `ADICIONAL` conserva coexistencia con la regular, bloque propio violeta y
  etiqueta; no consume otro cupo. Día/Semana conservan ambas citas; Mes y el
  encabezado muestran los totales adicionales/FH incluso sin horario regular.
- REGULAR ↔ FUERA_HORARIO devuelve 409 con confirmación específica y exige
  segundo PATCH con `confirmed_booking_type`. Se vuelve a validar bajo lock
  del médico: un destino ocupado se rechaza, nunca se convierte en ADICIONAL.
  Se mantiene la cita, paciente, servicio, responsable y economía; solo cambian
  fecha/hora, clasificación necesaria y auditoría del editor.
- El alta normal mantiene `LEGADO`/tipo NULL, como el piloto anterior. Se trata
  como regular a efectos de compatibilidad; este lote no anticipa A3.

## Formulario y reprogramación

La causa reproducida del atrapamiento era el selector nativo `input type=time`:
Tab alternaba entre sus listas de horas y minutos. Se reemplazó por HH:MM
editable con validación y botones 00/20/40. Se permiten otros minutos.

Servicio muestra asterisco/Obligatorio, mensaje específico, borde y foco al
intentar agendar sin selección. El modal incluye Servicio y Comercial dueño,
sincronizados con el workspace. La atribución mantiene la capability existente
y la validación backend. Guardar sin agendar conserva el flujo de paciente actual.

## Semáforo operativo

Capacidad = intervalos regulares generados por DoctorSchedule/duración; los
intervalos exactamente duplicados se cuentan una vez. Ocupación segura =
intervalos de esa capacidad que solapan una cita regular elegible, contados una
vez aunque existan datos históricos solapados. Una cita de duración mayor puede
cubrir varios intervalos. Requisitos económicos: precio_programado > 0 y
total_pagado × 2 >= precio_programado. Se excluyen pendientes, estados liberados,
ADICIONAL y FUERA_HORARIO. LEGADO/tipo NULL mantiene compatibilidad regular.

Porcentaje = ocupación segura / capacidad × 100. Verde <50, amarillo >=50 y <80,
rojo >=80; sin capacidad configurada, plomo. La selección y Hoy conservan señales
propias. Se devuelve solo información agregada, independiente del lector.

**Limitación aprobada del piloto:** total_pagado es un snapshot de Appointment.
No se reconstruyen pagos ni se reconcilia Caja/Ventas. El lote financiero debe
revisar `payments` como autoridad económica antes de tratar el indicador como
conciliación definitiva. Horarios históricos superpuestos con distintas
cadencias requieren revisión de catálogo; no se inventó una regla de unificación.

## Telemetría multimódulo v2

Una API: POST `/ui-telemetry/click-events`. La antigua API de Agenda se retiró.
Un colector y registro de módulos cubren Agenda, Horarios y Pacientes. Los
eventos nuevos requieren layout_version=2, módulo/vista/zona de listas cerradas,
UUID aleatorio de evento, coordenadas relativas a la zona y viewport. El servidor
asigna rol y timestamp; rechaza claves adicionales y texto arbitrario.

No se guardan DNI/HCE/nombres/apellidos/teléfonos, patient_id, appointment_id,
user_id, valores de inputs, texto clínico, URLs ni DOM. El colector omite campos
editables y nunca lee su contenido. La cola es acotada, de mejor esfuerzo; perder
telemetría por fallo del endpoint no bloquea el módulo.

El visor y sus datos exigen ADMINISTRADOR en middleware y controlador. La
capability audit.view por sí sola no concede acceso. Filtra módulo, fechas en
America/Lima, vista y zona. Agrupa en celdas 40×40 por zona.

Preview: wireframes estáticos sanitizados con zonas reconocibles, sin capturas
de sesiones ni pacientes. Las coordenadas de cada zona se proyectan a su panel
canónico. Esto permite comparar actividad por zona; no reproduce píxel a píxel
scroll, tamaños responsivos o contenido dinámico. Cambiar la geometría semántica
requiere nueva layout_version. Eventos v1 se conservan y no se mezclan con v2.

## Migrations y despliegue futuro

1. `2026_10_06_120000_extend_appointment_booking_type_with_off_hours`:
   amplía ENUM conservando REGULAR/ADICIONAL/NULL. En SQLite reconstruye la tabla
   preservando filas, FK, índices, triggers y secuencia. Down rechaza antes de
   modificar si quedan citas FUERA_HORARIO; no reclasifica ni elimina datos.
2. `2026_10_06_130000_add_sanitized_geometry_to_click_events`:
   layout_version default 1, zone nullable e índice módulo/layout/fecha.
   Preserva los eventos históricos. Down en SQLite <3.35 se rechaza antes de
   modificar índice/columnas; MySQL/MariaDB soporta retirar las nuevas columnas.

Aplicadas solo localmente por --path individual, tras verificar ambiente local,
127.0.0.1:3308 y base ERPCEOSALUD/erpceosalud. Motor local MariaDB 10.4.32.
Se comprobó que solo se añadieron esas dos migrations, que todas las citas
conservaron sus datos y que los 138 eventos v1 existentes permanecieron intactos.
No se ejecutó rollback. Producción requiere su propia revisión y autorización;
el código consumidor de FH necesita el schema nuevo antes de habilitarlo.

## Pendientes fuera de alcance

- A3: una reserva afectada por cambio de horario debe conservarse y requerir
  contingencia persistente/bandeja, seguimiento humano y notificación. No se
  cancela automáticamente. Este lote no implementa esa contingencia.
- Auditar permisos COMERCIAL antes de A3; Demo Comercial Local no tenía
  MVP/access/view/create. No se cambiaron permisos para suplirlo.
- Retiro, crédito, devolución, comprobantes, Drive, Factiliza, Excel y HCE se
  mantienen para lotes posteriores antes del lanzamiento.

## QA de cierre

Demo Admision Local (10) en navegador local, con paciente ficticio previo 4816:

- Servicio vacío: mensaje específico, aria-invalid y foco en el selector.
  Selects Servicio/Comercial dueño alineados, ambos con altura 32 px.
- Modal nuevo: Servicio y Comercial dueño presentes; Guardar y agendar sin
  Servicio devuelve feedback/foco; elegir Trauma sincroniza el workspace.
  Se descartó el modal y se comprobó que no se creó ese paciente.
- Alta normal por UI: cita 41, Trauma S/150, pago pendiente. Se convirtió a FH
  08:20 y volvió a REGULAR 12:20 conservando ID y snapshot. Intentar 09:00,
  ocupado por la cita FH 40, dejó el origen 12:20 y mostró conflicto.
- Atajos 00/20/40 cambiaron 08:17 a 08:00/08:20/08:40. Campo HH:MM editable,
  sin listas nativas que atrapen el foco.
- Regular 36 y adicional 38 visibles en filas distintas a las 12:00.
  Alta adicional explícita por UI creó 200 a esa misma hora sin cambiar regular.
- En bloque sin atención el alta normal estuvo deshabilitada y apareció la
  acción especial. Alta FH por UI creó 201 a las 08:40, CONFIRMADA/FUERA_HORARIO,
  precio 150 y pago PENDIENTE. Bloques sin atención y citas FH diferenciados.
- Día/Semana/Mes conservan eventos o conteos de adicionales/FH. Rodrigo
  confirmó visualmente la convivencia y aceptó resumen/conteo en Mes.

Semáforo: horario temporal 25 en 2026-11-02 con 100 intervalos de un minuto y
80 citas ficticias. Se ajustó solo su snapshot de adelanto a S/75 sobre S/150:
0 y 49 intervalos elegibles = verde; 50 y 79 = amarillo; 80 = rojo.
2026-11-03 sin horario = plomo. Se verificaron dataset, tooltip y color CSS
real en navegador; selección conservada. La leyenda quedó en dos filas compactas
con cuatro puntos/etiquetas; porcentajes y explicación se consultan por tooltip.
Se corrigió su recorte en el panel al comprobar la captura a 920 px de viewport.

## Heatmap de cierre

Clicks reales controlados en los tres módulos generaron eventos v2:
Agenda 61, Horarios 2 y Pacientes 3 al momento de la lectura. Los de Pacientes
incluyen lista y ficha. No se alteró la ficha ficticia. Se inspeccionó el schema
y ejemplos persistidos: únicamente campos técnicos permitidos, sin PII.

El visor y datos devolvieron 403 como Admisión; Rodrigo también confirmó el
rechazo visual. No había credenciales ADMINISTRADOR disponibles. Rodrigo
autorizó explícitamente reemplazar esa sesión visual por tests y HTTP interno.
Un administrador existente se utilizó solo como actor de lectura del kernel
HTTP, sin modificar cuenta, credenciales, roles, permisos o sesión del navegador.
Visor 200, selector de módulos/canvas presentes; datos 200/layout v2 para los
tres módulos. Filtros positivos vista/zona: Agenda dia/grid 13 clicks,
Horarios horarios/toolbar 2, Pacientes ficha/record 1. Fecha sin eventos devolvió 0.
Pruebas aisladas con administrador controlado verificaron autorización y filtros;
tests JavaScript verificaron preview de los tres módulos y proyección sobre zonas.
La interacción visual del visor como ADMINISTRADOR queda sin ejecutar; no se
presenta como prueba visual y no bloquea este cierre por decisión de Rodrigo.

## Limpieza y evidencia

Se eliminaron exclusivamente las citas de este lote 36, 38, 40, 41, 200 y 201,
y las 80 citas del semáforo junto al horario 25. Guardas de local, identidad,
IDs y dependencias precedieron cada limpieza. Se verificaron hashes de las
citas/horarios previos durante la limpieza del semáforo, y de todas las demás
citas al limpiar las seis pruebas de Agenda. Quedan las 14 citas anteriores.
Paciente ficticio previo 4816 se conservó; no se modificaron usuarios ni catálogo.
No quedan fixtures de citas/horarios creados por este lote. Los clicks anónimos
del QA permanecen como eventos de uso. El manifiesto externo de fixtures conserva
los 80 IDs exactos y marca cleaned=true; las capturas están fuera del repositorio.

## Tests finales

| Comando/suite | Resultado |
|---|---|
| php artisan route:list | 121 rutas; una API POST de telemetría |
| Feature/Scheduling | 295 passed, 1 skipped conocido, 0 failed |
| Feature/Patients | 65 passed, 0 failed |
| Feature/Security | 45 passed, 0 failed |
| Privacidad A2 + AppointmentAgendaLifecycleTest + CashierSalesAndPaymentsSmokeTest | 27 passed, 0 failed |
| Todos los tests JavaScript | 147 passed, 0 failed |
| AgendaHeatmapTest después de ampliar filtros positivos | 11 passed, 0 failed |

La omisión es el rollback nativo de A1 en SQLite 3.33. La nueva ampliación de
enum sí se probó con reconstrucción y rollback seguros en esa versión.
git diff --check del lote limpio; global conserva solo whitespace heredado de
DoctorController, que no se corrigió. Los tres controllers protegidos conservaron
exactamente sus hashes originales y deben quedar fuera del commit.

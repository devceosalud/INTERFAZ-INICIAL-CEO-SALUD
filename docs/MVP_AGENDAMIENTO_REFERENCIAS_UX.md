# MVP de agendamiento — referencias y principios UX

## 1. Propósito

Este documento separa las referencias visuales/operativas de los requisitos confirmados para el MVP. No define HTML, componentes, tablas, endpoints, paleta, enum ni layout definitivo.

No se incorporan capturas ni datos identificables. Nombres, historias clínicas, empresas, documentos y cualquier información personal visible en las imágenes de referencia quedan fuera de la documentación.

## 2. Clasificación

- **REFERENCIA UX INTERNA:** maqueta `ERP_Mockup_V8.OK.v2_Wilson-Cl.html`; orienta posibilidades de navegación y presentación, no reglas ni código reutilizable.
- **REFERENCIA OPERATIVA EXTERNA/ANTERIOR:** capturas proporcionadas por Rodrigo de un sistema usado anteriormente en una clínica; aporta patrones de densidad y rapidez, no un diseño que deba copiarse.
- **REQUISITO CONFIRMADO POR NEGOCIO:** resultado operativo obligatorio para el MVP.
- **PROPUESTA UX:** organización candidata que deberá validarse mediante prototipo y pruebas con usuarios.
- **PENDIENTE:** decisión no cerrada que no debe hardcodearse.

## 3. Aporte y límite de cada referencia

| Fuente | Aporte útil | No debe interpretarse como |
|---|---|---|
| REFERENCIA UX INTERNA | Día/Semana/Mes, comparación, tarjetas, asistente y edición desde agenda | Diseño literal, regla de seguridad, datos válidos o implementación aprobada |
| REFERENCIA OPERATIVA EXTERNA/ANTERIOR | Alta densidad, agenda diaria visible y registro repetitivo rápido | Paleta, layout, catálogo de estados, empresa, paciente o historia clínica que deban copiarse |
| ERP heredado | Evidencia de flujos, campos y restricciones existentes | Modelo funcional definitivo ni prueba de funcionamiento productivo |
| Llamador auditado | Dependencia AS-IS de estados/campos de citas | Contrato futuro definitivo ni autorización para cambiar producción |

## 4. Principio rector

**REQUISITO CONFIRMADO POR NEGOCIO:**

> Un usuario entrenado de Comercial/Admisión debe poder registrar correctamente una cita común en pocos segundos, con el mínimo de navegación y sin perder el contexto visual de la agenda.

“Pocos segundos” es criterio comparativo para evaluar propuestas, no un SLA numérico todavía. Rapidez no autoriza omitir validación, disponibilidad, permisos, trazabilidad ni consistencia financiera.

## 5. Modo de Agendamiento Rápido

**REQUISITO CONFIRMADO POR NEGOCIO:** debe existir un modo para COMERCIAL, ADMISION y otros usuarios de agendamiento repetitivo.

```text
DNI
  -> comprobar paciente local
  -> RENIEC solo si no existe y corresponde
  -> reutilizar paciente o registrar identidad mínima
  -> profesional
  -> fecha
  -> hora por slot o entrada manual
  -> servicio/tipo de consulta
  -> responsable
  -> condición de cita/pago
  -> validación final
  -> AGENDAR
```

El modo rápido conserva el contexto de filtros, profesional, fecha y agenda. Debe informar errores dentro del mismo recorrido y permitir corregirlos sin reiniciar el proceso.

## 6. Paciente registrado, nuevo e incompleto

### 6.1 Feedback

**REQUISITO CONFIRMADO POR NEGOCIO:** al completar un DNI válido, el sistema diferencia de forma inmediata:

- **PACIENTE REGISTRADO:** reutiliza la identidad local;
- **PACIENTE NUEVO:** permite obtener/registrar la identidad mínima;
- **DATOS PENDIENTES DE COMPLETAR/VALIDAR:** advierte que la ficha necesita verificación posterior.

Estos textos son equivalencias comunicativas, no nombres técnicos definitivos.

### 6.2 Evolución de una sola identidad

La llegada presencial permite que ADMISION verifique DNI y complete los datos obligatorios sobre el mismo paciente. No se crea otra persona ni otro paciente para representar la ficha completa.

| Nivel documental | Finalidad | Estado |
|---|---|---|
| Mínimos para agendar | Identificar inequívocamente y crear la cita sin exigir toda la ficha | REQUISITO CONFIRMADO; campos exactos PENDIENTES |
| Obligatorios para completar/validar | Completar la ficha durante atención presencial | REQUISITO CONFIRMADO; campos exactos PENDIENTES |

**PENDIENTE:** nombres definitivos de completitud/validación, campos de cada nivel y tratamiento de excepciones de identidad.

## 7. Densidad operativa desktop

**REQUISITO CONFIRMADO POR NEGOCIO:** la agenda debe equilibrar:

```text
CLARIDAD + DENSIDAD ÚTIL + VELOCIDAD
```

El usuario debe poder correlacionar en un mismo contexto profesionales, fecha, horas, citas, estados, condición de pago, paciente, horario y alta rápida. Esto no significa mostrar todo con igual prioridad: la información secundaria puede estar resumida, expandible o disponible en detalle.

Se prioriza experiencia desktop para COMERCIAL/ADMISION. Las tarjetas y separaciones no deben consumir espacio de forma que impida comparar o visualizar muchas citas simultáneamente.

## 8. Propuesta de pantalla de trabajo

**PROPUESTA UX:**

- **Zona 1:** profesionales, especialidades y filtros;
- **Zona 2:** calendario Día/Semana/Mes;
- **Zona 3:** agenda horaria, slots y citas;
- **Zona 4:** agendamiento rápido.

El detalle completo se abre bajo demanda. La distribución, tamaños, comportamiento responsive y componentes definitivos se decidirán mediante prototipo; las cuatro zonas son responsabilidades conceptuales, no paneles obligatorios.

## 9. Franjas horarias y hora manual

**REQUISITO CONFIRMADO POR NEGOCIO:**

- seleccionar un slot propone la hora;
- escribir una hora válida debe ser posible;
- cambiar 10:00 a 10:15 no debe exigir múltiples modales;
- reprogramar reutiliza un flujo corto y validado;
- la navegación futura debe considerar teclado y tabulación;
- 15 minutos puede ser una configuración visual, no una constante universal ni la duración automática de todo servicio.

Toda selección o edición vuelve a validar horario, bloque, capacidad, conflictos y autorización en backend.

## 10. Semaforización

**REQUISITO CONFIRMADO POR NEGOCIO:** el calendario utilizará semaforización accesible:

```text
COLOR + TEXTO / ETIQUETA / ICONO
```

No debe depender únicamente del color. Debe permitir diferenciar conceptualmente:

- disponible;
- pre-reserva;
- confirmada;
- pendiente de pago;
- pagada;
- adicional;
- paciente llegó;
- en espera;
- en consulta;
- atendida;
- cancelada;
- no atendida.

**PENDIENTE:** paleta, terminología y enum definitivos. Antes deben reconciliarse estados de producción, ERP heredado y `LLAMADOR-PACIENTE-CEO`.

## 11. Dos niveles de interacción, un solo modelo

| Nivel | Uso | Contenido esperado |
|---|---|---|
| Agendamiento rápido | Citas comunes y repetitivas | Datos mínimos para identificar paciente, programación, responsable y condición básica |
| Detalle completo | Operaciones avanzadas | Pago/evidencia, COSTO 0, adicional, aprobaciones, historial, auditoría, reprogramación, observaciones |

**PROPUESTA TÉCNICA:** ambos niveles deben invocar el mismo caso de uso y operar sobre la misma cita. No se duplican validaciones, autorización, cálculo, estado ni persistencia.

## 12. Trazabilidad sin sobrecarga

**REQUISITO CONFIRMADO POR NEGOCIO:** deben permanecer visibles o consultables:

- creado por;
- responsable actual;
- última modificación por;
- tipo de cita;
- estado.

El modo rápido muestra solo el resumen necesario; el historial completo se consulta en detalle.

## 13. Guiones futuros de validación UX

Las propuestas deberán demostrar, sin llamadas externas reales:

1. cita común para paciente registrado;
2. cita común para paciente nuevo con identidad mínima;
3. feedback claro registrado/nuevo/pendiente;
4. selección de slot;
5. escritura manual de hora;
6. cambio 10:00 → 10:15;
7. cambio de profesional/fecha/responsable;
8. reprogramación sin perder contexto;
9. apertura del detalle completo desde una cita rápida;
10. lectura de estados sin depender del color.

Se medirán pasos, cambios de contexto, errores recuperables y comprensión. El umbral numérico de tiempo queda **PENDIENTE**.

## 14. Pendientes que esta referencia no resuelve

- campos mínimos y obligatorios definitivos del paciente;
- nombres técnicos de completitud/validación;
- enum y transiciones productivas de cita;
- paleta y componentes visuales;
- intervalo visual predeterminado;
- SLA temporal de agendamiento;
- reglas ya pendientes de COSTO 0, sobreagendamiento, adicionales y piloto.

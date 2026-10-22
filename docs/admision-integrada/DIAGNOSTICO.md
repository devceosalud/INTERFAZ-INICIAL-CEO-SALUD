# Diagnóstico previo — Agenda de Admisión integrada

Base verificada: 3e1ca22b8e7e07bb2ddf18221cd8ff70499242a7 (PR #64). Rama local aislada codex/admision-flujo-integrado-20261022. Producción informada por responsable: d245918793014621066c8f35822dfca51b9ec296; no se accedió a producción.

- Ya funciona: llegada COMERCIAL por endpoint acotado, llegada ADMISION, identidad clínica vinculada, combinación verificada MEDICO + ADMINISTRADOR, auditoría/idempotencia, bloqueo de fin con reevaluación pendiente, procedimientos automáticos no vacíos, reevaluación manual histórica, contingencia explícita sin cuenta médica, marcas históricas de reprogramación.
- Falta: usar las acciones clínicas dentro del detalle existente de Admisión; acceso propio de MEDICO sin permisos generales; retirar menú y pantalla independiente; habilitar verificación de procedimientos, retorno manual y salida a COMERCIAL mediante capacidades acotadas.
- Exoneración: ReservationPaymentService ya contempla confirm=true sin pago positivo y valida disponibilidad. La reserva pendiente ajena sigue siendo privada. Se necesita una acción explícita sobre una exoneración autorizada, sin convertir la declaración en confirmación automática ni crear dinero ficticio.
- Intervenciones compartidas imprescindibles: autorización de lectura propia en Admisión, proyección sin datos económicos para cuentas exclusivamente clínicas, permisos operativos acotados en servicios de procedimientos/retorno, redirección del antiguo acceso médico. Se conservan los escritores clínicos existentes.
- Sin nuevas tablas, columnas, permisos ni roles. Sin cambios de catálogo, creación ordinaria, disponibilidad o identidades.

Despliegue: public_html es carpeta física y repositorio vivo; git switch no es atómico. PHP /usr/bin/php 8.3.35, Laravel 9.52.21. Sin verificación de cambio atómico del document root no se promete cero interrupciones. Preparar una intervención breve, con control de escrituras, respaldo y rollback, pendiente de autorización.

# Acciones por estado y rol

Las capacidades efectivas existentes, cuenta activa, visibilidad de cita, versiones, disponibilidad y condiciones económicas siguen siendo obligatorias. Un rol por sí solo no elimina esas condiciones. ADMINISTRADOR + MEDICO conserva ambos alcances; el alcance clínico se limita al doctor_id vinculado.

| Estado/condición | COMERCIAL | ADMISION | ADMINISTRADOR | MEDICO vinculado propio |
|---|---|---|---|---|
| Reserva privada ajena | Oculta | Oculta salvo alcance previo explícito; verlo no concede escritura | Conserva excepción previa de lectura; no escritura por verla | Oculta |
| Reserva privada propia, pendiente, sin pago/crédito/exoneración, precio positivo | Sin excepción | Confirmar sin adelanto: motivo y confirmación explícitos, UUID/revisión, disponibilidad | No por rol ADMINISTRADOR | No |
| Exoneración ya autorizada | Solo flujo y permisos de escritura anteriores | Confirmación de exoneración separada | Flujo anterior, sujeto a alcance | No |
| CONFIRMADA/LEGADO presencial, PROGRAMADO/CONFIRMADO/PACIENTE_LLEGO, sin tiempos posteriores | Llegada por endpoint acotado | Llegada por operación existente | Llegada por operación existente | No por rol clínico |
| EN_ESPERA/LLAMANDO con hora de llegada, sin inicio | Sin actuación médica | Llamado según estado; contingencia solo si corresponde | Igual contingencia administrativa | Iniciar atención propia |
| EN_ATENCION, sin indicación pendiente | Verificar procedimientos solicitados | Verificar procedimientos solicitados | Verificar y solicitud administrativa previa | Reevaluación y Finalizar atención; solicitar procedimientos propios |
| EN_ATENCION con reevaluación pendiente | Checks existentes; retorno manual autorizado | Igual | Igual | Retirar indicación con motivo; finalizar bloqueado |
| Procedimientos parcialmente completados | Sigue pendiente; envío parcial solo con autorización/motivo/confirmación | Igual | Igual | Finalización bloqueada mientras pendiente |
| Todos los procedimientos solicitados completados | Listo automático; no cambia checks ajenos | Igual | Igual | Puede finalizar cuando la reevaluación está lista |
| Indicación sin procedimientos | Marca manual permitida por reglas existentes; auditada | Igual | Igual | No puede inventar realizaciones operativas |
| ATENDIDO sin salida | Registrar salida física por endpoint acotado | Registrar salida existente | Registrar salida existente | No registrar salida por rol clínico |
| ATENDIDO con salida | Sin nueva salida; replay UUID no duplica | Igual | Igual | Sin nuevas actuaciones clínicas |
| Pago registrado, corrección de tarifa/descuento | Denegada incluso con permisos económicos heredados | Denegada incluso con permisos económicos heredados | Acción revisada y autorizada, motivo/antes/después, auditoría; conserva servicio y dinero | Denegada por rol clínico |
| Turno cerrado, fiscal/SUNAT, atribución ambigua, contrato especial, crédito/devolución o excedente | Sin edición directa | Sin edición directa | Mantener bloqueos y resolver por flujo financiero existente | Sin edición |
| Reprogramación elegible y visible | Solo si tiene RESCHEDULE efectivo | Igual | Igual | No por rol clínico |

La reevaluación manual no marca procedimientos como realizados. Los pendientes se conservan y la autorización se audita. La marca manual histórica de ATENDIDO sigue disponible con las reglas anteriores; no crea una cita ni nuevo cobro.

## Contingencia sin cuenta médica

Solo ADMISION/ADMINISTRADOR con permisos previos y configuración habilitada. Presencial, confirmada/legada, llegada registrada y secuencia válida, motivo obligatorio y confirmación. No debe existir ninguna cuenta vinculada al profesional, incluso inactiva, ni vínculo histórico verificado que se haya retirado. El evento tiene `physician_signed=false` y el actor administrativo real. La contingencia no emite indicaciones médicas; no habilita el recorrido clínico completo de Pilar sin cuenta médica verificada.

## Colores y disponibilidad

La referencia histórica REPROGRAMADA es roja y no participa en ocupación, métricas económicas o conteo de citas. La cita vigente mantiene el nuevo horario y enlace. La prioridad de fondos existente se conserva: desactivación/reembolso, salida, reevaluación lista elegible, atendido, presencia, pago y demás casos del catálogo. La confirmación sin adelanto auditada utiliza amarillo sin establecer paid_real ni es_exonerado; presencia y estados posteriores prevalecen. Al registrarse un pago real, desaparece la señal sin adelanto y se aplica la presentación económica vigente.

# Activación de médicos con cuentas existentes — pendiente de producción

> Propuesta de trabajo para la release acumulada sobre PR #57-#61. Este documento no autoriza merge ni despliegue, ni cambios de usuarios productivos.

## Identidades confirmadas en Hostinger mediante consultas de solo lectura
- `users.id=2`: cuenta operativa existente de Gerente Julio Quiroz; correo verificado contra el identificador esperado para el desvío a Admisión; roles exactos pendientes de comprobación.
- `doctors.id=1`: Dr. Julio Augusto Quiroz Guevara, activo, con 9 citas en la consulta de los próximos 7 días.
- `doctors.id=11`: Dra. Pilar Andrea Merino Orbegoso, activa, con 2 citas en el mismo periodo; existe solo en catálogo de médicos y **no tiene cuenta de acceso designada todavía**.

## Contrato nuevo de acceso dual
- Para aprovechar el usuario operativo existente de Julio, un **administrador distinto de esa cuenta** debe verificar identidad y realizar el vínculo único `users.doctor_id=1` desde el editor de vinculación, con confirmación, motivo y evento auditable.
- La asignación de MEDICO desde Usuarios al usuario que ya tenga ADMINISTRADOR añade el rol médico, **sin sustituir ADMINISTRADOR, eliminar privilegios heredados o cambiar contraseña**.
- MEDICO por sí solo conserva sus permisos clínicos mínimos; `MEDICO + ADMINISTRADOR` es la única combinación adicional admitida por `ClinicalAccess`. Agregar MEDICO a cuentas con otros roles heredados requiere revisión previa y se rechaza sin alterarlos.
- El rol ADMINISTRADOR sin MEDICO, aun si recibió accidentalmente permisos clínicos, no habilita la agenda médica; tener `doctor_id` sin MEDICO tampoco.
- El médico autenticado solo puede ver o mutar episodios de `appointments.doctor_id` igual a su propia vinculación. La verificación se repite con bloqueo de usuario, doctor y cita en las acciones transaccionales.
- Las sesiones del usuario afectado se invalidan cuando cambia su rol médico o vínculo profesional, sin impactar a otros usuarios.
- Para la cuenta ID 2 se conserva la preferencia de inicio de sesión hacia `/scheduling-mvp/admission`, mientras «Mi agenda médica» se accede desde el menú.
- Si se quiere retirar MEDICO de un perfil ADMINISTRADOR+MEDICO, el editor de roles exige confirmación adicional. No se quita silenciosamente por elegir ADMINISTRADOR.

## Condiciones pendientes antes de instalar
1. Confirmar los roles reales de `users.id=2` y la existencia de **otro administrador activo** que pueda certificar la vinculación, pues el servicio rechaza la autovinculación de cualquier administrador.
2. La cuenta de la Dra. Pilar **no debe inferirse ni fabricarse**: su registro médico no equivale a una identidad autenticable.
3. **Bloqueante para la continuidad clínica:** al instalar la release, Admisión dejará de poder iniciar o finalizar atención de las citas de todos los médicos, incluida la Dra. Pilar. Con citas pendientes y sin cuenta médica, las acciones clínicas de Pilar quedarían bloqueadas. Antes de la activación total, la dirección clínica debe decidir expresamente entre habilitarle su propio acceso a tiempo, o aprobar un flujo transitorio, restringido, auditable y diseñado por separado para profesionales aún sin login. No habilitar al administrador para suplantar indiscriminadamente la identidad de otro médico.
4. El cumplimiento del punto de confirmación sin adelanto continúa pendiente; no publicitarlo como implementado.
5. Ejecutar y verificar CI del SHA final, revisar diff acumulado de PR #57-#61 y dependencias de migraciones. Copia de seguridad verificable, pruebas de restauración, mantenimiento y plan de rollback aprobados antes de cualquier cambio en Hostinger.

## Rollback
La vuelta al código anterior no deshace de forma segura las nuevas vinculaciones ni permisos, pero conserva el historial. Preferir revertir el código a una versión fijada sin ejecutar `migrate:rollback` general ni borrar `users.doctor_id` después de crear eventos o vínculos. Cualquier recuperación de base de datos requiere reconciliación de citas y caja efectuadas desde el respaldo. No restaurar producción automáticamente.

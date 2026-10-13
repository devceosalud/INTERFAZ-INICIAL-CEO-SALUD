# R05 — cierre de origen inmutable

Correcciones adicionales sobre c5852a5: snapshots patient_id/service_id junto con doctor/fecha; revalidación de origen/evidencia antes de cobro o confirmación; no revelar cita privada desde un reintento de registro; fuente futura excluida por calendario clínico Lima manteniendo app UTC; referido de Quiroz exige origen incluso al seleccionar sus recargos heredados.

Pruebas actuales: PHP suite final **762 tests/5849 assertions**, OK; enfocados R05 **51/543**, OK; JavaScript **212/212**, OK; carrera bancaria repetida3/24 y de indicación3/27, OK. Suite MariaDB final **248 tests/2988 assertions**, OK. Corrida final completa incluye el último caso de origen oculto. No se suman resultados parciales como si fueran una única corrida.

La lectura del vínculo de indicación para obtener médicos se hace antes de abrir transacción, evitando fijar una vista financiera anterior al mutex. El origen y doctores involucrados se revalidan con locks/current reads dentro. Si cambia origen, se rechaza sin cambiar precios/estados/pagos históricos.

Trabajo R09/R10 preservado en stash644bc29462556f9d57762c586d8b1e365bd0059c y copia privada storage/app/qa-rules/agenda-before-r05; no parte de este commit.

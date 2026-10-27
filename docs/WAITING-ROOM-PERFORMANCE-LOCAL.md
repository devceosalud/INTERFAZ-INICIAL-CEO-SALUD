# Waiting room — corrección local 27/10/2026

La API conserva HMAC, replay, permisos por instalación/sede y su proyección explícita. La desconexión inicial fue una jornada del 27/10 sin destinos verificados: solo existía configuración del 26/10. La preparación local crea episodios ficticios nuevos y llegada oficial, sin reescribir ni borrar auditoría.

El contrato experimental es `ceo.waiting-room.v2`, en el endpoint local `/api/integration/v1/waiting-room/snapshot`: añade `operating_count` desde asignaciones verificadas y `revision` de contenido público. Los consultorios sin pacientes pueden ser operativos. La bandera `operational` no cambia el destino físico fijado durante la jornada. Hay que revisar/aprobar configuración real antes de publicar.

`If-None-Match` se firma como sexta línea del canonical HMAC. Las peticiones anteriores sin ese header conservan cinco líneas. Una respuesta 304 lleva ETag y cabeceras de vigencia, secuencia y stream; no es autorización para usar pacientes vencidos. La secuencia cambia solo con el contenido, nunca por el reloj. `updated_at` únicamente invalida caché; el anuncio depende del evento normalizado auditado.

La proyección estrecha consulta 3 SQL en caché y 7 en una reconstrucción típica. Su cuerpo está cifrado. El mapa de nonces es acotado, protegido por lock y conserva el rechazo del esquema anterior durante su ventana vigente. `WaitingRoomTiming` solo registra métricas agregadas cuando APP_ENV=integration y WR_METRICS_FILE está configurado fuera del webroot.

No hay migraciones, cambios de estados clínicos ni conexión productiva. Pruebas API: 12 / 111; AdmissionBoard: 15 / 210; TeleconsultationCare: 8 / 154. Se identificó un fallo heredado de AdmissionTask en AgendaBoardPresenter con PHP 8.0 (spread asociativo); no se modificó ese módulo ajeno a la integración.

Los resultados HTTP reales, prueba sostenida, consumo, audiovisual y procedimiento de arranque/cierre se encuentran en el worktree del llamador: `docs/INFORME-CORRECCION-20261027.md` y `docs/RESULTADOS-PRUEBA-SOSTENIDA.md`. No contienen credenciales. Mantener ambos repositorios sincronizados al revisar el contrato; todavía no está publicado.

# RC CEO Salud — resumen de cierre

**NO-GO productivo. Código integrado listo para revisión en Draft; no autoriza operación real.**

- Rama: `codex/rc-integracion-preproductiva`; nueve PR #5–#13 integrados localmente sin conflictos manuales ni merges GitHub; main permanece `1a2c1895c5ab65d35928fc7244d3cb57e6b4adc4`. Fuentes exactas en pr-inputs.json.
- Aplicación probada: `b388bd3f4816e30506e913aec33234518949db5b`; hash `a95709f76233b2e83b9e603581af47d8877c9b7a0bb355aff9f2c3f75b550ed5`. El SHA final con evidencias/herramientas se publica en el PR, que no se fusionará automáticamente.
- Suites finales: PHP 878/6.850, MariaDB 372/4.153, JavaScript 261; sin fallos finales. 79 casos Chrome aprobados en 17 etapas; un chequeo real de ocultación de pestaña no acreditado. Se conservan los fallos previos y su repetición, sin sumarlos como éxitos.
- Matriz: 17 implementados en desarrollo, 8 parciales, 2 faltantes y 3 cancelados. R25–R30 permanecen en fase posterior; R05 reasignación/reprecio pagado, R12 Excel, R13 seguimiento consolidado, R19 drag/drop siguen pendientes.
- Jornada: usuarios y permisos por UI; dos comerciales, reserva privada, 20+30 y 50, ADD explícito, OCR tres entidades, confirmación, exoneración autorizada, atención completa y revocación de acceso. Persistencia final 8 citas ficticias, 3 pagos por S/100.00, sin duplicados/huérfanos y con histórico intacto.
- R14 cerrado en alcance operativo: acceso privado, clasificación, reemplazo versionado con motivo/actor y evidencia protegida. Nicole revalida su detalle y permisos aunque las filas no cambien. Horarios protegen confirmadas/ADD/fondeadas, rechazan nuevos cruces y preservan herencia.
- Rendimiento 2.000/eight sesiones: p95 ciclo tabla+detalle 2265.736 ms, 54 SQL y 192 bytes sin cambios; sin SLA Hostinger. Detalles por tamaño en informe.
- Restore local: 44 tablas y 324 filas con huellas iguales; 30 archivos privados con hash verificado; copia restaurada en esquema nuevo, sin sobreescribir fuente. No sustituye backup/restore productivo.

## Bloqueos y riesgos pendientes

1. **ALTO:** avisos Composer de Laravel 9, uno alto; corregir framework/compatibilidad y revisar exposición antes de GO. Dependencias transitivas compatibles actualizadas, sin afirmar que resuelven Laravel.
2. **ALTO:** privacidad/admisibilidad Clarity no aprobada para datos sanitarios/financieros; código intacto por instrucción. Responsable debe resolver alcance antes de datos reales.
3. **ALTO operativo:** sin staging equivalente Linux/PHP web, backup/restore productivo ensayado, autodespliegue verificado y aprobación final. Modo financiero, roles participantes/grants, cinco guards, sede y horarios futuros requieren intervención controlada autorizada.
4. **PENDIENTE QA:** transición de visibilidad real de pestaña no acreditada, pese a pruebas unitarias; repetir en navegador interactivo equivalente. Safari/dispositivo físico y jornada completa móvil no se ejecutaron.
5. **MEDIO crecimiento:** rango de fecha de Nicole escanea tabla sin índice dedicado; objetivos actuales pasaron en 2.000 sintéticos. Mantener límites y medir antes de crecer.
6. **FUNCIONAL declarado:** Excel, historial documental consolidado, editor drag/drop y reasignación médica cancelada pendientes; ajustes/créditos/devoluciones generales R25–R30 no se incluyeron. Sobrepago/cambio financiero incompatible se bloquea, sin corrección manual oculta de BD.

## Entregables y próxima intervención

Matriz Markdown/JSON, informe QA, jornada, rendimiento, seguridad, manual de liberación y evidencia saneada en `docs/evidence/rc/final`; manifiesto completo de archivos/migraciones. Pasos de publicación y reversión en RC_CEO_SALUD_LIBERACION.md. Nunca restaurar una BD antigua sobre cobros nuevos; ante incompatibilidad cerrar operaciones y corregir hacia delante con conciliación. No merge, despliegue ni modificación productiva ejecutados. Esperar revisión de Rodrigo y responsables.
